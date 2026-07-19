<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlockchainTransaction;
use App\Models\Vehicle;
use App\Services\BlockchainService;
use App\Services\Web3\ContractAnchorService;
use App\Services\Web3\EthereumRpcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BlockchainController extends Controller
{
    public function settings(BlockchainService $blockchain): JsonResponse
    {
        return response()->json(['settings' => $blockchain->settings()]);
    }

    public function updateSettings(Request $request, BlockchainService $blockchain): JsonResponse
    {
        $data = $request->validate([
            'network' => ['nullable', 'string', 'max:100'],
            'rpc_url' => ['nullable', 'url'],
            'contract_address' => ['nullable', 'string', 'max:100'],
            'require_double_signature' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'message' => 'Configuration smart contract mise à jour.',
            'settings' => $blockchain->updateSettings($data),
        ]);
    }

    public function status(ContractAnchorService $anchor): JsonResponse
    {
        return response()->json(['status' => $anchor->status()]);
    }

    public function index(Request $request): JsonResponse
    {
        $txs = BlockchainTransaction::query()
            ->with(['vehicle:id,technical_id,registration_number', 'initiator:id,name'])
            ->when($request->vehicle_id, fn ($q, $id) => $q->where('vehicle_id', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($txs);
    }

    public function prepare(Request $request, BlockchainService $blockchain): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'action_type' => ['required', 'string', 'max:100'],
            'payload' => ['nullable', 'array'],
        ]);

        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        $tx = $blockchain->prepareProof(
            $vehicle,
            $data['action_type'],
            $data['payload'] ?? [],
            $request->user()
        );

        return response()->json([
            'message' => 'Preuve préparée (sans données nominatives).',
            'transaction' => $tx,
        ], 201);
    }

    public function calldata(BlockchainTransaction $transaction, ContractAnchorService $anchor): JsonResponse
    {
        try {
            return response()->json([
                'transaction_id' => $transaction->id,
                'calldata' => $anchor->buildCalldata($transaction),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function signAdmin(Request $request, BlockchainTransaction $transaction, BlockchainService $blockchain): JsonResponse
    {
        $tx = $blockchain->signAsAdmin($transaction, $request->user());

        return response()->json(['message' => 'Signature admin enregistrée.', 'transaction' => $tx]);
    }

    public function signBuyer(Request $request, BlockchainTransaction $transaction, BlockchainService $blockchain): JsonResponse
    {
        $tx = $blockchain->signAsBuyer($transaction, $request->user());

        return response()->json(['message' => 'Signature acheteur enregistrée.', 'transaction' => $tx]);
    }

    public function confirm(Request $request, BlockchainTransaction $transaction, BlockchainService $blockchain): JsonResponse
    {
        $data = $request->validate([
            'tx_hash' => ['required', 'string', 'max:100'],
            'block_number' => ['nullable', 'integer', 'min:0'],
        ]);

        // Vérifie le receipt si le RPC est joignable
        $settings = $blockchain->settings();
        $client = new EthereumRpcClient($settings['rpc_url'] ?? config('autochain.blockchain.rpc_url'));
        if ($client->isReachable()) {
            $receipt = $client->getTransactionReceipt($data['tx_hash']);
            if (! $receipt) {
                return response()->json(['message' => 'Receipt introuvable pour ce tx_hash.'], 422);
            }
            if (($receipt['status'] ?? '0x0') === '0x0') {
                return response()->json(['message' => 'Transaction on-chain en échec.'], 422);
            }
            $data['block_number'] = isset($receipt['blockNumber'])
                ? hexdec($receipt['blockNumber'])
                : $data['block_number'];
        }

        $tx = $blockchain->confirm($transaction, $data['tx_hash'], $data['block_number'] ?? null);

        return response()->json(['message' => 'Transaction confirmée on-chain.', 'transaction' => $tx]);
    }

    public function anchor(
        Request $request,
        BlockchainTransaction $transaction,
        ContractAnchorService $anchor,
    ): JsonResponse {
        $data = $request->validate([
            'force_simulate' => ['nullable', 'boolean'],
        ]);

        try {
            $tx = $anchor->anchor($transaction, (bool) ($data['force_simulate'] ?? false));
        } catch (RuntimeException $e) {
            if (config('autochain.blockchain.allow_simulate_fallback', true)) {
                $tx = $anchor->anchor($transaction, true);

                return response()->json([
                    'message' => 'Ancrage on-chain impossible, fallback simulation : '.$e->getMessage(),
                    'transaction' => $tx,
                    'mode' => 'simulate',
                ]);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $mode = str_contains((string) $tx->notes, 'mode=onchain') ? 'onchain' : 'simulate';

        return response()->json([
            'message' => $mode === 'onchain'
                ? 'Preuve ancrée sur la blockchain.'
                : 'Ancrage simulé (nœud ou contrat non configuré).',
            'transaction' => $tx,
            'mode' => $mode,
        ]);
    }

    public function simulate(BlockchainTransaction $transaction, BlockchainService $blockchain): JsonResponse
    {
        $tx = $blockchain->simulateAnchor($transaction);

        return response()->json([
            'message' => 'Ancrage simulé (environnement de développement).',
            'transaction' => $tx,
        ]);
    }

    public function vehicleProof(Vehicle $vehicle): JsonResponse
    {
        return response()->json([
            'technical_id' => $vehicle->technical_id,
            'blockchain_hash' => $vehicle->blockchain_hash,
            'ipfs_cid' => $vehicle->ipfs_cid,
            'current_mileage' => $vehicle->current_mileage,
            'transactions' => $vehicle->blockchainTransactions()
                ->latest()
                ->limit(20)
                ->get(['id', 'action_type', 'payload_hash', 'tx_hash', 'block_number', 'status', 'created_at']),
        ]);
    }
}
