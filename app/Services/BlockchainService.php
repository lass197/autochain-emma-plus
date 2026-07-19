<?php

namespace App\Services;

use App\Models\BlockchainSetting;
use App\Models\BlockchainTransaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Str;

class BlockchainService
{
    /**
     * Prépare une preuve on-chain sans données nominatives (RGPD).
     * Seuls technical_id + hash du payload sont destinés à la blockchain.
     */
    public function prepareProof(Vehicle $vehicle, string $actionType, array $payload, User $initiator): BlockchainTransaction
    {
        $sanitized = [
            'technical_id' => $vehicle->technical_id,
            'action' => $actionType,
            'data' => $payload,
            'timestamp' => now()->toIso8601String(),
        ];

        $payloadHash = hash('sha256', json_encode($sanitized, JSON_THROW_ON_ERROR));

        return BlockchainTransaction::query()->create([
            'vehicle_id' => $vehicle->id,
            'initiated_by' => $initiator->id,
            'action_type' => $actionType,
            'payload_hash' => $payloadHash,
            'payload' => $sanitized,
            'status' => 'pending',
        ]);
    }

    public function signAsAdmin(BlockchainTransaction $tx, User $admin): BlockchainTransaction
    {
        $tx->fill([
            'signed_by_admin' => $admin->id,
            'admin_signed_at' => now(),
        ]);
        $tx->status = $this->resolveStatus($tx);
        $tx->save();

        return $tx->fresh();
    }

    public function signAsBuyer(BlockchainTransaction $tx, User $buyer): BlockchainTransaction
    {
        $tx->fill([
            'signed_by_buyer' => $buyer->id,
            'buyer_signed_at' => now(),
        ]);
        $tx->status = $this->resolveStatus($tx);
        $tx->save();

        return $tx->fresh();
    }

    /**
     * Confirme une transaction après ancrage blockchain (appelé par le bridge Web3).
     */
    public function confirm(BlockchainTransaction $tx, string $txHash, ?int $blockNumber = null): BlockchainTransaction
    {
        $requireDouble = config('autochain.blockchain.require_double_signature', true);

        if ($requireDouble && in_array($tx->action_type, ['transfer', 'sale', 'archive'], true) && ! $tx->isFullySigned()) {
            abort(422, 'Double signature (admin + acheteur) requise pour cette opération sensible.');
        }

        $tx->update([
            'tx_hash' => $txHash,
            'block_number' => $blockNumber,
            'status' => 'confirmed',
        ]);

        if ($tx->vehicle) {
            $tx->vehicle->update([
                'blockchain_hash' => $tx->payload_hash,
            ]);
        }

        return $tx->fresh(['vehicle']);
    }

    /**
     * Simulation locale pour le développement (sans nœud Ethereum).
     */
    public function simulateAnchor(BlockchainTransaction $tx): BlockchainTransaction
    {
        return $this->confirm(
            $tx,
            '0x'.Str::lower(bin2hex(random_bytes(32))),
            random_int(1, 999999)
        );
    }

    public function settings(): array
    {
        return [
            'network' => BlockchainSetting::getValue('network', config('autochain.blockchain.network')),
            'rpc_url' => BlockchainSetting::getValue('rpc_url', config('autochain.blockchain.rpc_url')),
            'contract_address' => BlockchainSetting::getValue('contract_address', config('autochain.blockchain.contract_address')),
            'require_double_signature' => filter_var(
                BlockchainSetting::getValue(
                    'require_double_signature',
                    config('autochain.blockchain.require_double_signature') ? '1' : '0'
                ),
                FILTER_VALIDATE_BOOLEAN
            ),
        ];
    }

    public function updateSettings(array $data): array
    {
        foreach ($data as $key => $value) {
            BlockchainSetting::setValue($key, is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        return $this->settings();
    }

    protected function resolveStatus(BlockchainTransaction $tx): string
    {
        if ($tx->status === 'confirmed') {
            return 'confirmed';
        }

        $requireDouble = config('autochain.blockchain.require_double_signature', true);
        $sensitive = in_array($tx->action_type, ['transfer', 'sale', 'archive'], true);

        if ($sensitive && $requireDouble) {
            return $tx->isFullySigned() ? 'ready' : 'awaiting_signatures';
        }

        return $tx->signed_by_admin ? 'ready' : 'pending';
    }
}
