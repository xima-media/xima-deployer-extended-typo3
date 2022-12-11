<?php

namespace Deployer;

// apache user
set('http_user', 'www-data');

// remote permissions
set('writable_mode', 'chmod');
set('writable_chmod_recursive', false);
set('writable_chmod_mode', '2770');

// local host is always needed
host('local')->hostname('local')->set('deploy_path', '/var/www/html');

// read typo3 database connection from bin/typo3cms > AdditionalConfiguration.php > .env
set('driver_typo3cms', true);

// register new variable for feature-branch deployment
set('base_branch', '');

// set writable dirs
set('writable_dirs', function () {
    return [
        get('web_path') . 'typo3conf',
        get('web_path') . 'typo3temp',
        get('web_path') . 'typo3temp/assets',
        get('web_path') . 'typo3temp/assets/images',
        get('web_path') . 'typo3temp/assets/_processed_',
        get('web_path') . 'uploads',
        get('web_path') . 'fileadmin',
        get('web_path') . '../var',
        get('web_path') . '../var/log',
        get('web_path') . '../var/transient',
        get('web_path') . 'fileadmin/_processed_',
    ];
});

// set shared dirs
set('shared_dirs', function () {
    return [
        'public/fileadmin',
        'public/uploads',
        'public/typo3temp/assets',
        'var/log',
        'var/transient',
        'var/goaccess',
    ];
});

// disable branch check because it fails in pipeline
task('deploy:check_branch_local', function () {
});

// prevent pipeline fail on first deploy (no tables)
before('db:truncate', 'typo3cms:database:updateschema');