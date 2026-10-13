<?php

namespace Xima\XimaDeployerExtendedTypo3\Utility;

use Symfony\Component\Dotenv\Dotenv;

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

    /**
     * Remote env vars as the app reads them: .env, .env.local, .env.<APP_ENV>, .env.<APP_ENV>.local, later files win.
     */
    public static function getRemoteAppEnvVars(): array
    {
        $activePath = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/release ]') ? 'release' : 'current');
        $dotenv = new Dotenv();
        $read = static function (string $file) use ($activePath, $dotenv): ?array {
            $path = $activePath . '/' . $file;
            return test('[ -f ' . $path . ' ]') ? $dotenv->parse(run('cat ' . $path), $path) : null;
        };

        $vars = $read('.env') ?? $read('.env.dist') ?? [];
        $env = $vars['APP_ENV'] ?? 'dev';
        if ($env !== 'test') {
            $vars = array_merge($vars, $read('.env.local') ?? []);
            $env = $vars['APP_ENV'] ?? $env;
        }
        if ($env !== 'local') {
            $vars = array_merge($vars, $read('.env.' . $env) ?? [], $read('.env.' . $env . '.local') ?? []);
        }

        return $vars;
    }
}