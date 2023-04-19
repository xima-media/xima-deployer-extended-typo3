<?php

namespace Deployer;

use SourceBroker\DeployerExtendedDatabase\Utility\ConsoleUtility;
use SourceBroker\DeployerExtendedTypo3\Utility\ConsoleUtility as ConsoleUtilityAlias;
use Symfony\Component\Console\Helper\Table;
use Xima\XimaDeployerExtendedTypo3\Utility\EnvUtility;

set('requirement_rows', []);

desc('ensure deployment requirements are fulfilled');
task('check:requirements', [
    'check:locales',
    'check:user',
    'check:permissions',
    'check:env_perms',
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
    $required = array('de_DE.utf8', 'en_US.utf8');
    $available = run('locale -a');
    $missing = array();

    foreach ($required as $locale) {
        if (str_contains($available, $locale) === false) {
            $missing[] = $locale;
        }
    }

    if (empty($missing)) {
        $status = 'Ok';
        $msg = 'Required locales are installed: ' . implode(', ', $required);
    } else {
        $status = 'Error';
        $msg = 'Required locales are missing: ' .implode(', ', $missing);
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:locales',$status, $msg],
    ]);
})->hidden();

desc('Ensure SSH user matches remote_user and has primary group www-data');
task('check:user', function () {
    $remoteUser = get('remote_user');
    $userName = run('id -un');
    $primaryUserGroup = run('id -gn');

    if ($userName === $remoteUser && $primaryUserGroup === 'www-data') {
        $status = 'Ok';
        $msg = 'SSH user matches remote_user ' . $remoteUser . ' and is member of ' . $primaryUserGroup;
    } else {
        $status = 'Error';
        $msg = 'SSH user must be' . $remoteUser . '(is ' . $userName . ') and primary group must be www-data (is ' . $primaryUserGroup . ')';
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:user',$status, $msg],
    ]);
})->hidden();

desc('Ensure application directory exists with correct permissions');
task('check:permissions', function () {
    if (test('[ -d {{deploy_path}} ]')) {
        $remoteUser = get('remote_user');
        $owner = run('stat -c "%U" ' . get('deploy_path'));
        $group = run('stat -c "%G" ' . get('deploy_path'));
        $mode  = run('stat -c "%a" ' . get('deploy_path'));

        if ($mode === '2770' && $owner === $remoteUser && $group === 'www-data') {
            $status = 'Ok';
            $msg = get('deploy_path') . ' is owned by user ' . $owner . ', group ' . $group . ', with permission ' . $mode;
        } else {
            $status = 'Error';
            $msg = get('deploy_path') . ' must be owned by user ' . $remoteUser . ' (is ' . $owner . '), group www-data (is ' . $group . '), with permission 2770 (is ' . $mode . ')';
        }
    } else {
        $status = 'Error';
        $msg = 'deploy_path {{deploy_path}} does not exist';
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:permissions',$status, $msg],
    ]);
})->hidden();

desc('Ensure .env exists with correct permissions');
task('check:env_perms', function() {
    if (test('[ -f {{deploy_path}}/shared/.env ]')) {
        $env = get('deploy_path') . '/shared/.env';
        $remoteUser = get('remote_user');
        $owner = run('stat -c "%U" ' . $env);
        $group = run('stat -c "%G" ' . $env);
        $mode  = run('stat -c "%a" ' . $env);

        if ($mode === '640' && $owner === $remoteUser && $group === 'www-data') {
            $status = 'Ok';
            $msg = $env . ' is owned by user ' . $owner . ', group ' . $group . ', with permission ' . $mode;
        } else {
            $status = 'Error';
            $msg = $env . ' must be owned by user ' . $remoteUser . ' (is ' . $owner . '), group www-data (is ' . $group . '), with permission 640 (is ' . $mode . ')';
        }
        
    } else {
        $status = 'Error';
        $msg = 'Environment file {{deploy_path}}/shared/.env does not exist';
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:env_perms',$status, $msg],
    ]);
})->hidden();

desc('Ensure INSTANCE in .env matches deployer hostname');
task('check:env_instance', function() {
    $currentHostname = currentHost()->get('alias');
    $instance = EnvUtility::getRemoteEnvVars()['INSTANCE'];

    if ($currentHostname === $instance) {
        $status = 'Ok';
        $msg = 'hostname (' . $currentHostname . ') matches INSTANCE from .env (' . $instance . ')';
    } else {
        $status = 'Error';
        $msg = 'hostname (' . $currentHostname . ') does not match INSTANCE from .env (' . $instance . ')';
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:env_instance',$status, $msg],
    ]);
})->hidden();

desc('Ensure mandatory .env parameters are configured');
task('check:env_vars', function() {
    $vars = EnvUtility::getRemoteEnvVars();
    $parameters = array(
        'TYPO3_BASE_URL',
        'TYPO3_RELEASE_URL',
        'TYPO3_CONF_VARS__DB__Connections__Default__dbname',
        'TYPO3_CONF_VARS__DB__Connections__Default__host',
        'TYPO3_CONF_VARS__DB__Connections__Default__password',
        'TYPO3_CONF_VARS__DB__Connections__Default__port',
        'TYPO3_CONF_VARS__DB__Connections__Default__user'
    );
    $missing = array();
    $empty = array();
    
    foreach ($parameters as $parameter) {
        if (!array_key_exists($parameter, $vars)) {
            $missing[] = $parameter;
        } else {
            if (test('[ -z ' . $vars[$parameter] .' ]')) {
                $empty[] = $parameter;
            }
        }
    }

    if (empty($empty) && empty($missing)) {
        $status = 'Ok';
        $msg = 'Mandatory parameters in .env are configured';
    } else {
        $status = 'Error';
        $msg = 'Mandatory parameters in .env are missing (' . implode(', ', $missing) . ') or empty (' . implode(', ', $empty) . ')';
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:env_vars',$status, $msg],
    ]);
})->hidden();

desc('Ensure DNS records for TYPO3_BASE_URL, TYPO3_BASE_URL and TYPO3_ALIAS_URL_* exist');
task('check:dns', function() {
    $vars = EnvUtility::getRemoteEnvVars();
    $baseDomain = parse_url($vars['TYPO3_BASE_URL'], PHP_URL_HOST);
    $releaseDomain = parse_url($vars['TYPO3_RELEASE_URL'], PHP_URL_HOST);
    $domains = array($baseDomain, $releaseDomain);
    foreach ($vars as $key => $value) {
        if (preg_match('/TYPO3\_ALIAS\_URL\_[0-9]+/', $key)) {
            $domains[] = parse_url($vars[$key], PHP_URL_HOST);
        }
    }
    
    $unresolved = array();
    foreach ($domains as $domain) {
        if (checkdnsrr($domain, 'A') === false) {
            $unresolved[] = $domain;
        }
    }

    if (empty($unresolved)) {
        $status = 'Ok';
        $msg = 'DNS A records do exist for ' . implode(', ', $domains);
    } else {
        $status = 'Error';
        $msg = 'DNS A records were not found for ' . implode(', ', $unresolved);
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:dns',$status, $msg],
    ]);
})->hidden();

desc('Ensure TYPO3_BASE_URL, TYPO3_BASE_URL and TYPO3_ALIAS_URL_* are reachable with HTTP code 200 or 404');
task('check:urls', function() {
    $vars = EnvUtility::getRemoteEnvVars();
    $baseUrl = $vars['TYPO3_BASE_URL'];
    $releaseUrl = $vars['TYPO3_RELEASE_URL'];
    $urls = array($baseUrl, $releaseUrl);
    foreach ($vars as $key => $value) {
        if (preg_match('/TYPO3\_ALIAS\_URL\_[0-9]+/', $key)) {
            $urls[] = $vars[$key];
        }
    }

    $failed = array();
    foreach ($urls as $url) {
        $headers = @get_headers($url, true);
        if ($headers === false) {
            $failed[] = $url . ': HTTP request failed';
        } else {
            $statusCode = $headers[0];
            if ($statusCode !== 'HTTP/1.1 200 OK' && $statusCode !== 'HTTP/1.1 404 Not Found') {
                $failed[] = $url . ': ' . $statusCode;
            }
        }
    }

    if (empty($failed)) {
        $status = 'Ok';
        $msg = 'Valid HTTP response codes: ' . implode(', ', $urls);
    } else {
        $status = 'Error';
        $msg = 'Invalid HTTP response codes: ' . implode(', ', $failed);
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:urls',$status, $msg],
    ]);
})->hidden();

desc('Ensure database can be accessed');
task('check:mysql', function() {
    $vars = EnvUtility::getRemoteEnvVars();
    $dbname = $vars['TYPO3_CONF_VARS__DB__Connections__Default__dbname'];
    $host = $vars['TYPO3_CONF_VARS__DB__Connections__Default__host'];
    $port = $vars['TYPO3_CONF_VARS__DB__Connections__Default__port'];
    $user = $vars['TYPO3_CONF_VARS__DB__Connections__Default__user'];
    $password = $vars['TYPO3_CONF_VARS__DB__Connections__Default__password'];

    if ($vars['TYPO3_CONF_VARS__DB__Connections__Default__user'] === '2048') {
        // if ssl is needed
        $result = run('mysqlshow --host=' .$host . ' --port=' . $port . ' --user=' . $user . ' --password=' . $password . ' --ssl ' . $dbname . ' > /dev/null 2>&1; echo $?');
    } else {
        $result = run('mysqlshow --host=' .$host . ' --port=' . $port . ' --user=' . $user . ' --password=' . $password . ' ' . $dbname . ' > /dev/null 2>&1; echo $?');
    }

    if ($result === '0') {
        $status = 'Ok';
        $msg = 'Database is accessible';
    } elseif ($result === '1') {
        $status = 'Error';
        $msg = 'Could not connect to database';
    } else {
        $status = 'Error';
        $msg = 'Unknown exit code: ' . $result;
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:mysql',$status, $msg],
    ]);
})->hidden();

desc('Ensure always mandatory php extensions are present');
task('check:php_extensions', function() {
    $required = array(
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
        'apcu'
    );
    $available = run('php -m');
    $missing = array();

    foreach ($required as $extension) {
        if (stripos($available, $extension) === false) {
            $missing[] = $extension;
        }
    }

    if (empty($missing)) {
        $status = 'Ok';
        $msg = 'Mandatory PHP extensions are present, perform manual checks for additional extensions like ldap and redis';
    } else {
        $status = 'Error';
        $msg = 'Mandatory extensions are missing: ' . implode(', ', $missing);
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:php_extensions',$status, $msg],
    ]);
})->hidden();

desc('Ensure mandatory php settings are set');
task('check:php_settings', function() {
    $baseUrl = EnvUtility::getRemoteEnvVars()['TYPO3_BASE_URL'];
    $docRoot = get('deploy_path') . '/current/public/';
    $phpFile = 'phpinfo_' . uniqid() . '.php';
    $configToJson = <<<'EOD'
<?php
\$data = array(
    'php_sapi_name' => php_sapi_name(),                        // must be "fpm-fcgi"
    'default_timezone' => date_default_timezone_get(),         // must be Europe/Berlin
    'memory_limit' => ini_get('memory_limit'),                 // must be 512M
    'max_execution_time' => ini_get('max_execution_time'),     // must be 240+
    'max_input_vars' => ini_get('max_input_vars'),             // must be 1500+
    'post_max_size' => ini_get('post_max_size'),               // must be 21M+
    'upload_max_filesize' => ini_get('upload_max_filesize'),   // must be 20M+
    'opcache.enable' => ini_get('opcache.enable'),             // must be 1
);
header(\"Content-Type: application/json\");
echo json_encode(\$data);
exit();
EOD;
    // output config as json via webserver
    run('mkdir -p ' . $docRoot);
    run('echo "' . $configToJson . '" > ' . $docRoot . $phpFile);
    //  and read json from webserver
    $json = run('wget ' . $baseUrl . '/' . $phpFile . ' -q -O - || echo "1"');
    if ($json === '1') {
        $status = 'Error';
        $msg = 'Could not read php config from url, vHost may be misconfigured';
    } else {
        $config =(json_decode($json, true));
        $errors = array();
    
        function return_bytes ($size_str) {
            switch (substr ($size_str, -1)) {
                case 'M': case 'm': return (int)$size_str * 1048576;
                case 'K': case 'k': return (int)$size_str * 1024;
                case 'G': case 'g': return (int)$size_str * 1073741824;
                default: return $size_str;
            }
        }
        
        if ($config['php_sapi_name'] !== 'fpm-fcgi') {
            $errors[] = 'PHP-FPM not enabled';
        }
        if ($config['default_timezone'] !== 'Europe/Berlin') {
            $errors[] = 'default timezone is not Europe/Berlin';
        }
        if (intval(return_bytes($config['memory_limit'])) < 536870912) {
            $errors[] = 'memory_limit is less than 512M';
        }
        if (intval($config['max_execution_time']) < 240 ) {
            $errors[] = 'max_execution_time is lower than 240s';
        }
        if (intval($config['max_input_vars']) < 1500 ) {
            $errors[] = 'max_input_vars is lower than 1500';
        }
        if (intval(return_bytes($config['post_max_size'])) < 22020096) {
            $errors[] = 'post_max_size is less than 21M';
        }
        if (intval(return_bytes($config['upload_max_filesize'])) < 20971520) {
            $errors[] = 'upload_max_filesize is less than 20M';
        }
        if ($config['opcache.enable'] !== '1' ) {
            $errors[] = 'opcache is disabled';
        }
    
        if (empty($errors)) {
            $status = 'Ok';
            $msg = 'Mandatory PHP settings are set';
        } else {
            $status = 'Error';
            $msg = 'Mandatory PHP settings are wrong: ' . implode(', ', $errors);
        }
    }
    
    // clean up
    run("rm " .$docRoot . $phpFile);

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:php_settings',$status, $msg],
    ]);
})->hidden();

desc('Ensure apache virtualhost for TYPO3_BASE_URL and TYPO3_ALIAS_URL_* matches requirements');
task('check:vhost_base', function() {
    $vars = EnvUtility::getRemoteEnvVars();
    $domain = parse_url($vars['TYPO3_BASE_URL'], PHP_URL_HOST);
    $aliases = array();
    foreach ($vars as $key => $value) {
        if (preg_match('/TYPO3\_ALIAS\_URL\_[0-9]+/', $key)) {
            $aliases[] = parse_url($vars[$key], PHP_URL_HOST);
        }
    }
    $docRoot = get('deploy_path') . '/current/public';
    $errors = array();

    // ensure vHost exists
    $vhost = run('grep -Rls "\s' . $domain . '" /etc/apache2/sites-enabled/ || echo "1"');
    if ($vhost === '1') {
        $status = 'Error';
        $msg = 'vHost not found for ' . $domain;
    } else {
        // ensure aliases are configured
        foreach ($aliases as $alias) {
            if (run('grep ServerAlias "' . $vhost . '" | grep -q "' . $alias . '"; echo $?') === '1') {
                $errors[] = 'Alias ' . $alias . ' missing';
            }
        }
        // ensure DocumentRoot is configured
        if (run('grep DocumentRoot "' . $vhost . '" | grep -q "' . $docRoot . '"; echo $?') === '1') {
            $errors[] = 'DocumentRoot';
        }
        // ensure Directory is configured
        if (run('grep "<Directory" "' . $vhost . '" | grep -q "' . $docRoot . '"; echo $?') === '1') {
            $errors[] = '<Directory>';
        }
        // ensure .htaccess  is enabled
        if (run('grep -q "AllowOverride All" "' . $vhost . '";echo $?') === '1') {
            $errors[] = 'AllowOverride All';
        }
        // ensure FollowSymLinks is enabled
        if (run('grep -Eq "+FollowSymLinks|FollowSymLinks" "' . $vhost . '";echo $?') === '1') {
            $errors[] = 'FollowSymLinks';
        }
        // // ensure Multiviews is enabled
        if (run('grep -Eq "+Multiviews|Multiviews" "' . $vhost . '";echo $?') === '1') {
            $errors[] = 'Multiviews';
        }

        if (empty($errors)) {
            $status = 'Ok';
            $msg = 'Configuration is ok: ' . $vhost;
        } else {
            $status = 'Error';
            $msg = 'Errors found in ' .$vhost . ': ' . implode(', ', $errors);
        }
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:vhost_base',$status, $msg],
    ]);
})->hidden();

desc('Ensure apache virtualhost for TYPO3_RELEASE_URL matches requirements');
task('check:vhost_release', function() {
    $domain = parse_url(EnvUtility::getRemoteEnvVars()['TYPO3_RELEASE_URL'], PHP_URL_HOST);
    $docRoot = get('deploy_path') . '/release/public';
    $errors = array();

    // ensure vHost exists
    $vhost = run('grep -Rls "\s' . $domain . '" /etc/apache2/sites-enabled/ || echo "1"');
    if ($vhost === '1') {
        $status = 'Error';
        $msg = 'vHost not found for ' . $domain;
    } else {
        // ensure DocumentRoot is configured
        if (run('grep DocumentRoot "' . $vhost . '" | grep -q "' . $docRoot . '"; echo $?') === '1') {
            $errors[] = 'DocumentRoot';
        }
        // ensure <Directory> is configured
        if (run('grep "<Directory" "' . $vhost . '" | grep -q "' . $docRoot . '"; echo $?') === '1') {
            $errors[] = '<Directory>';
        }
        // ensure .htaccess is enabled
        if (run('grep -q "AllowOverride All" "' . $vhost . '";echo $?') === '1') {
            $errors[] = 'AllowOverride All';
        }
        // ensure FollowSymLinks is enabled
        if (run('grep -Eq "+FollowSymLinks|FollowSymLinks" "' . $vhost . '";echo $?') === '1') {
            $errors[] = 'FollowSymLinks';
        }
        // ensure Multiviews is enabled
        if (run('grep -Eq "+Multiviews|Multiviews" "' . $vhost . '";echo $?') === '1') {
            $errors[] = 'Multiviews';
        }
        // ensure release variable is set
        if (run('grep -q "SetEnv IS_RELEASE_REQUEST 1" "' . $vhost . '";echo $?') === '1') {
            $errors[] = 'SetEnv IS_RELEASE_REQUEST 1';
        }

        if (empty($errors)) {
            $status = 'Ok';
            $msg = 'Configuration is ok: ' . $vhost;
        } else {
            $status = 'Error';
            $msg = 'Errors found in ' .$vhost . ': ' . implode(', ', $errors);
        }
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:vhost_release',$status, $msg],
    ]);
})->hidden();
