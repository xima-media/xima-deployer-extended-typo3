<?php

namespace Deployer;

function runMigration($before = false) {
    if (get('is_argument_host_the_same_as_local_host')) {

        $activeDir = get('deploy_path') . (testLocally('[ -e {{deploy_path}}/release ]') ? '/release' : '/current');
        $activeDir = testLocally('[ -e ' . $activeDir . ' ]') ? $activeDir : get('deploy_path');

        foreach (get($before ? 'migration_before_tasks' : 'migration_after_tasks', []) as $task) {
            // Import SQL migration files.
            if (str_ends_with($task, '.sql')) {
                runLocally('cd ' . $activeDir . ' && cat ' . $task . ' | {{local/bin/php}} {{local/bin/typo3}} database:import -vvv');
                continue;
            }
            // Run the specified TYPO3 upgrade wizard.
            runLocally('cd ' . $activeDir . ' && {{local/bin/php}} {{local/bin/typo3}} upgrade:run ' . $task . ' -vvv --no-interaction');
        }
    } else {
        run('cd {{release_or_current_path}} && {{bin/php}} {{bin/deployer}} deploy:migration:' . ($before ? 'before' : 'after') . ' {{argument_host}}');
    }
}

task('deploy:migration:before', function () {
    runMigration(true);
})->desc('Run configured SQL migrations and TYPO3 upgrade wizards before extension setup');
before('typo3:extension:setup', 'deploy:migration:before');

task('deploy:migration:after', function () {
    runMigration();
})->desc('Run configured SQL migrations and TYPO3 upgrade wizards after extension setup');
after('typo3:extension:setup', 'deploy:migration:after');
