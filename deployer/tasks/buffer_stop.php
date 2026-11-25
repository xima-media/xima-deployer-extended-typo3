<?php

namespace Deployer;

// Read more on https://github.com/sourcebroker/deployer-extended#buffer-stop
use Deployer\Exception\GracefulShutdownException;

task('buffer:stop', function () {
    if (empty(get('deploy_path'))) {
        throw new GracefulShutdownException('The "deploy_path" var is empty but its used for file operations so its dangerous state.');
    }
    $overwriteReleases = ['current'];
    $releasesList = get('releases_list');
    if (isset($releasesList[1])) {
        $overwriteReleases[] = 'releases/' . $releasesList[1];
    }
    // Add .flag.oldrelease to all releases except the one "current" points to, a worker can still resolve "current" to any of them
    $currentRelease = basename(run('readlink -f ' . get('deploy_path') . '/current'));
    foreach (get('buffer_config') as $inject) {
        if (empty($inject['entrypoint_filename'])) {
            throw new GracefulShutdownException('entrypoint_filename not set for buffer_data');
        }
        $oldReleaseFlagFilename = empty($inject['oldrelease_flag_filename']) ?
            '.flag.oldrelease' : $inject['oldrelease_flag_filename'];
        $entrypointDirectory = dirname($inject['entrypoint_filename']) === '.' ? '' : dirname($inject['entrypoint_filename']) . '/';
        run('cd ' . get('deploy_path') . '/releases && for release in */; do release=${release%/}; ' .
            '[ -f "$release/"' . escapeshellarg($inject['entrypoint_filename']) . ' ] || continue; ' .
            'flag="$release/"' . escapeshellarg($entrypointDirectory . $oldReleaseFlagFilename) . '; ' .
            'if [ "$release" = ' . escapeshellarg($currentRelease) . ' ]; then rm -f "$flag"; else touch "$flag"; fi; done');
    }
    // Remove .flag.requestbuffer also from previous release because it can be still read because php cache
    foreach ($overwriteReleases as $overwriteRelease) {
        $overwriteReleasePath = get('deploy_path') . '/' . $overwriteRelease;
        foreach (get('buffer_config') as $inject) {
            if (empty($inject['entrypoint_filename'])) {
                throw new GracefulShutdownException('entrypoint_filename not set for buffer_data');
            }
            $activateBufferFlagFilename = empty($inject['requestbuffer_flag_filename']) ?
                '.flag.requestbuffer' : $inject['requestbuffer_flag_filename'];
            $entrypointDirectory = dirname($inject['entrypoint_filename']) === '.' ? '' : dirname($inject['entrypoint_filename']) . '/';
            run('cd ' . $overwriteReleasePath . ' && rm -f ' . $entrypointDirectory . $activateBufferFlagFilename);
        }
    }
})->desc('Stop buffering requests to application entry points');

// After a rollback, mark all other releases so workers stop serving them.
after('rollback', 'buffer:stop');
