<?php

namespace Deployer;

// webserver user
set('http_user', 'www-data');

// default php binary
set('bin/php', 'php');

// remote permissions
set('writable_mode', 'chmod');
set('writable_chmod_recursive', false);
set('writable_chmod_mode', '2770');

// local host is always needed
localhost('local')
    ->set('bin/php', 'php')
    ->set('deploy_path', getcwd());

// read typo3 database connection from bin/typo3 > additional.php > .env
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
    ];
});

// add additional shared files
set('shared_files', [
    ...get('shared_files'),
    '.env.local',
]);

// use curl instead of wget
set('fetch_method', 'curl');

// keep permissions from source system
set('media_custom', [
    'flags' => 'rzp',
    ],
);

// Common random that can be used between tasks. Must be in form that can be used directly in filename!
set('random', md5(time() . mt_rand()));

// disable composer version check
set('composer_channel_autoupdate', false);

// disable plattform requirement check (fails in pipeline)
set('check_composer_install_options', '--verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader --ignore-platform-reqs');

// files to upload in non-git deployment
set('upload_paths', [
    'composer.json',
    'composer.lock',
    'deploy.php',
    'config',
    'packages',
    'patches',
    'public/.htaccess',
    'public/.well-known',
    'var/labels',
]);

// Configure request buffering to avoid errors during deployments
set('buffer_config', function () {
    return [
        'index.php' => [
            'entrypoint_filename' => get('web_path') . 'index.php',
        ],
        'typo3/index.php' => [
            'entrypoint_filename' => get('web_path') . 'typo3/index.php',
        ],
        'typo3/install.php' => [
            'entrypoint_filename' => get('web_path') . 'typo3/install.php',
        ]
    ];
});