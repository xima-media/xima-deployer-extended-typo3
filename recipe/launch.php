<?php

namespace Deployer;

use Xima\XimaDeployerExtendedTypo3\Utility\EnvUtility;

task('launch', function () {
    $vars = EnvUtility::getRemoteEnvVars();
    output()->writeln($vars['TYPO3_BASE_URL']);
})->desc('Print TYPO3_BASE_URL');
