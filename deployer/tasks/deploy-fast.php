<?php

namespace Deployer;

task('deploy-fast', [

    // Standard Deployer task.
    'deploy:info',

    // sourcebroker/deployer-extended special task. Read more at https://github.com/sourcebroker/deployer-extended#deploy-check-lock
    'deploy:check_lock',

    // sourcebroker/deployer-extended special task. Read more at https://github.com/sourcebroker/deployer-extended#deploy-check-composer-install
    'deploy:check_composer_install',

    // sourcebroker/deployer-extended special task. Read more at https://github.com/sourcebroker/deployer-extended#check_composer_validate
    'deploy:check_composer_validate',

    // Standard Deployer task.
    'deploy:setup',

    // Standard Deployer task.
    'deploy:lock',

    // Standard Deployer task.
    'deploy:release',

    // xima/xima-deployer-extended-typo3 custom task.
    'deploy:upload_code',

    // Standard Deployer task.
    'deploy:shared',

    // xima/xima-deployer-extended-typo3 custom task.
    'deploy:writableLocalConfiguration',

    // Standard Deployer task.
    'deploy:writable',

    // Standard Deployer task.
    'deploy:vendors',

    // Standard Deployer task.
    'deploy:clear_paths',

    // deployer-typo3-deploy-ci task.
    'typo3:cache:warmup:system',

    // xima/xima-deployer-extended-typo3 custom task.
    'db:init',

    // deployer-typo3-deploy-ci task.
    'typo3:extension:setup',

    // sourcebroker/deployer-extended-database task.
    'db:truncate',

    // Standard Deployer task.
    'deploy:symlink',

    // sourcebroker/deployer-extended special task. Read more on https://github.com/sourcebroker/deployer-extended#cache-clear-php-cli
    'cache:clear_php_cli',

    // deployer-typo3-deploy task.
    'typo3:cache:flush:pages',

    // sourcebroker/deployer-extended special task. Read more on https://github.com/sourcebroker/deployer-extended#cache-clear-php-http
    'cache:clear_php_http',

    // Standard Deployer task.
    'deploy:unlock',

    // Standard Deployer task.
    'deploy:cleanup',

    // Standard Deployer task.
    'deploy:success',

])->desc('Deploy your TYPO3');