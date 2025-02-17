<?php

namespace Deployer;

use Deployer\Exception\GracefulShutdownException;
use SourceBroker\DeployerExtendedDatabase\Utility\ConsoleUtility;
use SourceBroker\DeployerExtendedDatabase\Utility\OptionUtility;

task('reset:from_gitlab_artifact', function () {
    // set in deploy.php as https://gitlab.example.org/api/v4/projects/<project-id>/jobs/artifacts/<branch>/download?job=<job-of-artifact>
    $url = get('reset_gitlab_artifact_url');
    // Gitlab API token for the repository where the artifact is stored. Can be a project token.
    $optionUtility = new OptionUtility(input()->getOption('options'));
    $gitlabApiToken = $optionUtility->getOption('token', true);
    // Database dumpcode that was used during the creation of the Gitlab artifact.
    $dumpCode = $optionUtility->getOption('dumpcode', true);

    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#jobs\/artifacts\/.+\/download$#', parse_url($url, PHP_URL_PATH))) {
        throw new GracefulShutdownException('Gitlab API URL is invalid: "' . $url . '"');
    }
    if (get('is_argument_host_the_same_as_local_host')) {
        $activeDir = get('deploy_path') . (testLocally('[ -e {{deploy_path}}/release ]') ? '/release' : '/current');
        $activeDir = testLocally('[ -e ' . $activeDir . ' ]') ? $activeDir : get('deploy_path');
        runLocally('cd ' . $activeDir . ' && curl --location --output artifacts.zip --header "PRIVATE-TOKEN: ' . $gitlabApiToken . '" "' . $url . '"');
        runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:rmdump {{argument_host}} --options=dumpcode:' . $dumpCode . ' --no-interaction');
        runLocally('cd ' . $activeDir . ' && unzip -o artifacts.zip');
        runLocally('cd ' . $activeDir . ' && mv -n .dep/database/dumps/*dumpcode=' . $dumpCode . '* {{db_storage_path_local}}/');
        runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:decompress {{argument_host}} --options=dumpcode:' . $dumpCode . ' --no-interaction');
        runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:import {{argument_host}} --options=dumpcode:' . $dumpCode . ' --no-interaction');
        runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:rmdump {{argument_host}} --options=dumpcode:' . $dumpCode . ' --no-interaction');
        runLocally('cd ' . $activeDir . ' && rm -f artifacts.zip');
        runLocally('cd ' . $activeDir . ' && {{local/bin/php}} {{bin/typo3cms}} cache:flush');
        runLocally('cd ' . $activeDir . ' && {{local/bin/php}} {{bin/typo3cms}} cache:warmup');
    } else {
        $verbosity = (new ConsoleUtility())->getVerbosityAsParameter();
        run('cd {{release_or_current_path}} && {{bin/php}} {{bin/deployer}} reset:from_gitlab_artifact ' . $verbosity . ' --options="token:' . $gitlabApiToken . ',dumpcode:' . $dumpCode . '" {{argument_host}}');
    }
});
