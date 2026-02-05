<?php

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

use StubsGenerator\GenerateStubsCommand;
use Symfony\Component\Console\Application;

$command = new GenerateStubsCommand();
$application = new Application();
$application->add($command);
$application->setDefaultCommand($command->getName(), true);

// Return the application for PHPStan (don't run it)
return $application;
