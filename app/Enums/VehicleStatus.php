<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Disponible = 'disponible';
    case Affecte = 'affecte';
    case EnMaintenance = 'en_maintenance';
    case HorsService = 'hors_service';
    case Archive = 'archive';
}
