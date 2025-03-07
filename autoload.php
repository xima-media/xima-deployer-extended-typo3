<?php

namespace Deployer;

// install deployer-extended-typo3
$vendorRoot = is_dir(__DIR__ . '/../../../vendor') ? __DIR__ . '/../../..' : __DIR__ . '/../..';
require_once($vendorRoot . '/vendor/sourcebroker/deployer-loader/autoload.php');
new \SourceBroker\DeployerExtendedTypo3\Loader();

// install default settings
require_once(__DIR__ . '/set.php');

// install recipes
require_once(__DIR__ . '/recipe/db_init.php');
require_once(__DIR__ . '/recipe/deploy_check_branch_local.php');
require_once(__DIR__ . '/recipe/deploy_check_branch.php');
require_once(__DIR__ . '/recipe/deploy_upload_code.php');
require_once(__DIR__ . '/recipe/deploy_writable_local_configuration.php');
require_once(__DIR__ . '/recipe/launch.php');
require_once(__DIR__ . '/recipe/logs_php.php');
require_once(__DIR__ . '/recipe/check_requirements.php');
require_once(__DIR__ . '/recipe/sequelace.php');
require_once(__DIR__ . '/recipe/reset_from_gitlab_artifact.php');

// prevent pipeline fail on first deploy (no tables)
// + enable database copy in feature branch deployment
before('db:truncate', 'db:init');

// make LocalConfiguration.php from git writable
// don't use deploy:update_code since it could be disabled in non-git deployment
after('deploy:shared', 'deploy:writableLocalConfiguration');
