<?php

namespace Deployer;

// The database tasks of sourcebroker/deployer-extended-database (db:copy, db:import, ...) call each
// other as nested Deployer processes, on the CI runner and on the remote host. Each of these processes
// capped its commands at default_timeout (300 seconds), which is too short to copy a large database,
// e.g. when db:init fills a new feature branch environment.
// Every db:* task now runs with db_timeout instead. 0 disables the limit.
set('db_timeout', 1800);

// Deployer 7 runs runLocally() on a new Localhost that reads the global configuration, so the global
// default is raised in every Deployer process that runs a db:* task: the task workers (--task db:init)
// and the nested db:* processes on the CI runner and on the remote host.
if (preg_match('/(?:^|\s)db:(?!timeout:)[a-z]/i', implode(' ', $_SERVER['argv'] ?? []))) {
    set('default_timeout', fn () => (int)get('db_timeout'));
}

// run() reads the timeout from the host. Deployer keeps the host configuration between tasks, so a value
// read by an earlier task would win over the global default, and a raised value would leak into the
// following tasks. The timeout is therefore set on the host only while a db:* task runs.
// The loader requires the package files sorted by path, so this file comes after db_init.php.
task('db:timeout:raise', function () {
    $host = currentHost();
    if ($host->get('db_timeout_previous') === null) {
        $host->set('db_timeout_previous', get('default_timeout'));
    }
    $host->set('default_timeout', (int)get('db_timeout'));
})->hidden();

task('db:timeout:restore', function () {
    $host = currentHost();
    if ($host->get('db_timeout_previous') !== null) {
        $host->set('default_timeout', $host->get('db_timeout_previous'));
        $host->set('db_timeout_previous', null);
    }
})->hidden();

foreach (array_keys(Deployer::get()->tasks->all()) as $taskName) {
    if (str_starts_with($taskName, 'db:') && !str_starts_with($taskName, 'db:timeout:')) {
        before($taskName, 'db:timeout:raise');
        after($taskName, 'db:timeout:restore');
    }
}
