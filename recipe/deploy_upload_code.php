<?php

namespace Deployer;

// upload needed files + fix permissions
task('deploy:upload_code', function () {
    upload('composer.json', '{{release_path}}/composer.json');
    upload('composer.lock', '{{release_path}}/composer.lock');
    upload('deploy.php', '{{release_path}}/deploy.php');
    upload('config', '{{release_path}}/');
    upload('packages', '{{release_path}}/');
    upload('public/.htaccess', '{{release_path}}/public/');
    upload('public/typo3conf/LocalConfiguration.php', '{{release_path}}/public/typo3conf/');
    upload('public/typo3conf/AdditionalConfiguration.php', '{{release_path}}/public/typo3conf/');
    run('find {{release_path}} -type d -exec chmod {{writable_chmod_mode}} {} \;');
    run('find {{release_path}} -type f -exec chmod 0640 {} \;');
});