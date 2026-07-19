<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Enums\MaintenanceType;
use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Models\BlockchainSetting;
use App\Models\FuelConsumption;
use App\Models\Maintenance;
use App\Models\MileageRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Services\BlockchainService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('email', 'admin@autochain.local')->exists()) {
            $this->command?->info('Seed déjà appliqué — ignoré.');

            return;
        }

        $admin = User::query()->create([
            'name' => 'Lass Super Admin',
            'email' => 'admin@autochain.local',
            'password' => Hash::make('password'),
            'role' => UserRole::SuperAdmin,
            // Hardhat #0 — importer la clé privée Hardhat dans MetaMask pour le login Web3
            'wallet_address' => '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266',
            'phone' => '+221770000001',
            'is_active' => true,
        ]);

        $gestionnaire = User::query()->create([
            'name' => 'Awa Gestionnaire',
            'email' => 'gestionnaire@autochain.local',
            'password' => Hash::make('password'),
            'role' => UserRole::Gestionnaire,
            'wallet_address' => '0x70997970C51812dc3A010C7d01b50e0d17dc79C8',
            'phone' => '+221770000002',
            'is_active' => true,
        ]);

        $chauffeur = User::query()->create([
            'name' => 'Ibrahima Chauffeur',
            'email' => 'chauffeur@autochain.local',
            'password' => Hash::make('password'),
            'role' => UserRole::Chauffeur,
            'wallet_address' => '0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC',
            'phone' => '+221770000003',
            'is_active' => true,
        ]);

        $garagiste = User::query()->create([
            'name' => 'Omar Garagiste',
            'email' => 'garagiste@autochain.local',
            'password' => Hash::make('password'),
            'role' => UserRole::Garagiste,
            'wallet_address' => '0x90F79bf6EB2c4f870365E785982E1f101E93b906',
            'phone' => '+221770000004',
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Fatou Auditrice',
            'email' => 'auditeur@autochain.local',
            'password' => Hash::make('password'),
            'role' => UserRole::Auditeur,
            'wallet_address' => '0x15d34AAf54267DB7D7c367839AAf71A00a2C6A65',
            'phone' => '+221770000005',
            'is_active' => true,
        ]);

        BlockchainSetting::setValue('network', 'localhost', 'Réseau blockchain');
        BlockchainSetting::setValue('rpc_url', 'http://127.0.0.1:8545', 'URL RPC');
        BlockchainSetting::setValue('contract_address', '', 'Adresse du smart contract (après déploiement Hardhat)');
        BlockchainSetting::setValue('require_double_signature', '1', 'Double signature obligatoire');

        $vehicle = Vehicle::query()->create([
            'vin' => 'VF1RJA00000000001',
            'registration_number' => 'DK-1234-AB',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'color' => 'Blanc',
            'current_mileage' => 42000,
            'status' => VehicleStatus::Affecte,
            'fuel_type' => 'essence',
            'tank_capacity' => 50,
            'insurance_expires_at' => now()->addDays(20),
            'technical_control_expires_at' => now()->addMonths(4),
            'next_service_mileage' => 45000,
            'next_service_at' => now()->addDays(40),
            'created_by' => $gestionnaire->id,
        ]);

        $blockchain = app(BlockchainService::class);
        $tx = $blockchain->prepareProof($vehicle, 'vehicle_registered', [
            'vin_hash' => hash('sha256', $vehicle->vin),
            'registration_hash' => hash('sha256', $vehicle->registration_number),
            'mileage' => $vehicle->current_mileage,
        ], $gestionnaire);
        $blockchain->simulateAnchor($tx);

        VehicleAssignment::query()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $chauffeur->id,
            'assigned_by' => $gestionnaire->id,
            'started_at' => now()->subDays(10),
            'status' => 'active',
            'mileage_at_start' => 40000,
            'driver_acknowledged' => true,
            'acknowledged_at' => now()->subDays(10),
            'notes' => 'Affectation initiale de démonstration',
        ]);

        $mileage = MileageRecord::query()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $chauffeur->id,
            'mileage' => 42000,
            'previous_mileage' => 40000,
            'recorded_at' => now()->subDay(),
            'status' => 'certified',
            'is_certified' => true,
        ]);

        $mileageTx = $blockchain->prepareProof($vehicle, 'mileage_record', [
            'mileage' => $mileage->mileage,
            'previous_mileage' => $mileage->previous_mileage,
        ], $chauffeur);
        $mileageTx = $blockchain->simulateAnchor($mileageTx);
        $mileage->update(['blockchain_tx_hash' => $mileageTx->tx_hash]);

        $maintenance = Maintenance::query()->create([
            'vehicle_id' => $vehicle->id,
            'garage_id' => $garagiste->id,
            'type' => MaintenanceType::Vidange,
            'title' => 'Vidange + filtres',
            'description' => 'Entretien périodique certifié',
            'parts_changed' => ['Filtre à huile', 'Huile 5W30', 'Filtre à air'],
            'mileage_at_service' => 40000,
            'cost' => 85000,
            'performed_at' => now()->subDays(15),
            'is_certified' => true,
            'status' => 'certified',
        ]);

        $maintTx = $blockchain->prepareProof($vehicle, 'maintenance', [
            'type' => $maintenance->type->value,
            'mileage_at_service' => $maintenance->mileage_at_service,
            'parts_hash' => hash('sha256', json_encode($maintenance->parts_changed)),
        ], $garagiste);
        $maintTx = $blockchain->simulateAnchor($maintTx);
        $maintenance->update(['blockchain_tx_hash' => $maintTx->tx_hash]);

        FuelConsumption::query()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $chauffeur->id,
            'liters' => 40,
            'cost' => 28000,
            'mileage_at_fill' => 40500,
            'filled_at' => now()->subDays(8),
            'station' => 'Total Dakar',
            'consumption_l_per_100km' => null,
        ]);

        FuelConsumption::query()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $chauffeur->id,
            'liters' => 38,
            'cost' => 26600,
            'mileage_at_fill' => 42000,
            'filled_at' => now()->subDay(),
            'station' => 'Shell Almadies',
            'consumption_l_per_100km' => 2.53,
        ]);

        // Document factice (hash uniquement, fichier optionnel en démo)
        $vehicle->documents()->create([
            'uploaded_by' => $gestionnaire->id,
            'type' => DocumentType::Assurance,
            'title' => 'Attestation assurance 2026',
            'file_path' => 'documents/vehicules/demo/assurance.pdf',
            'original_name' => 'assurance.pdf',
            'file_hash' => hash('sha256', 'demo-assurance-content'),
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'is_public' => false,
            'expires_at' => now()->addDays(20),
        ]);

        $this->command?->info('Autochain Emma+ seedé.');
        $this->command?->table(
            ['Rôle', 'Email', 'Mot de passe'],
            [
                ['Super Admin', 'admin@autochain.local', 'password'],
                ['Gestionnaire', 'gestionnaire@autochain.local', 'password'],
                ['Chauffeur', 'chauffeur@autochain.local', 'password'],
                ['Garagiste', 'garagiste@autochain.local', 'password'],
                ['Auditeur', 'auditeur@autochain.local', 'password'],
            ]
        );

        unset($admin);
    }
}
