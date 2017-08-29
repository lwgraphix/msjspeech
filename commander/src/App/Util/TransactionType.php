<?php

namespace App\Util;

class TransactionType
{
    const CARD_DEPOSIT = 0;
    const MEMBERSHIP_FEE = 1;
    const MANUAL = 2;
    const TOURNAMENT_JOIN = 3;
    const TOURNAMENT_REFUND = 4;
    const TOURNAMENT_FEE = 5;

    const NAMES = [
        'Card deposit',
        'Membership fee',
        'Manual',
        'Tournament join',
        'Tournament refund',
        'Tournament fee'
    ];
}