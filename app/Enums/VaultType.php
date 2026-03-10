<?php

namespace App\Enums;

enum VaultType: string
{
    case GOLD = 'GOLD';
    case SILVER = 'SILVER';
    case CASH = 'CASH';
    case BANK = 'BANK';
}
