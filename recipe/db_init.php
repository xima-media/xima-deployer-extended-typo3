<?php

namespace Deployer;

use SourceBroker\DeployerExtendedDatabase\Utility\ConsoleUtility;

/**
 * Check for missing database: Run database updateschema + import database of base branch
 */
task('db:init', function () {
    $baseBranch = (new ConsoleUtility())->getOption('base_branch') ?: '';

    // abort if feature branch has already been configured
    // test('[ -f {{deploy_path}}/.dep/latest_release ]')
    if (!$baseBranch || !get('argument_host')) {
        return;
    }

    $targetHost = get('argument_host');
    $baseStage = str_replace(strtolower(get('branch')), $baseBranch, $targetHost);
    $activePath = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/release ]') ? 'release' : 'current');

    // update schema (db:import would fail with empty database)
    run('cd ' . $activePath . ' && {{bin/php}} {{bin/typo3cms}} database:updateschema');

    // copy database from base branch
    runLocally('{{local/bin/deployer}} db:copy ' . $baseStage . ' --options=target:' . $targetHost);
});
