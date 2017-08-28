#!/usr/bin/php
<?php

use App\Util\OneTimeLoader;
use Symfony\Component\Console\Application;

require __DIR__.'/vendor/autoload.php';

$application = new Application();
$application->setName('Development helper');

/**
 * Command autoloader
 */
$sc = scandir(__DIR__.'/src/App/Command'); unset($sc[0]); unset($sc[1]);
foreach($sc as $class) {
    $className = "\\App\\Command\\".explode(".", $class)[0];
    $application->add(new $className());
}

$application->run();