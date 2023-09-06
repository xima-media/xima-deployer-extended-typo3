<?php

namespace Deployer;

use SourceBroker\DeployerExtendedDatabase\Utility\ConsoleUtility;
use SourceBroker\DeployerExtendedTypo3\Utility\ConsoleUtility as ConsoleUtilityAlias;
use Symfony\Component\Console\Helper\Table;
use Xima\XimaDeployerExtendedTypo3\Utility\EnvUtility;

set('requirement_rows', []);

desc('Ensure deployment requirements are fulfilled');
task('check:requirements', [
    'check:locales',
    'check:software',
    'check:user',
    'check:dir',
    'check:env',
    'check:env_instance',
    'check:env_vars',
    'check:mysql',
    'check:php_extensions',
    'check:dns',
    'check:urls',
    'check:vhost_base',
    'check:vhost_release',
    'check:php_settings',
    'check:summary'
]);

task('check:summary', function () {
    (new Table(output()))
       ->setHeaderTitle(currentHost()->getAlias())
       ->setHeaders(['Task', 'Status', 'Info'])
       ->setRows(get('requirement_rows'))
       ->render();
})->hidden();

desc('Ensure system locales are present');
task('check:locales', function () {
    $required = ['de_DE.utf8', 'en_US.utf8'];
    $available = run('locale -a');
    $results = [];

    foreach ($required as $locale) {
        if (str_contains($available, $locale)) {
            $results[] = ['check:locales', 'Ok', 'Locale installed: ' . $locale];
            continue;
        }
        $results[] = ['check:locales', 'Error', 'Locale missing: ' . $locale];
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure required software is present');
task('check:software', function () {
    $tools = ['patch', 'git', 'gm', 'exiftool', 'ghostscript', 'pdftotext', 'pdfinfo', 'catdoc', 'catppt', 'xls2csv'];
    $results = [];

    foreach ($tools as $tool) {
        if (run('which ' . $tool)) {
            $results[] = ['check:software', 'Ok', 'Software installed: ' . $tool];
            continue;
        }
        $results[] = ['check:software', 'Error', 'Software missing: ' . $tool];
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure SSH user matches remote_user and has primary group www-data');
task('check:user', function () {
    $remoteUser = get('remote_user');
    $userName = run('id -un');
    $primaryUserGroup = run('id -gn');
    $results = [];

    if ($userName !== $remoteUser) {
        $results[] = ['check:user', 'Error', 'SSH user (' . $userName . ') does not match deployer remote_user (' . $remoteUser . ')'];
    }
    $results[] = ['check:user', 'Ok', 'SSH user matches deployer remote_user ' . $remoteUser];

    if ($primaryUserGroup !== 'www-data') {
        $results[] = ['check:user', 'Error', 'Primary user group (' . $primaryUserGroup .') must be www-data'];
    }
    $results[] = ['check:user', 'Ok', 'Primary user group is ' . $primaryUserGroup];

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure application directory exists with correct permissions');
task('check:dir', function () {
    $results = [];
    if (!test('[ -d {{deploy_path}} ]')) {
        $results[] = [ 'check:dir', 'Error', 'deploy_path {{deploy_path}} does not exist'];
    } else {
        $remoteUser = get('remote_user');
        $owner = run('stat -c "%U" ' . get('deploy_path'));
        $group = run('stat -c "%G" ' . get('deploy_path'));
        $mode  = run('stat -c "%a" ' . get('deploy_path'));

        if ($owner !== $remoteUser) {
            $results[] = ['check:dir', 'Error', $remoteUser . ' is not owner of ' . get('deploy_path')];
        }
        $results[] = ['check:dir', 'Ok', $remoteUser . ' is owner of ' . get('deploy_path')];
        
        if ($group !== 'www-data') {
            $results[] = ['check:dir', 'Error', 'www-data is not group of ' . get('deploy_path')];
        }
        $results[] = ['check:dir', 'Ok', 'www-data is group of ' . get('deploy_path')];

        if ($mode !== '2770') {
            $results[] = ['check:dir', 'Error', 'Unix permission is' . $mode . ' (must be 2770) for ' . get('deploy_path')];
        }
        $results[] = ['check:dir', 'Ok', 'Unix permission is ' . $mode . ' for ' . get('deploy_path')];
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure .env exists with correct permissions');
task('check:env', function() {
    $results = [];
    if (!test('[ -f {{deploy_path}}/shared/.env ]')) {
        $results[] = ['check:env','Error', 'Environment file {{deploy_path}}/shared/.env does not exist'];
    } else {
        $env = get('deploy_path') . '/shared/.env';
        $remoteUser = get('remote_user');
        $owner = run('stat -c "%U" ' . $env);
        $group = run('stat -c "%G" ' . $env);
        $mode  = run('stat -c "%a" ' . $env);

        if ($owner !== $remoteUser) {
            $results[] = ['check:env', 'Error', $remoteUser . ' is not owner of ' . $env];
        }
        $results[] = ['check:env', 'Ok', $remoteUser . ' is owner of ' . $env];
        
        if ($group !== 'www-data') {
            $results[] = ['check:env', 'Error', 'www-data is not group of ' . $env];
        }
        $results[] = ['check:env', 'Ok', 'www-data is group of ' . $env];

        if ($mode !== '640') {
            $results[] = ['check:env', 'Error', 'Unix permission is' . $mode . ' (must be 640) for ' . $env];
        }
        $results[] = ['check:env', 'Ok', 'Unix permission is ' . $mode . ' for ' . $env];
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure INSTANCE in .env matches deployer hostname');
task('check:env_instance', function() {
    $results = [];
    $currentHostname = currentHost()->get('alias');
    $instance = EnvUtility::getRemoteEnvVars()['INSTANCE'] ?? '';

    if ($currentHostname === $instance) {
        $results[] = ['check:env_instance','Ok', 'Deployer hostname (' . $currentHostname . ') matches INSTANCE (' . $instance . ')'];
    } else {
        $results[] = ['check:env_instance','Error', 'Deployer hostname (' . $currentHostname . ') does not match INSTANCE (' . $instance . ')'];
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure mandatory variables in .env are present');
task('check:env_vars', function() {
    $results = [];
    $vars = EnvUtility::getRemoteEnvVars();
    $variables = [
        'TYPO3_BASE_URL',
        'TYPO3_RELEASE_URL',
        'TYPO3_CONF_VARS__DB__Connections__Default__dbname',
        'TYPO3_CONF_VARS__DB__Connections__Default__host',
        'TYPO3_CONF_VARS__DB__Connections__Default__password',
        'TYPO3_CONF_VARS__DB__Connections__Default__port',
        'TYPO3_CONF_VARS__DB__Connections__Default__user'
    ];
    
    foreach ($variables as $variable) {
        if (!array_key_exists($variable, $vars)) {
            $results[] = ['check:env_vars','Error', 'Missing variable: ' . $variable];
            continue;
        }
        if (test('[ -z ' . $vars[$variable] .' ]')) {
            $results[] = ['check:env_vars','Error', 'Empty variable: ' . $variable];
        }
        $results[] = ['check:env_vars','Ok', 'Variable is set: ' . $variable];
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure DNS records for TYPO3_BASE_URL, TYPO3_BASE_URL and TYPO3_ALIAS_URL_* exist');
task('check:dns', function() {
    $results = [];
    $vars = EnvUtility::getRemoteEnvVars();
    $urls = [
        $vars['TYPO3_BASE_URL'] ?? '',
        $vars['TYPO3_RELEASE_URL'] ?? '',
    ];
    foreach ($vars as $var => $value) {
        if (preg_match('/TYPO3\_ALIAS\_URL\_[0-9]+/', $var)) {
            $urls[] = $value;
        }
    }

    array_filter($urls);
    foreach ($urls as $url) {
        $host = @parse_url($url, PHP_URL_HOST);
        if (!$host) {
            $results[] = [ 'check:dns', 'Error', 'URL malformed or missing ' . $url ];
            continue;
        }
        if (!checkdnsrr($host, 'A')) {
            $results[] = [ 'check:dns', 'Error', 'A record not found: ' . $host ];
            continue;
        }
        $results[] = [ 'check:dns', 'Ok', 'A record found: ' . $host ];
    }
    
    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure TYPO3_BASE_URL, TYPO3_BASE_URL and TYPO3_ALIAS_URL_* are reachable with HTTP code 200 or 404');
task('check:urls', function() {
    $results = [];
    $vars = EnvUtility::getRemoteEnvVars();
    $urls = [
        $vars['TYPO3_BASE_URL'] ?? '',
        $vars['TYPO3_RELEASE_URL'] ?? '',
    ];
    foreach ($vars as $var => $value) {
        if (preg_match('/TYPO3\_ALIAS\_URL\_[0-9]+/', $var)) {
            $urls[] = $value;
        }
    }

    array_filter($urls);
    foreach ($urls as $url) {
        if (!parse_url($url, PHP_URL_HOST)) {
            $results[] = ['check:urls','Error', 'URL malformed or missing' . $url];
            continue;
        }
        $headers = @get_headers($url, true);
        if (!$headers) {
            $results[] = ['check:urls','Error', 'HTTP request failed:' . $url];
            continue;
        }
        $statusCode = $headers[0];
        if ($statusCode !== 'HTTP/1.1 200 OK' && $statusCode !== 'HTTP/1.1 404 Not Found') {
            $results[] = ['check:urls','Error', 'Invalid HTTP response ' . $statusCode . ': ' . $url];
            continue;
        }
        $results[] = ['check:urls','Ok', 'Valid HTTP response ' . $statusCode . ': ' . $url];

    }
    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure database can be accessed');
task('check:mysql', function() {
    $results = [];
    $vars = EnvUtility::getRemoteEnvVars();
    $dbname = $vars['TYPO3_CONF_VARS__DB__Connections__Default__dbname'] ?? '';
    $host = $vars['TYPO3_CONF_VARS__DB__Connections__Default__host'] ?? '';
    $port = $vars['TYPO3_CONF_VARS__DB__Connections__Default__port'] ?? '';
    $user = $vars['TYPO3_CONF_VARS__DB__Connections__Default__user'] ?? '';
    $password = $vars['TYPO3_CONF_VARS__DB__Connections__Default__password'] ?? '';

    if (in_array('', [$dbname, $host, $port, $user, $password ])) {
        $results[] = ['check:mysql', 'Error', 'Undefined database variable'];
        set('requirement_rows', [
            ...get('requirement_rows'),
            ...$results
        ]);
        return;
    }

    if ($vars['TYPO3_CONF_VARS__DB__Connections__Default__driverOptions__flags'] ?? '' === '2048') {
        // if ssl is needed
        $query = run('mysqlshow --host=' .$host . ' --port=' . $port . ' --user=' . $user . ' --password=' . $password . ' --ssl ' . $dbname . ' > /dev/null 2>&1; echo $?');
    } else {
        $query = run('mysqlshow --host=' .$host . ' --port=' . $port . ' --user=' . $user . ' --password=' . $password . ' ' . $dbname . ' > /dev/null 2>&1; echo $?');
    }

    if ($query === '0') {
        $results[] = ['check:mysql', 'Ok', 'Database is accessible: ' . $dbname];
    } elseif ($query === '1') {
        $results[] = ['check:mysql', 'Error', 'Could not connect to database: ' . $dbname];
    } else {
        $results[] = ['check:mysql', 'Error', 'Unknown exit code' . $query];
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure always mandatory php extensions are present');
task('check:php_extensions', function() {
    $results = [];
    $required = [
        'pdo',
        'json',
        'pcre',
        'session',
        'xml',
        'filter',
        'SPL',
        'standard',
        'tokenizer',
        'mbstring',
        'intl',
        'fileinfo',
        'gd',
        'zip',
        'zlib',
        'openssl',
        'pdo_mysql',
        'apcu',
        'mailparse'
    ];
    $available = run('php -m');

    foreach ($required as $extension) {
        if (!stripos($available, $extension)) {
            $results[] = ['check:php_extensions','Error', 'Missing extension: ' . $extension];
            continue;
        }
        $results[] = ['check:php_extensions','Ok', 'Extension is present: ' . $extension];

    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure Apache runs PHP with required settings');
task('check:php_settings', function() {
    $results = [];
    $baseUrl = EnvUtility::getRemoteEnvVars()['TYPO3_BASE_URL'] ?? '';
    if (!parse_url($baseUrl, PHP_URL_HOST)) {
        $results[] = ['check:php_settings','Error', 'TYPO3_BASE_URL is undefined or missing'];
        set('requirement_rows', [
            ...get('requirement_rows'),
            ...$results
        ]);
        return;
    }
    $headers = @get_headers($baseUrl, true);
    $statusCode = $headers[0];
    if ($statusCode !== 'HTTP/1.1 200 OK' && $statusCode !== 'HTTP/1.1 404 Not Found') {
        $results[] = ['check:php_settings','Error', 'Invalid HTTP response ' . $statusCode . ': ' . $url];
        set('requirement_rows', [
            ...get('requirement_rows'),
            ...$results
        ]);
        return;
    }

    $docRoot = get('deploy_path') . '/current/public/';
    $docRootExists = test('[ -d ' . $docRoot . ' ]');
    // create docRoot if not present
    if (!$docRootExists) {
        run('mkdir -p ' . $docRoot);
    }

    $phpFile = 'phpinfo_' . uniqid() . '.php';
    $configToJson = <<<'EOD'
<?php
\$data = [
    'php_sapi_name' => php_sapi_name(),                        // must be "fpm-fcgi"
    'default_timezone' => date_default_timezone_get(),         // must be Europe/Berlin
    'memory_limit' => ini_get('memory_limit'),                 // must be 512M
    'max_execution_time' => ini_get('max_execution_time'),     // must be 240+
    'max_input_vars' => ini_get('max_input_vars'),             // must be 1500+
    'post_max_size' => ini_get('post_max_size'),               // must be 21M+
    'upload_max_filesize' => ini_get('upload_max_filesize'),   // must be 20M+
    'opcache.enable' => ini_get('opcache.enable'),             // must be 1
];
header(\"Content-Type: application/json\");
echo json_encode(\$data);
exit();
EOD;
    // render php file on webserver and get json
    run('echo "' . $configToJson . '" > ' . $docRoot . $phpFile);
    $json = run('wget ' . $baseUrl . '/' . $phpFile . ' -q -O - || echo "1"');
    // clean up php file
    run('rm ' .$docRoot . $phpFile);
    // remove docRoot if it was created by this task
    if (!$docRootExists) {
        run('rmdir ' . get('deploy_path') . '/current/public');
        run('rmdir ' . get('deploy_path') . '/current');
    }

    // validate json
    if ($json === '1') {
        $results[] = ['check:php_settings','Error', 'Could not read php settings, vHost may be misconfigured'];
        set('requirement_rows', [
            ...get('requirement_rows'),
            ...$results
        ]);
        return;
    }
    $config = @json_decode($json, true);
    if (is_null($config)) {
        $results[] = ['check:php_settings','Error', 'Unable to parse json'];
        set('requirement_rows', [
            ...get('requirement_rows'),
            ...$results
        ]);
        return;
    }

    function return_bytes ($size_str) {
        switch (substr ($size_str, -1)) {
            case 'M': case 'm': return (int)$size_str * 1048576;
            case 'K': case 'k': return (int)$size_str * 1024;
            case 'G': case 'g': return (int)$size_str * 1073741824;
            default: return $size_str;
        }
    }
    
    if ($config['php_sapi_name'] !== 'fpm-fcgi') {
        $results[] = ['check:php_settings', 'Error' ,'PHP-FPM not enabled (' . $config['php_sapi_name'] . ')'];
    } else {
        $results[] = ['check:php_settings', 'Ok' ,'PHP-FPM is enabled (' . $config['php_sapi_name'] . ')'];
    }
    if ($config['default_timezone'] !== 'Europe/Berlin') {
        $results[] = ['check:php_settings', 'Error' ,'Default timezone is ' . $config['default_timezone']];
    } else {
        $results[] = ['check:php_settings', 'Ok' ,'Default timezone is ' . $config['default_timezone']];
    }
    if (intval(return_bytes($config['memory_limit'])) < 536870912) {
        $results[] = ['check:php_settings', 'Error' ,'memory_limit ' . $config['memory_limit'] . ' is less than 512M'];
    } else {
        $results[] = ['check:php_settings', 'Ok' ,'memory_limit = ' . $config['memory_limit']];
    }
    if (intval($config['max_execution_time']) < 240 ) {
        $results[] = ['check:php_settings', 'Error' ,'max_execution_time ' . $config['max_execution_time'] . ' is lower than 240s'];
    } else {
        $results[] = ['check:php_settings', 'Ok' ,'max_execution_time = ' . $config['max_execution_time']];
    }
    if (intval($config['max_input_vars']) < 1500 ) {
        $results[] = ['check:php_settings', 'Error' ,'max_input_vars ' . $config['max_input_vars'] . ' is lower than 1500'];
    } else {
        $results[] = ['check:php_settings', 'Ok' ,'max_input_vars = ' . $config['max_input_vars']];
    }
    if (intval(return_bytes($config['post_max_size'])) < 22020096) {
        $results[] = ['check:php_settings', 'Error' ,'post_max_size ' . $config['post_max_size'] . ' is less than 21M'];
    } else {
        $results[] = ['check:php_settings', 'Ok' ,'post_max_size = ' . $config['post_max_size']];
    }
    if (intval(return_bytes($config['upload_max_filesize'])) < 20971520) {
        $results[] = ['check:php_settings', 'Error' ,'upload_max_filesize ' . $config['upload_max_filesize'] . ' is less than 20M'];
    } else {
        $results[] = ['check:php_settings', 'Ok' ,'upload_max_filesize = ' . $config['upload_max_filesize']];
    }
    if ($config['opcache.enable'] !== '1' ) {
        $results[] = ['check:php_settings', 'Error' ,'Opcache is disabled'];
    } else {
        $results[] = ['check:php_settings', 'Ok' ,'Opcache is enabled'];
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure apache virtualhost for TYPO3_BASE_URL and TYPO3_ALIAS_URL_* matches requirements');
task('check:vhost_base', function() {
    $results = [];
    $docRoot = get('deploy_path') . '/current/public';
    $vars = EnvUtility::getRemoteEnvVars();
    $baseUrl = $vars['TYPO3_BASE_URL'] ?? '';
    $baseHost = @parse_url($baseUrl, PHP_URL_HOST);
    if (!$baseHost) {
        $results[] = ['check:vhost_base', 'Error', 'Could not parse TYPO3_BASE_URL'];
        set('requirement_rows', [
            ...get('requirement_rows'),
            ...$results
        ]);
        return;
    }
    $aliasHosts = [];
    foreach ($vars as $var => $value) {
        if (preg_match('/TYPO3\_ALIAS\_URL\_[0-9]+/', $var)) {
            $host = @parse_url($value, PHP_URL_HOST);
            if (!$host) {
                $results[] = ['check:vhost_base', 'Error', 'Could not parse alias ' . $value];
                continue;
            }
            $aliasHosts[] = $host;
        }
    }

    // ensure vHost exists
    $vhost = run('grep -Rls "\s' . $baseHost . '" /etc/apache2/sites-enabled/ || echo "1"');
    if ($vhost === '1') {
        $results[] = ['check:vhost_base', 'Error', 'vHost not found or accessible for ' . $baseHost];
        set('requirement_rows', [
            ...get('requirement_rows'),
            ...$results
        ]);
        return;
    }

    // ensure aliases are configured
    foreach ($aliasHosts as $host) {
        if (run('grep ServerAlias "' . $vhost . '" | grep -q "' . $host . '"; echo $?') === '1') {
            $results[] = ['check:vhost_base', 'Error', 'Alias missing: ' . $host];
        }
        $results[] = ['check:vhost_base', 'Ok', 'Alias configured: ' . $host];

    }

    // ensure DocumentRoot is configured
    if (run('grep DocumentRoot "' . $vhost . '" | grep -q "' . $docRoot . '"; echo $?') === '1') {
        $results[] = ['check:vhost_base', 'Error', 'DocumentRoot undefined'];
    }
    $results[] = ['check:vhost_base', 'Ok', 'DocumentRoot configured'];

    // ensure Directory is configured
    if (run('grep "<Directory" "' . $vhost . '" | grep -q "' . $docRoot . '"; echo $?') === '1') {
        $results[] = ['check:vhost_base', 'Error', '<Directory> undefined'];
    }
    $results[] = ['check:vhost_base', 'Ok', '<Directory> configured'];

    // ensure .htaccess  is enabled
    if (run('grep -q "AllowOverride All" "' . $vhost . '";echo $?') === '1') {
        $results[] = ['check:vhost_base', 'Error', 'AllowOverride All missing'];
    }
    $results[] = ['check:vhost_base', 'Ok', 'AllowOverride All configured'];
    
    // ensure FollowSymLinks is enabled
    if (run('grep -Eq "+FollowSymLinks|FollowSymLinks" "' . $vhost . '";echo $?') === '1') {
        $results[] = ['check:vhost_base', 'Error', 'FollowSymLinks missing'];
    }
    $results[] = ['check:vhost_base', 'Ok', 'FollowSymLinks configured'];
    
    // ensure Multiviews is enabled
    if (run('grep -Eq "+Multiviews|Multiviews" "' . $vhost . '";echo $?') === '1') {
        $results[] = ['check:vhost_base', 'Error', 'Multiviews missing'];
    }
    $results[] = ['check:vhost_base', 'Ok', 'Multiviews configured'];

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();

desc('Ensure apache virtualhost for TYPO3_RELEASE_URL matches requirements');
task('check:vhost_release', function() {
    $results = [];
    $docRoot = get('deploy_path') . '/release/public';
    $vars = EnvUtility::getRemoteEnvVars();
    $releaseUrl = $vars['TYPO3_RELEASE_URL'] ?? '';
    $releaseHost = @parse_url($releaseUrl, PHP_URL_HOST);
    if (!$releaseHost) {
        $results[] = ['check:vhost_release', 'Error', 'Could not parse TYPO3_RELEASE_URL'];
        set('requirement_rows', [
            ...get('requirement_rows'),
            ...$results
        ]);
        return;
    }

    // ensure vHost exists
    $vhost = run('grep -Rls "\s' . $releaseHost . '" /etc/apache2/sites-enabled/ || echo "1"');
    if ($vhost === '1') {
        $results[] = ['check:vhost_release', 'Error', 'vHost not found or accessible for ' . $releaseHost];
        set('requirement_rows', [
            ...get('requirement_rows'),
            ...$results
        ]);
        return;
    }

    // ensure DocumentRoot is configured
    if (run('grep DocumentRoot "' . $vhost . '" | grep -q "' . $docRoot . '"; echo $?') === '1') {
        $results[] = ['check:vhost_release', 'Error', 'DocumentRoot undefined'];
    }
    $results[] = ['check:vhost_release', 'Ok', 'DocumentRoot configured'];

    // ensure Directory is configured
    if (run('grep "<Directory" "' . $vhost . '" | grep -q "' . $docRoot . '"; echo $?') === '1') {
        $results[] = ['check:vhost_release', 'Error', '<Directory> undefined'];
    }
    $results[] = ['check:vhost_release', 'Ok', '<Directory> configured'];

    // ensure .htaccess  is enabled
    if (run('grep -q "AllowOverride All" "' . $vhost . '";echo $?') === '1') {
        $results[] = ['check:vhost_release', 'Error', 'AllowOverride All missing'];
    }
    $results[] = ['check:vhost_release', 'Ok', 'AllowOverride All configured'];
    
    // ensure FollowSymLinks is enabled
    if (run('grep -Eq "+FollowSymLinks|FollowSymLinks" "' . $vhost . '";echo $?') === '1') {
        $results[] = ['check:vhost_release', 'Error', 'FollowSymLinks missing'];
    }
    $results[] = ['check:vhost_release', 'Ok', 'FollowSymLinks configured'];
    
    // ensure Multiviews is enabled
    if (run('grep -Eq "+Multiviews|Multiviews" "' . $vhost . '";echo $?') === '1') {
        $results[] = ['check:vhost_release', 'Error', 'Multiviews missing'];
    }
    $results[] = ['check:vhost_release', 'Ok', 'Multiviews configured'];

    // ensure release variable is set
    if (run('grep -q "SetEnv IS_RELEASE_REQUEST 1" "' . $vhost . '";echo $?') === '1') {
        $results[] = ['check:vhost_release', 'Error', '"SetEnv IS_RELEASE_REQUEST 1" missing'];

    }
    $results[] = ['check:vhost_release', 'Ok', '"SetEnv IS_RELEASE_REQUEST 1" is present'];

    set('requirement_rows', [
        ...get('requirement_rows'),
        ...$results
    ]);
})->hidden();
