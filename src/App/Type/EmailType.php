<?php

namespace App\Type;

class EmailType
{
    const MEMBERSHIP_REGISTRATION = 0; // done
    const USER_ROLE_CHANGE = 1; // done
    const TOURNAMENT_JOIN = 2; // done
    const TOURNAMENT_DROP_BEFORE_DEADLINE = 3;
    const TOURNAMENT_DROP_AFTER_DEADLINE = 4;
    const TOURNAMENT_PARTNER_DROP_BEFORE_DEADLINE = 5;
    const TOURNAMENT_PARTNER_DROP_AFTER_DEADLINE = 6;
    const TRANSACTION_CREATE = 7; // done
    const PARTNER_REQUEST = 8; // done
    const PARTNER_REQUEST_DECLINE = 9; // done
    const PARTNER_REQUEST_ACCEPT = 10; // done
    const PARTNER_REQUEST_EXPIRED = 11; // done
    const TOURNAMENT_JUDGE = 12; // done
    const ACCOUNT_RESTORE_ACCESS = 13; // done
    const PARTNER_CANCELLED = 14; // done
    const TOURNAMENT_REGISTRATION_APPROVED = 15;
    const SYSTEM_SETTINGS_CHANGED = 16;
    const TOURNAMENT_REGISTRATION_REJECTED = 17;
    const EMAIL_TEMPLATE_CHANGED = 18;

    const NAMES = [
        'Membership registration',
        'User role change',
        'Tournament registration',
        'Tournament drop before deadline',
        'Tournament drop after deadline',
        'Tournament partner drop before deadline',
        'Tournament partner drop after deadline',
        'Manual transaction',
        'Partner request',
        'Partner request declined',
        'Partner request accepted',
        'Partner request expired',
        'Tournament judge',
        'Password recovery',
        'Partner request cancelled',
        'Tournament registration approved',
        'System settings changed',
        'Tournament registration rejected',
        'Email template changed'
    ];
}