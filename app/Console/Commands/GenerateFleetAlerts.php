<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

class GenerateFleetAlerts extends Command
{
    protected $signature = 'autochain:alerts';

    protected $description = 'Génère les alertes automatiques (assurances, entretiens, contrôles)';

    public function handle(AlertService $alertService): int
    {
        $created = $alertService->generateForFleet();
        $this->info("Alertes traitées : {$created->count()}");

        return self::SUCCESS;
    }
}
