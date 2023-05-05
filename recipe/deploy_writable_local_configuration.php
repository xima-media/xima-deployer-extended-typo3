<?php

namespace Deployer;

task('deploy:writableLocalConfiguration', function () {
    $remotePath = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/release ]') ? 'release' : 'current');
    run('chmod 660 ' . $remotePath . '/public/typo3conf/LocalConfiguration.php');
});