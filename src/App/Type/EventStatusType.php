<?php

namespace App\Type;

class EventStatusType
{
    const WAITING_FOR_APPROVE = 0;
    const APPROVED = 1;
    const DECLINED = 2;
    const WAITING_PARTNER_RESPONSE = 3;
    const DECLINED_BY_PARTNER = 4;
    const DROPPED = 5;
    const CANCELLED = 6;

    const NAMES = ['Waiting for approve', 'Approved', 'Declined', 'Waiting partner response', 'Declined by partner', 'Dropped', 'Cancelled'];
    const COLORS = ['label-info', 'label-success', 'label-danger', 'label-info', 'label-danger', 'label-danger', 'label-danger'];
}