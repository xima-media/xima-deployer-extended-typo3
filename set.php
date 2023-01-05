<?php

namespace Deployer;

// webserver user
set('http_user', 'www-data');

// remote permissions
set('writable_mode', 'chmod');
set('writable_chmod_recursive', false);
set('writable_chmod_mode', '2770');

// local host is always needed
host('local')->set('deploy_path', '/var/www/html');

// read typo3 database connection from bin/typo3cms > AdditionalConfiguration.php > .env
set('driver_typo3cms', true);

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

// set log files dir
set('log_files', 'var/log/*.log');

// disable database override feature
set('db_databases_merged', get('db_databases'));
