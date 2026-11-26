<?php

namespace Deployer;

use Deployer\Exception\GracefulShutdownException;

// Read more on https://github.com/sourcebroker/deployer-extended#buffer-start
task('buffer:start', function () {
    if (empty(get('deploy_path'))) {
        throw new GracefulShutdownException('The "deploy_path" var is empty but its used for file operations so its dangerous state.');
    }
    if (empty(get('random'))) {
        throw new GracefulShutdownException('The "random" config var is empty. Its needed for file operation.');
    }
    if (preg_match('/^[a-zA-Z0-9]$/', get('random'))) {
        throw new GracefulShutdownException('The "random" config var should be only /^[a-zA-Z0-9]$/ because its used in filenames.');
    }
    $currentPath = var_export(run('cd ' . get('deploy_path') . ' && pwd -P') . '/current', true);
    // "release" and "current" get the injected code and buffer requests. In all other retained releases, code injected
    // by earlier versions is replaced, so a worker that still resolves "current" to one of them switches as well.
    $overwriteReleases = [];
    foreach (['release', 'current'] as $overwriteRelease) {
        $overwriteReleases[get('deploy_path') . '/' . $overwriteRelease . '/'] = true;
    }
    $bufferedReleaseDirs = explode("\n", run('for link in release current; do [ -e ' . get('deploy_path') . '/$link ] && readlink -f ' . get('deploy_path') . '/$link; done; true'));
    if (test('[ -d ' . get('deploy_path') . '/releases ]')) {
        $releaseDirs = explode("\n", run('cd ' . get('deploy_path') . '/releases && for release in */; do [ -d "$release" ] && readlink -f "$release"; done; true'));
        foreach (array_diff(array_filter($releaseDirs), $bufferedReleaseDirs) as $releaseDir) {
            $overwriteReleases[$releaseDir . '/'] = false;
        }
    }
    foreach ($overwriteReleases as $overwriteReleasePath => $startBuffer) {
        if (test("[ -e $overwriteReleasePath ]")) {
            $tempPath = $overwriteReleasePath . get('random');
            run('[ -e ' . $tempPath . ' ] || mkdir -p ' . $tempPath);
            $entrypointInjectStartComment = "\n\n// deployer extended buffering request code START\n";
            $entrypointInjectEndComment = "\n// deployer extended buffering request code END\n\n";
            foreach ((array)get('buffer_config') as $inject) {
                if (empty($inject['entrypoint_filename'])) {
                    throw new GracefulShutdownException('entrypoint_filename not set for buffer_data');
                }
                // general inject settings
                $entrypointFilename = $inject['entrypoint_filename'];
                // TYPO3 v14 removed typo3/index.php and typo3/install.php
                if (!test('[ -f ' . $overwriteReleasePath . $entrypointFilename . ' ]')) {
                    continue;
                }
                if (empty($inject['entrypoint_needle'])) {
                    $entrypointNeedle = '<?php';
                } else {
                    $entrypointNeedle = $inject['entrypoint_needle'];
                }

                // Buffering php requests
                if (empty($inject['requestbuffer_sleep'])) {
                    $requestBufferSleep = 200000; // 200ms
                } else {
                    $requestBufferSleep = (int)$inject['requestbuffer_sleep'];
                }
                if (empty($inject['requestbuffer_flag_filename'])) {
                    $requestBufferFlagFilename = '.flag.requestbuffer';
                } else {
                    $requestBufferFlagFilename = $inject['requestbuffer_flag_filename'];
                }
                if (empty($inject['requestbuffer_duration'])) {
                    $requestBufferDuration = 60;
                } else {
                    $requestBufferDuration = (int)$inject['requestbuffer_duration'];
                }

                // Action if php choose old release to serve request
                if (empty($inject['oldrelease_flag_filename'])) {
                    $oldReleaseFlagFilename = '.flag.oldrelease';
                } else {
                    $oldReleaseFlagFilename = $inject['oldrelease_flag_filename'];
                }
                $oldReleaseRedirectSleep = (int)($inject['oldrelease_redirect_sleep'] ?? 0);

                if (empty($inject['entrypoint_inject'])) {
                    $entrypointInject =
                        "// Buffering php requests\n" .
                        "clearstatcache(true, __DIR__ . '/$requestBufferFlagFilename');\n" .
                        "while (file_exists(__DIR__ . '/$requestBufferFlagFilename')) {\n" .
                        "    usleep($requestBufferSleep);\n" .
                        "    clearstatcache(true);\n" .
                        "    if(time() - @filectime(__DIR__ . '/$requestBufferFlagFilename') > $requestBufferDuration) @unlink(__DIR__ . '/$requestBufferFlagFilename');\n" .
                        "}\n" .
                        "// Action if php choose old release to serve request\n" .
                        "clearstatcache(true, __DIR__ . '/$oldReleaseFlagFilename');\n" .
                        "if(file_exists(__DIR__ . '/$oldReleaseFlagFilename')) {\n" .
                        "    clearstatcache(true);\n" .
                        "    \$deployerExtendedCurrentEntrypoint = realpath($currentPath) . " . var_export('/' . $entrypointFilename, true) . ";\n" .
                        "    // Entry points removed in the current release (e.g. typo3/index.php in TYPO3 v14) keep running here\n" .
                        "    if(strpos(realpath(__DIR__) . '/', realpath($currentPath) . '/') !== 0 && is_file(\$deployerExtendedCurrentEntrypoint)) {\n" .
                        ($oldReleaseRedirectSleep > 0 ? "        usleep($oldReleaseRedirectSleep);\n" : '') .
                        "        include \$deployerExtendedCurrentEntrypoint;\n" .
                        "        exit();\n" .
                        "    }\n" .
                        "}";
                } else {
                    $entrypointInject = $inject['entrypoint_inject'];
                }
                $entrypointFileContent = trim(run('cd ' . $overwriteReleasePath . ' && [ -f ' . $entrypointFilename . ' ] && cat ' . $entrypointFilename . ' || echo ""'));
                if (empty($entrypointFileContent)) {
                    throw new GracefulShutdownException('Can not find file to overwrite or the file is empty. File that was read is: ' . $overwriteReleasePath . $entrypointFilename);
                }
                $entrypointInjectBlock = $entrypointInjectStartComment . $entrypointInject . $entrypointInjectEndComment;
                $blockStart = strpos($entrypointFileContent, $entrypointInjectStartComment);
                if ($blockStart === false && !$startBuffer) {
                    continue;
                }
                if ($blockStart !== false) {
                    $blockEnd = strpos($entrypointFileContent, $entrypointInjectEndComment, $blockStart);
                    if ($blockEnd === false) {
                        throw new GracefulShutdownException('Can not find end of injected code in ' . $overwriteReleasePath . $entrypointFilename);
                    }
                    $content = substr_replace($entrypointFileContent, $entrypointInjectBlock, $blockStart, $blockEnd + strlen($entrypointInjectEndComment) - $blockStart);
                } else {
                    $pos = strpos($entrypointFileContent, $entrypointNeedle);
                    if ($pos === false) {
                        throw new GracefulShutdownException('Can not find needle to inject with inclusion.');
                    }
                    $content = substr_replace($entrypointFileContent, $entrypointNeedle . $entrypointInjectBlock, $pos, strlen($entrypointNeedle));
                }
                if ($content !== $entrypointFileContent) {
                    $entrypointAbsolutePath = $overwriteReleasePath . $entrypointFilename;
                    run('cp -p ' . $entrypointAbsolutePath . ' ' . $tempPath . '/');
                    run('echo ' . escapeshellarg($content) . ' > ' . $tempPath . '/' . basename($entrypointAbsolutePath));
                    run('mv -fT ' . $tempPath . '/' . basename($entrypointAbsolutePath) . ' ' . $entrypointAbsolutePath);
                }
                if ($startBuffer && test('[ -e ' . $overwriteReleasePath . dirname($entrypointFilename) . ' ]')) {
                    run('cd ' . $overwriteReleasePath . ' && touch ' . (dirname($entrypointFilename) ? dirname($entrypointFilename) . '/' : '') . $requestBufferFlagFilename);
                }
            }
            run('rmdir ' . $tempPath);
        }
    }
})->desc('Start buffering requests to application entry points');