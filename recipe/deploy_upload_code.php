<?php

namespace Deployer;

task('deploy:upload_code', function () {
    // upload files/folders
    foreach (get('upload_paths') as $path) {
        if (testLocally('[ -f ' . $path . ' ]') || testLocally('[ -d ' . $path . ' ]')) {
            upload($path, '{{release_path}}/', ['options' => ['-Rq']]);
        }
    }

    // fix permissions
    run('find {{release_path}} -type d -exec chmod {{writable_chmod_mode}} {} \;');
    run('find {{release_path}} -type f -exec chmod 0640 {} \;');
});
