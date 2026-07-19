<?php

namespace App\Enums;

enum AlertType: string
{
    case Entretien = 'entretien';
    case Assurance = 'assurance';
    case ControleTechnique = 'controle_technique';
    case Kilometrage = 'kilometrage';
    case Document = 'document';
    case Blockchain = 'blockchain';
}
