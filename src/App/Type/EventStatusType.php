<?php

namespace App\Type;

class EventStatusType
{
    const WAITING_FOR_APPROVE = 0;
    const APPROVED = 1;
    const DECLINED = 2;
    const WAITING_PARTNER_RESPONSE = 3;

    const NAMES = ['Waiting for approve', 'Approved', 'Declined', 'Waiting partner response'];
}