<?php declare(strict_types=1);

if (@!include __DIR__ . '/../vendor/autoload.php') { // @ dependencies may not be installed
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}

chdir(dirname(__DIR__)); // Larastan starts the application of testbench from the working directory

Tester\Environment::setup();
Tester\Environment::setupFunctions();
