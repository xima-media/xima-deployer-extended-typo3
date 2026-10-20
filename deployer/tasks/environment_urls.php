<?php

namespace Deployer;

use Xima\XimaDeployerExtendedTypo3\Utility\EnvUtility;

// File that environment:urls:write creates after every deployment, relative to the working directory.
// Empty disables it. Enable it in CI e.g. with `-o environment_urls_file=environment-urls.env`.
set('environment_urls_file', '');

task('environment:urls', function () {
    output()->write(EnvUtility::getEnvironmentUrls());
})->once()->desc('Print the environment\'s primary and alias URLs for a GitLab dotenv report');

task('environment:urls:write', function () {
    $file = get('environment_urls_file');
    if ($file === '') {
        return;
    }
    // The release is already live: a missing or invalid URL must not fail the deployment.
    try {
        $content = EnvUtility::getEnvironmentUrls();
    } catch (\RuntimeException $e) {
        warning('environment:urls:write skipped: ' . $e->getMessage());
        if (is_file($file)) {
            unlink($file);
        }
        return;
    }
    file_put_contents($file, $content);
    info('Environment URLs written to ' . $file);
})->once()->hidden();

after('deploy:success', 'environment:urls:write');
