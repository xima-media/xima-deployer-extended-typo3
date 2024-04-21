<?php

namespace Deployer;

option(
    'gitlab_api_token',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
    'Gitlab API token for the repository whre the artifact is stored. Can be a project token.'
);

option(
    'dumpcode',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
    'Database dumpcode that was used during the creation of the Gitlab artifact.'
);

task('reset:from_gitlab_artifact', function () {
    // set in deploy.php as https://gitlab.example.org/api/v4/projects/<project-id>/jobs/artifacts/<branch>/download?job=<job-of-artifact>
    $url = get('reset_gitlab_artifact_url');

    if ( filter_var($url, FILTER_VALIDATE_URL) && preg_match('#jobs\/artifacts\/.+\/download$#', parse_url($url, PHP_URL_PATH)) ) {
        if (get('is_argument_host_the_same_as_local_host')) {
            $activeDir = get('deploy_path') . (testLocally('[ -e {{deploy_path}}/release ]') ? '/release' : '/current');
            $activeDir = testLocally('[ -e ' . $activeDir . ' ]') ? $activeDir : get('deploy_path');
            runLocally('cd ' . $activeDir . ' && curl --location --output artifacts.zip --header "PRIVATE-TOKEN: {{gitlab_api_token}}" "' . $url . '"');
            runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:rmdump {{argument_host}} --options=dumpcode:{{dumpcode}} --no-interaction');
            runLocally('cd ' . $activeDir . ' && unzip -o artifacts.zip');
            runLocally('cd ' . $activeDir . ' && mv -n .dep/database/dumps/*dumpcode={{dumpcode}}* {{db_storage_path_local}}/');
            runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:decompress {{argument_host}} --options=dumpcode:{{dumpcode}} --no-interaction');
            runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:import {{argument_host}} --options=dumpcode:{{dumpcode}} --no-interaction');
            runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:rmdump {{argument_host}} --options=dumpcode:{{dumpcode}} --no-interaction');
            runLocally('cd ' . $activeDir . ' && rm -f artifacts.zip');
            runLocally('cd ' . $activeDir . ' && {{local/bin/php}} {{bin/typo3cms}} cache:flush');
            runLocally('cd ' . $activeDir . ' && {{local/bin/php}} {{bin/typo3cms}} cache:warmup');
        } else {
            $verbosity = (new ConsoleUtility())->getVerbosityAsParameter();
            run('cd {{release_or_current_path}} && {{bin/php}} {{bin/deployer}} reset:from_gitlab_artifact {{argument_host}} -o gitlab_api_token="{{gitlab_api_token}}" -o dumpcode="{{dumpcode}}" ' . $verbosity);
        }
    } else {
        writeln('Gitlab API URL is invalid: ' . $url);
        exit(1);
    }
});
