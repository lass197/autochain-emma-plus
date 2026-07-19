<?php

namespace App\Enums;

enum DocumentType: string
{
    case CarteGrise = 'carte_grise';
    case Assurance = 'assurance';
    case Facture = 'facture';
    case ControleTechnique = 'controle_technique';
    case Certificat = 'certificat';
    case Autre = 'autre';
}
