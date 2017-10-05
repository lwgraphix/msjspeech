<?php

namespace App\Util;

class EventStatusType
{
    const WAITING_FOR_APPROVE = 0;
    const APPROVED = 1;
    const DECLINED = 2;
    const WAITING_PARTNER_RESPONSE = 3;
    const DECLINED_BY_PARTNER = 4;
    const DROPPED = 5;

    const NAMES = ['Waiting for approve', 'Approved', 'Declined by officer', 'Waiting partner response', 'Declined by partner', 'Dropped'];
    const COLORS = ['label-info', 'label-success', 'label-danger', 'label-info', 'label-danger', 'label-danger'];
}