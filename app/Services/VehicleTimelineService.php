<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Support\Collection;

class VehicleTimelineService
{
    /**
     * Historique combiné blockchain + backend pour le frontend.
     */
    public function build(Vehicle $vehicle): Collection
    {
        $events = collect();

        foreach ($vehicle->mileageRecords()->orderBy('recorded_at')->get() as $record) {
            $events->push([
                'type' => 'mileage',
                'source' => $record->is_certified ? 'blockchain' : 'backend',
                'occurred_at' => $record->recorded_at?->toIso8601String(),
                'title' => 'Relevé kilométrique',
                'summary' => "{$record->mileage} km",
                'meta' => [
                    'mileage' => $record->mileage,
                    'previous_mileage' => $record->previous_mileage,
                    'tx_hash' => $record->blockchain_tx_hash,
                    'is_certified' => $record->is_certified,
                ],
            ]);
        }

        foreach ($vehicle->maintenances()->orderBy('performed_at')->get() as $maintenance) {
            $events->push([
                'type' => 'maintenance',
                'source' => $maintenance->is_certified ? 'blockchain' : 'backend',
                'occurred_at' => $maintenance->performed_at?->toIso8601String(),
                'title' => $maintenance->title,
                'summary' => $maintenance->description,
                'meta' => [
                    'maintenance_type' => $maintenance->type?->value,
                    'parts_changed' => $maintenance->parts_changed,
                    'cost' => $maintenance->cost,
                    'mileage_at_service' => $maintenance->mileage_at_service,
                    'tx_hash' => $maintenance->blockchain_tx_hash,
                    'is_certified' => $maintenance->is_certified,
                ],
            ]);
        }

        foreach ($vehicle->assignments()->orderBy('started_at')->get() as $assignment) {
            $events->push([
                'type' => 'assignment',
                'source' => 'backend',
                'occurred_at' => $assignment->started_at?->toIso8601String(),
                'title' => 'Affectation chauffeur',
                'summary' => "Statut: {$assignment->status}",
                'meta' => [
                    'driver_id' => $assignment->driver_id,
                    'status' => $assignment->status,
                    'mileage_at_start' => $assignment->mileage_at_start,
                    'ended_at' => $assignment->ended_at?->toIso8601String(),
                ],
            ]);
        }

        foreach ($vehicle->documents()->orderBy('created_at')->get() as $document) {
            $events->push([
                'type' => 'document',
                'source' => $document->ipfs_cid ? 'ipfs' : 'backend',
                'occurred_at' => $document->created_at?->toIso8601String(),
                'title' => $document->title,
                'summary' => "Document {$document->type?->value}",
                'meta' => [
                    'file_hash' => $document->file_hash,
                    'ipfs_cid' => $document->ipfs_cid,
                    'document_type' => $document->type?->value,
                ],
            ]);
        }

        foreach ($vehicle->blockchainTransactions()->orderBy('created_at')->get() as $tx) {
            $events->push([
                'type' => 'blockchain',
                'source' => 'blockchain',
                'occurred_at' => $tx->created_at?->toIso8601String(),
                'title' => "Transaction {$tx->action_type}",
                'summary' => "Statut: {$tx->status}",
                'meta' => [
                    'payload_hash' => $tx->payload_hash,
                    'tx_hash' => $tx->tx_hash,
                    'block_number' => $tx->block_number,
                    'status' => $tx->status,
                ],
            ]);
        }

        return $events->sortBy('occurred_at')->values();
    }
}
