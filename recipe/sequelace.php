<?php

namespace Deployer;

use Xima\XimaDeployerExtendedTypo3\Utility\EnvUtility;

// SSH support in progress, see https://github.com/Sequel-Ace/Sequel-Ace/pull/1703
task('sequelace', function () {
    $vars = EnvUtility::getRemoteEnvVars();
    output()->writeln('mysql://' . $vars['TYPO3_CONF_VARS__DB__Connections__Default__user'] . ':' . $vars['TYPO3_CONF_VARS__DB__Connections__Default__password'] . '@' . $vars['TYPO3_CONF_VARS__DB__Connections__Default__host'] . ':' . $vars['TYPO3_CONF_VARS__DB__Connections__Default__port'] . '/' . $vars['TYPO3_CONF_VARS__DB__Connections__Default__dbname'] . '?ssh_user='. get('remote_user') . '&ssh_host=' . get('hostname'));
})->desc('Get mysql inline uri for fast connection with Sequel Ace');