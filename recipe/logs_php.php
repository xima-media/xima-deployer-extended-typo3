<?php

namespace Deployer;

task('logs:php-fpm', function () {
    run('tail -F /var/log/php*.log');
})->verbose();
