<?php

namespace Deployer;

use SourceBroker\DeployerExtendedDatabase\Utility\ConsoleUtility;
use SourceBroker\DeployerExtendedTypo3\Utility\ConsoleUtility as ConsoleUtilityAlias;
use Symfony\Component\Console\Helper\Table;
use Xima\XimaDeployerExtendedTypo3\Utility\EnvUtility;

set('requirement_rows', []);

desc('Check if deployment requirements are fulfilled');
task('check:requirements', [
    'check:user',
    'check:permissions',
    'check:env',
    'check:instance',
    'check:summary'
]);

task('check:summary', function () {
    (new Table(output()))
       ->setHeaderTitle(currentHost()->getAlias())
       ->setHeaders(['Task', 'Status', 'Info'])
       ->setRows(get('requirement_rows'))
       ->render();
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
task('check:env', function() {
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
        ['check:env',$status, $msg],
    ]);
})->hidden();

desc('Ensure INSTANCE in .env matches deployer hostname');
task('check:instance', function() {
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
        ['check:instance',$status, $msg],
    ]);
})->hidden();
