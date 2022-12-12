<?php

$vendorRoot = is_dir(__DIR__ . '/vendor') ? __DIR__ : __DIR__ . '/../..';

// install deployer-extended-typo3
require_once($vendorRoot . '/vendor/sourcebroker/deployer-loader/autoload.php');
new \SourceBroker\DeployerExtendedTypo3\Loader();

// install default settings
require_once(__DIR__ . '/defaults.php');

// install recipes
require_once(__DIR__ . '/recipe/db_init.php');
require_once(__DIR__ . '/recipe/db_truncate.php');