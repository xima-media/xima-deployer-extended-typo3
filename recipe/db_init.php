<?php

namespace Deployer;

use SourceBroker\DeployerExtendedDatabase\Utility\ConsoleUtility;
use SourceBroker\DeployerExtendedDatabase\Utility\DatabaseUtility;

task('db:init', function () {

    $baseBranch = (new ConsoleUtility())->getOption('base_branch') ?: '';

    if (!empty(get('argument_stage'))) {
        $verbosity = (new ConsoleUtility())->getVerbosityAsParameter();
        $baseOption = $baseBranch ? '--options=base_branch:' . $baseBranch . ' ' : '';
        $activePath = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/release ]') ? 'release' : 'current');
        run('cd ' . $activePath . ' && {{bin/php}} {{bin/deployer}} db:init ' . $baseOption . $verbosity);
    }

    // empty database: schema update
    $tables = [];
    $databaseUtility = new DatabaseUtility();
    foreach (get('db_databases_merged') as $databaseCode => $databaseConfig) {
        $tables = [...$databaseUtility->getTables($databaseConfig)];
    }
    if (!empty($tables)) {
        $activePath = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/release ]') ? 'release' : 'current');
        run('cd ' . $activePath . ' && {{bin/php}} {{bin/deployer}} db:init ' . $baseOption . $verbosity);
        invoke('typo3cms:database:updateschema');
    }

    // no data: import from base branch


});