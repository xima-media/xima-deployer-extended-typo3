<?php

namespace Deployer;

use Xima\XimaDeployerExtendedTypo3\Utility\EnvUtility;

task('environment:urls', function () {
    $vars = EnvUtility::getRemoteAppEnvVars();

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
    output()->write($content);
})->once()->desc('Print the environment\'s primary and alias URLs for a GitLab dotenv report');
