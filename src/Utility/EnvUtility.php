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
     *
     * $namePattern (POSIX ERE) limits the variables to read. The files are filtered on the remote host, so no
     * other line reaches Deployer's output, which prints remote command output from -v upwards. APP_ENV is
     * always read. A value that references a filtered variable resolves to an empty string.
     */
    public static function getRemoteAppEnvVars(string $namePattern = '[A-Za-z_][A-Za-z0-9_]*'): array
    {
        $activePath = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/release ]') ? 'release' : 'current');
        $dotenv = new Dotenv();
        $grep = escapeshellarg('^[[:space:]]*(export[[:space:]]+)?(APP_ENV|' . $namePattern . ')[[:space:]]*=');
        $read = static function (string $file) use ($activePath, $dotenv, $grep): ?array {
            $path = $activePath . '/' . $file;
            return test('[ -f ' . $path . ' ]')
                ? $dotenv->parse(run('grep -E ' . $grep . ' ' . escapeshellarg($path) . ' || true'), $path)
                : null;
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

    /**
     * ENVIRONMENT_URL_PRIMARY (TYPO3_BASE_URL) and ENVIRONMENT_URL_ALIASES (TYPO3_ALIAS_URL_0, _1, ... in index
     * order) of the current host as dotenv lines.
     */
    public static function getEnvironmentUrls(): string
    {
        $vars = self::getRemoteAppEnvVars('TYPO3_BASE_URL|TYPO3_ALIAS_URL_[0-9]+');

        $isUrl = static fn (string $url): bool => in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            && (string)parse_url($url, PHP_URL_HOST) !== ''
            && preg_match('/[\s,]/', $url) === 0;

        $primary = $vars['TYPO3_BASE_URL'] ?? '';
        if (!$isUrl($primary)) {
            throw new \RuntimeException('TYPO3_BASE_URL must be a full URL, got "' . $primary . '"');
        }

        $aliases = [];
        foreach ($vars as $key => $value) {
            if (preg_match('/^TYPO3_ALIAS_URL_(\d+)$/', $key, $match) === 1) {
                if ($match[1] !== (string)(int)$match[1]) {
                    throw new \RuntimeException($key . ': index with leading zero');
                }
                if (!$isUrl($value)) {
                    throw new \RuntimeException($key . ' must be a full URL, got "' . $value . '"');
                }
                $aliases[(int)$match[1]] = $value;
            }
        }
        ksort($aliases);
        if ($aliases !== [] && array_keys($aliases) !== range(0, count($aliases) - 1)) {
            throw new \RuntimeException('TYPO3_ALIAS_URL_* must be numbered 0, 1, 2, ... without gaps, got ' . implode(', ', array_keys($aliases)));
        }

        $content = 'ENVIRONMENT_URL_PRIMARY=' . $primary . "\n";
        if ($aliases !== []) {
            $content .= 'ENVIRONMENT_URL_ALIASES=' . implode(',', $aliases) . "\n";
        }

        return $content;
    }
}
