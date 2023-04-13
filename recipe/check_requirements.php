<?php

namespace Deployer;

use SourceBroker\DeployerExtendedDatabase\Utility\ConsoleUtility;
use SourceBroker\DeployerExtendedTypo3\Utility\ConsoleUtility as ConsoleUtilityAlias;
use Symfony\Component\Console\Helper\Table;

task('check:system', [
    'checks:permissions',
    'checks:requirements',
]);

task('check:permissions', function () {
    runLocally('echo "hello"');
});

task('check:requirements', function () {

    $verbosity = (new ConsoleUtilityAlias())->getVerbosityAsParameter();

    //if (!get('is_argument_host_the_same_as_local_host')) {
    //    $activePath = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/current ]') ? 'current' : 'release');
    //    run('cd ' . $activePath . ' && vendor/bin/dep migrate:import-v9-database {{argument_host}} ' . $verbosity);
    //} else {
    //    runLocally('cat .migration/backup_v9.sql | {{bin/typo3cms}} database:import ' . $verbosity);
    //}



    //$releasesLog = get('releases_log');
    //$currentRelease = basename(run('readlink {{current_path}}'));
    //$releasesList = get('releases_list');
    //
    //$table = [];
    //$tz = !empty(getenv('TIMEZONE')) ? getenv('TIMEZONE') : date_default_timezone_get();
    //
    //foreach ($releasesLog as &$metainfo) {
    //    $date = \DateTime::createFromFormat(\DateTimeInterface::ISO8601, $metainfo['created_at']);
    //    $date->setTimezone(new \DateTimeZone($tz));
    //    $status = $release = $metainfo['release_name'];
    //    if (in_array($release, $releasesList, true)) {
    //        if (test("[ -f releases/$release/BAD_RELEASE ]")) {
    //            $status = "<error>$release</error> (bad)";
    //        } else if (test("[ -f releases/$release/DIRTY_RELEASE ]")) {
    //            $status = "<error>$release</error> (dirty)";
    //        } else {
    //            $status = "<info>$release</info>";
    //        }
    //    }
    //    if ($release === $currentRelease) {
    //        $status .= ' (current)';
    //    }
    //    try {
    //        $revision = run("cat releases/$release/REVISION");
    //    } catch (\Throwable $e) {
    //        $revision = 'unknown';
    //    }
    //    $table[] = [
    //        $date->format("Y-m-d H:i:s"),
    //        $status,
    //        $metainfo['user'],
    //        $metainfo['target'],
    //        $revision,
    //    ];
    //}
    //
    //(new Table(output()))
    //    ->setHeaderTitle(currentHost()->getAlias())
    //    ->setHeaders(["Date ($tz)", 'Release', 'Author', 'Target', 'Commit'])
    //    ->setRows($table)
    //    ->render();

});