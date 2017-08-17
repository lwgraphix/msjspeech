<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Bootstrap.php';

if (function_exists('date_default_timezone_set'))
{
    date_default_timezone_set('Europe/Moscow');
}

setlocale(LC_ALL, 'ru_RU.UTF-8');

Bootstrap::getApplication()->run();