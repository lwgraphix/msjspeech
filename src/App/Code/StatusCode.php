<?php

namespace App\Code;

class StatusCode
{
    const USER_NO_PERMISSION = 0;
    const USER_BAD_CREDENTIALS = 1;
    const USER_ROLE_NO_ACCESS = 2;
    const USER_EMAIL_EXISTS = 3;

    const CATEGORY_IS_PARENT = 4;
    const CATEGORY_MAX_DEPTH = 5;

    const PAGE_SLUG_EXISTS = 6;

    const STRIPE_CHARGE_FAILED = 7;
}