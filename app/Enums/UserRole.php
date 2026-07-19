<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Gestionnaire = 'gestionnaire';
    case Chauffeur = 'chauffeur';
    case Garagiste = 'garagiste';
    case Auditeur = 'auditeur';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrateur',
            self::Gestionnaire => 'Gestionnaire de parc',
            self::Chauffeur => 'Chauffeur',
            self::Garagiste => 'Garagiste agréé',
            self::Auditeur => 'Auditeur / Acheteur',
        };
    }

    public function canWrite(): bool
    {
        return $this !== self::Auditeur;
    }
}
