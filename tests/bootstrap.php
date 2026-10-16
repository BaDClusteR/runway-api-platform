<?php

declare(strict_types=1);

use Runway\Service\Provider\PathsProvider;

// The logs of the tests go to a temporary folder.
define('PROJECT_ROOT', sys_get_temp_dir() . '/runway-api-platform-tests');

require_once dirname(__DIR__) . '/vendor/autoload.php';

// The fixture endpoints (see tests/Fixture) for the integration tests.
PathsProvider::getInstance()->addConfigDirectory(__DIR__ . '/config');
