<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AlertService
{
    public function generateForFleet(): Collection
    {
        $created = collect();

        Vehicle::query()
            ->whereNull('deleted_at')
            ->each(function (Vehicle $vehicle) use ($created): void {
                $created->push(...$this->checkVehicle($vehicle));
            });

        return $created->filter();
    }

    public function checkVehicle(Vehicle $vehicle): Collection
    {
        $alerts = collect();
        $managers = User::query()
            ->whereIn('role', [UserRole::Gestionnaire->value, UserRole::SuperAdmin->value])
            ->where('is_active', true)
            ->get();

        $insuranceDays = config('autochain.alerts.insurance_days_before', 30);
        $techDays = config('autochain.alerts.technical_control_days_before', 30);
        $mileageThreshold = config('autochain.alerts.service_mileage_threshold', 500);

        if ($vehicle->insurance_expires_at) {
            $daysLeft = Carbon::today()->diffInDays($vehicle->insurance_expires_at, false);
            if ($daysLeft <= $insuranceDays) {
                $alerts->push($this->upsertAlert(
                    $vehicle,
                    $managers,
                    AlertType::Assurance,
                    $daysLeft < 0 ? 'critical' : 'warning',
                    'Assurance à renouveler',
                    "L'assurance du véhicule {$vehicle->registration_number} expire le {$vehicle->insurance_expires_at->format('d/m/Y')}.",
                    $vehicle->insurance_expires_at
                ));
            }
        }

        if ($vehicle->technical_control_expires_at) {
            $daysLeft = Carbon::today()->diffInDays($vehicle->technical_control_expires_at, false);
            if ($daysLeft <= $techDays) {
                $alerts->push($this->upsertAlert(
                    $vehicle,
                    $managers,
                    AlertType::ControleTechnique,
                    $daysLeft < 0 ? 'critical' : 'warning',
                    'Contrôle technique à prévoir',
                    "Le contrôle technique du véhicule {$vehicle->registration_number} expire le {$vehicle->technical_control_expires_at->format('d/m/Y')}.",
                    $vehicle->technical_control_expires_at
                ));
            }
        }

        if ($vehicle->next_service_mileage !== null
            && $vehicle->current_mileage >= ($vehicle->next_service_mileage - $mileageThreshold)
        ) {
            $alerts->push($this->upsertAlert(
                $vehicle,
                $managers,
                AlertType::Entretien,
                'warning',
                'Entretien proche',
                "Le véhicule {$vehicle->registration_number} approche du prochain entretien ({$vehicle->current_mileage}/{$vehicle->next_service_mileage} km).",
                $vehicle->next_service_at
            ));
        }

        if ($vehicle->next_service_at && Carbon::today()->diffInDays($vehicle->next_service_at, false) <= 14) {
            $alerts->push($this->upsertAlert(
                $vehicle,
                $managers,
                AlertType::Entretien,
                'info',
                'Entretien planifié',
                "Entretien prévu le {$vehicle->next_service_at->format('d/m/Y')} pour {$vehicle->registration_number}.",
                $vehicle->next_service_at
            ));
        }

        return $alerts->flatten();
    }

    protected function upsertAlert(
        Vehicle $vehicle,
        Collection $managers,
        AlertType $type,
        string $severity,
        string $title,
        string $message,
        mixed $dueDate
    ): Collection {
        return $managers->map(function (User $manager) use ($vehicle, $type, $severity, $title, $message, $dueDate) {
            return Alert::query()->firstOrCreate(
                [
                    'vehicle_id' => $vehicle->id,
                    'user_id' => $manager->id,
                    'type' => $type->value,
                    'title' => $title,
                    'is_resolved' => false,
                ],
                [
                    'severity' => $severity,
                    'message' => $message,
                    'due_date' => $dueDate,
                ]
            );
        });
    }
}
