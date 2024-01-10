<?php

namespace Deployer;

task('logs:php-fpm', function () {
    run('tail -f /var/log/php*.log');
})->verbose();
