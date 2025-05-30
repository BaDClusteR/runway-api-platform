<?php

use Runway\Service\Provider\PathsProvider;

const API_PLATFORM_ROOT = __DIR__;
const API_PLATFORM_CONFIG_ROOT = API_PLATFORM_ROOT . "/config";

$pathsProvider = PathsProvider::getInstance();
$pathsProvider->addConfigDirectory(API_PLATFORM_CONFIG_ROOT);
$pathsProvider->addEnvFilePath(API_PLATFORM_ROOT . "/.env");