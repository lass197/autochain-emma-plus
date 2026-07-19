<?php

namespace App\Enums;

enum MaintenanceType: string
{
    case Revision = 'revision';
    case Reparation = 'reparation';
    case Vidange = 'vidange';
    case Pneus = 'pneus';
    case Freins = 'freins';
    case Controle = 'controle';
    case Autre = 'autre';
}
