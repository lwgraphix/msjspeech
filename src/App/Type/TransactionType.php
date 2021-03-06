<?php

namespace App\Type;

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
        'Membership contribution',
        'Manual',
        'Tournament registration',
        'Tournament refund',
        'Tournament fine'
    ];
}