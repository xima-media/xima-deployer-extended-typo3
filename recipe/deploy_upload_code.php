<?php

namespace Deployer;

task('deploy:upload_code', function () {
    // upload files/folders
    foreach (get('upload_paths') as $path) {
        if (test('[ -f ' . $path . ' ]') || test('[ -d ' . $path . ' ]')) {
            upload($path, '{{release_path}}/', ['options' => ['-R']]);
        }
    }

    // fix permissions
    run('find {{release_path}} -type d -exec chmod {{writable_chmod_mode}} {} \;');
    run('find {{release_path}} -type f -exec chmod 0640 {} \;');
});
