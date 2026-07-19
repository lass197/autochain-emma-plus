<?php

namespace App\Console\Commands;

use App\Services\Web3\ContractAnchorService;
use Illuminate\Console\Command;

class BlockchainStatusCommand extends Command
{
    protected $signature = 'autochain:blockchain-status';

    protected $description = 'Affiche l’état de la couche blockchain Autochain Emma+';

    public function handle(ContractAnchorService $anchor): int
    {
        $status = $anchor->status();

        $this->table(
            ['Clé', 'Valeur'],
            collect($status)->map(fn ($value, $key) => [
                $key,
                is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? 'null'),
            ])->values()->all()
        );

        return self::SUCCESS;
    }
}
