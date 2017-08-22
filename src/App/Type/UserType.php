<?php

namespace App\Type;

class UserType
{
    const PENDING = 0;
    const FROZEN = 1;
    const SUSPENDED = 2;
    const MEMBER = 3;
    const OFFICER = 4;
    const ADMINISTRATOR = 5;

    const NAMES = ['Pending', 'Frozen', 'Suspended', 'Member', 'Officer', 'Administrator'];
}