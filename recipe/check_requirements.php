<?php

namespace Deployer;

use SourceBroker\DeployerExtendedDatabase\Utility\ConsoleUtility;
use SourceBroker\DeployerExtendedTypo3\Utility\ConsoleUtility as ConsoleUtilityAlias;
use Symfony\Component\Console\Helper\Table;

set('requirement_rows', []);

desc('Check if deployment requirements are fulfilled');
task('check:requirements', [
    'check:group',
    'check:summary'
]);

task('check:summary', function () {
    (new Table(output()))
       ->setHeaderTitle(currentHost()->getAlias())
       ->setHeaders(['Task', 'Status', 'Info'])
       ->setRows(get('requirement_rows'))
       ->render();
})->hidden();

desc('Ensure user has primary group www-data');
task('check:group', function () {
    $primaryUserGroup = run('id -gn');

    if ($primaryUserGroup === "www-data") {
        $status = "Ok";
        $msg = "User is member of $primaryUserGroup";
    } else {
        $status = "Error";
        $msg = "Primary group must be www-data (is $primaryUserGroup)";
    }

    set('requirement_rows', [
        ...get('requirement_rows'),
        ['check:group',$status, $msg],
    ]);
})->hidden();
