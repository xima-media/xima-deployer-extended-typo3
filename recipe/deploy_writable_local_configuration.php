<?php

namespace Deployer;

task('deploy:writableLocalConfiguration', function () {
    $remotePath = get('deploy_path') . '/' . (test('[ -L {{deploy_path}}/release ]') ? 'release' : 'current');
    $configFiles = [
        $remotePath . '/public/typo3conf/LocalConfiguration.php',
        $remotePath . '/config/system/settings.php',
    ];
    foreach ($configFiles as $filePath) {
        if (test('[ -f ' . $filePath . ' ]')) {
            run('chmod 660 ' . $filePath);
        }
    }
});
