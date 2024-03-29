<?php

namespace Deployer;

task('logs:phpfpm', function () {
    run('tail -F /var/log/php*www*.log');
})->verbose();

task('logs:phpfpm-access', function () {
    run('tail -F /var/log/php*www.access.log');
})->verbose();

task('logs:phpfpm-slow', function () {
    run('tail -F -n 30 /var/log/php*www.slow.log');
})->verbose();

task('logs:phpfpm-error', function () {
    run('tail -F -n +1 /var/log/php*www.error.log');
})->verbose();
