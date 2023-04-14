<?php

namespace Xima\XimaDeployerExtendedTypo3\Utility;


use function Deployer\get;
use function Deployer\run;
use function Deployer\test;

class EnvUtility {

    public static function getRemoteEnvVars(): array
    {
        $activePath = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/release ]') ? 'release' : 'current');
        $remoteEnv = run('cat ' . $activePath . '/.env');
        $lines = array_filter(explode(PHP_EOL, $remoteEnv));
        $vars = [];

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            $result = explode('=', $line, 2);
            $vars[trim($result[0])] = str_replace('\'', '', trim($result[1]));
        }

        return $vars;
    }
}