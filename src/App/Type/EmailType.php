<?php

namespace App\Type;

class EmailType
{
    const MEMBERSHIP_REGISTRATION = 0; // done
    const USER_ROLE_CHANGE = 1;
    const TOURNAMENT_JOIN = 2;
    const TOURNAMENT_DROP_BEFORE_DEADLINE = 3;
    const TOURNAMENT_DROP_AFTER_DEADLINE = 4;
    const TOURNAMENT_PARTNER_DROP_BEFORE_DEADLINE = 5;
    const TOURNAMENT_PARTNER_DROP_AFTER_DEADLINE = 6;
    const TRANSACTION_CREATE = 7;
    const PARTNER_REQUEST = 8;
    const PARTNER_REQUEST_DECLINE = 9;
    const PARTNER_REQUEST_ACCEPT = 10;
    const PARTNER_REQUEST_EXPIRED = 11;
    const TOURNAMENT_JUDGE = 12;
    const ACCOUNT_RESTORE_ACCESS = 13; // done

    const NAMES = [
        'Membership registration',
        'User role change',
        'Tournament join',
        'Tournament drop before deadline',
        'Tournament drop after deadline',
        'Tournament partner drop before deadline',
        'Tournament partner drop after deadline',
        'Transaction create',
        'Partner request',
        'Partner request decline',
        'Partner request accept',
        'Partner request expired',
        'Tournament judge',
        'Account restore access'
    ];
}