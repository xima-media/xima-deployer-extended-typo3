<?php

namespace Deployer;

option(
    'GITLAB_ARTIFACT_ACCESS_TOKEN',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
    'Gitlab access token of the repository from where the artifact is downloaded.'
);

option(
    'GITLAB_ARTIFACT_URL',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
    'URL to Gitlab artifact, https://<domain>/api/v4/projects/<project-id>/jobs/artifacts/<branch>/'
);

option(
    'GITLAB_ARTIFACT_DUMPCODE',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
    'Dumpcode of database in Gitlab artifact.'
);

task('reset:from_gitlab_artifact', function () {
    if (get('is_argument_host_the_same_as_local_host')) {
        $activeDir = get('deploy_path') . (testLocally('[ -e {{deploy_path}}/release ]') ? '/release' : '/current');
        $activeDir = testLocally('[ -e ' . $activeDir . ' ]') ? $activeDir : get('deploy_path');
        runLocally('cd ' . $activeDir . ' && curl --location --output artifacts.zip --header "PRIVATE-TOKEN: {{GITLAB_ARTIFACT_ACCESS_TOKEN}}" "{{GITLAB_ARTIFACT_URL}}/download?job=backup-live-to-artifact"');
        runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:rmdump {{argument_host}} --options=dumpcode:{{GITLAB_ARTIFACT_DUMPCODE}} --no-interaction');
        runLocally('cd ' . $activeDir . ' && unzip -o artifacts.zip');
        runLocally('cd ' . $activeDir . ' && mv -n .dep/database/dumps/*dumpcode={{GITLAB_ARTIFACT_DUMPCODE}}* {{db_storage_path_local}}/');
        runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:decompress {{argument_host}} --options=dumpcode:{{GITLAB_ARTIFACT_DUMPCODE}} --no-interaction');
        runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:import {{argument_host}} --options=dumpcode:{{GITLAB_ARTIFACT_DUMPCODE}} --no-interaction');
        runLocally('cd ' . $activeDir . ' && vendor/bin/dep db:rmdump {{argument_host}} --options=dumpcode:{{GITLAB_ARTIFACT_DUMPCODE}} --no-interaction');
        runLocally('cd ' . $activeDir . ' && rm -f artifacts.zip');
        runLocally('cd ' . $activeDir . ' && {{local/bin/php}} {{bin/typo3cms}} cache:flush');
        runLocally('cd ' . $activeDir . ' && {{local/bin/php}} {{bin/typo3cms}} cache:warmup');
    } else {
        $verbosity = (new ConsoleUtility())->getVerbosityAsParameter();
        run('cd {{release_or_current_path}} && {{bin/php}} {{bin/deployer}} reset:from_gitlab_artifact {{argument_host}} -o GITLAB_ARTIFACT_ACCESS_TOKEN="{{GITLAB_ARTIFACT_ACCESS_TOKEN}}" -o GITLAB_ARTIFACT_URL="{{GITLAB_ARTIFACT_URL}}" -o GITLAB_ARTIFACT_DUMPCODE="{{GITLAB_ARTIFACT_DUMPCODE}}" ' . $verbosity);
    }
});
