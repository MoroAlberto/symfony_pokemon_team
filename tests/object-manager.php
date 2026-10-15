<?php

// Gives PHPStan the Doctrine entity manager, so that it knows the entities and checks the DQL queries
use App\Kernel;

// Load the variables of the test environment (.env.test), whatever APP_ENV the shell or .env.local sets
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
require __DIR__.'/bootstrap.php';

$kernel = new Kernel('test', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
