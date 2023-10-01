<?php

namespace Deployer;

task('deploy:writableLocalConfiguration', function () {
    $remotePath = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/release ]') ? 'release' : 'current');
    $confPath = $remotePath . '/public/typo3conf/LocalConfiguration.php';
    if (test('[ -f ' . $confPath . ' ]')) {
        run('chmod 660 ' . $confPath);
    }
});
