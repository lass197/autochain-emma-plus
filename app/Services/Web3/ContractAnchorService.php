<?php

namespace App\Services\Web3;

use App\Models\BlockchainTransaction;
use App\Models\User;
use App\Services\BlockchainService;
use Illuminate\Support\Facades\Log;
use kornrunner\Ethereum\Transaction;
use RuntimeException;

class ContractAnchorService
{
    public function __construct(
        protected BlockchainService $blockchain,
    ) {}

    public function status(): array
    {
        $settings = $this->blockchain->settings();
        $rpcUrl = $settings['rpc_url'] ?? config('autochain.blockchain.rpc_url');
        $client = new EthereumRpcClient($rpcUrl);

        $reachable = $client->isReachable();
        $operatorConfigured = (bool) config('autochain.blockchain.operator_private_key');
        $contract = $this->normalizeContractAddress(
            $settings['contract_address'] ?: config('autochain.blockchain.contract_address')
        );

        return [
            'mode' => $this->resolveMode($reachable, $operatorConfigured, $contract),
            'rpc_reachable' => $reachable,
            'rpc_url' => $rpcUrl,
            'network' => $settings['network'],
            'chain_id' => $reachable ? $client->chainId() : (int) config('autochain.blockchain.chain_id'),
            'block_number' => $reachable ? $client->blockNumber() : null,
            'contract_address' => $contract,
            'operator_configured' => $operatorConfigured,
            'operator_address' => $this->operatorAddress(),
            'require_double_signature' => $settings['require_double_signature'],
        ];
    }

    /**
     * Ancre une preuve : on-chain si nœud + clé opérateur, sinon simulation.
     */
    public function anchor(BlockchainTransaction $tx, bool $forceSimulate = false): BlockchainTransaction
    {
        if ($tx->status === 'confirmed') {
            return $tx;
        }

        $status = $this->status();

        if ($forceSimulate || $status['mode'] !== 'onchain') {
            $confirmed = $this->blockchain->simulateAnchor($tx);
            $confirmed->notes = ($confirmed->notes ? $confirmed->notes.' | ' : '').'mode=simulate';
            $confirmed->save();

            return $confirmed->fresh();
        }

        return $this->anchorOnChain($tx);
    }

    public function buildCalldata(BlockchainTransaction $tx): array
    {
        $vehicle = $tx->vehicle;
        if (! $vehicle) {
            throw new RuntimeException('Transaction sans véhicule.');
        }

        $technicalId = AbiEncoder::technicalIdToBytes32($vehicle->technical_id);
        $payloadHash = AbiEncoder::hashToBytes32($tx->payload_hash);
        $settings = $this->blockchain->settings();

        $calldata = match ($tx->action_type) {
            'vehicle_registered' => AbiEncoder::encodeCall(
                'registerVehicle(bytes32,bytes32,bytes32,uint256)',
                [
                    $technicalId,
                    AbiEncoder::hashToBytes32($tx->payload['data']['vin_hash'] ?? hash('sha256', $vehicle->vin)),
                    AbiEncoder::hashToBytes32($tx->payload['data']['registration_hash'] ?? hash('sha256', $vehicle->registration_number)),
                    ['type' => 'uint256', 'value' => (int) ($tx->payload['data']['mileage'] ?? $vehicle->current_mileage)],
                ]
            ),
            'mileage_record' => AbiEncoder::encodeCall(
                'certifyMileage(bytes32,uint256,bytes32)',
                [
                    $technicalId,
                    ['type' => 'uint256', 'value' => (int) ($tx->payload['data']['mileage'] ?? $vehicle->current_mileage)],
                    $payloadHash,
                ]
            ),
            'maintenance' => AbiEncoder::encodeCall(
                'certifyMaintenance(bytes32,uint256,bytes32)',
                [
                    $technicalId,
                    ['type' => 'uint256', 'value' => (int) ($tx->payload['data']['mileage_at_service'] ?? $vehicle->current_mileage)],
                    $payloadHash,
                ]
            ),
            'transfer', 'sale', 'archive' => AbiEncoder::encodeCall(
                'executeSensitiveAction(bytes32,bytes32,address)',
                [
                    $technicalId,
                    $payloadHash,
                    [
                        'type' => 'address',
                        'value' => $tx->buyerSigner?->wallet_address
                            ?? config('autochain.blockchain.default_buyer_address')
                            ?? '0x0000000000000000000000000000000000000001',
                    ],
                ]
            ),
            default => AbiEncoder::encodeCall(
                'certifyMileage(bytes32,uint256,bytes32)',
                [
                    $technicalId,
                    ['type' => 'uint256', 'value' => (int) $vehicle->current_mileage],
                    $payloadHash,
                ]
            ),
        };

        return [
            'to' => $this->normalizeContractAddress(
                $settings['contract_address'] ?: config('autochain.blockchain.contract_address')
            ),
            'data' => $calldata,
            'chain_id' => (int) config('autochain.blockchain.chain_id', 31337),
            'technical_id_bytes32' => $technicalId,
            'payload_hash' => $payloadHash,
            'action_type' => $tx->action_type,
        ];
    }

    protected function anchorOnChain(BlockchainTransaction $tx): BlockchainTransaction
    {
        $privateKey = ltrim((string) config('autochain.blockchain.operator_private_key'), '0x');
        $settings = $this->blockchain->settings();
        $contract = $this->normalizeContractAddress(
            $settings['contract_address'] ?: config('autochain.blockchain.contract_address')
        );
        $rpcUrl = $settings['rpc_url'] ?? config('autochain.blockchain.rpc_url');
        $chainId = (int) config('autochain.blockchain.chain_id', 31337);

        if (! $privateKey || ! $contract) {
            throw new RuntimeException('Clé opérateur ou adresse du contrat manquante.');
        }

        $client = new EthereumRpcClient($rpcUrl);
        $from = $this->operatorAddress();
        $nonce = $client->getTransactionCount($from);
        $gasPrice = $client->gasPrice();
        $call = $this->buildCalldata($tx);

        $transaction = new Transaction(
            '0x'.dechex($nonce),
            $gasPrice,
            '0x'.dechex(800000),
            $contract,
            '0x0',
            $call['data'],
        );

        $raw = '0x'.$transaction->getRaw($privateKey, $chainId);
        $txHash = $client->sendRawTransaction($raw);

        // Attente courte du receipt
        $blockNumber = null;
        for ($i = 0; $i < 8; $i++) {
            usleep(400000);
            $receipt = $client->getTransactionReceipt($txHash);
            if ($receipt) {
                if (($receipt['status'] ?? '0x0') === '0x0') {
                    throw new RuntimeException('Transaction on-chain échouée (revert).');
                }
                $blockNumber = isset($receipt['blockNumber']) ? hexdec($receipt['blockNumber']) : null;
                break;
            }
        }

        $confirmed = $this->blockchain->confirm($tx, $txHash, $blockNumber);
        $confirmed->notes = trim(($confirmed->notes ? $confirmed->notes.' | ' : '').'mode=onchain');
        $confirmed->save();

        Log::info('Autochain ancrage on-chain', [
            'tx_id' => $tx->id,
            'tx_hash' => $txHash,
            'block' => $blockNumber,
        ]);

        return $confirmed->fresh();
    }

    protected function operatorAddress(): ?string
    {
        $privateKey = config('autochain.blockchain.operator_private_key');
        if (! $privateKey) {
            return null;
        }

        try {
            $ec = new \Elliptic\EC('secp256k1');
            $key = $ec->keyFromPrivate(ltrim($privateKey, '0x'), 'hex');
            $publicKey = substr($key->getPublic(false, 'hex'), 2);
            $address = substr(\kornrunner\Keccak::hash(hex2bin($publicKey), 256), 24);

            return '0x'.strtolower($address);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveMode(bool $reachable, bool $operatorConfigured, ?string $contract): string
    {
        if ($reachable && $operatorConfigured && $contract) {
            return 'onchain';
        }

        return 'simulate';
    }

    protected function normalizeContractAddress(?string $address): ?string
    {
        if (! $address) {
            return null;
        }

        $normalized = strtolower($address);

        if ($normalized === '0x0000000000000000000000000000000000000000') {
            return null;
        }

        return $address;
    }
}
