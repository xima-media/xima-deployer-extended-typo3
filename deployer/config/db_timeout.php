<?php

namespace Deployer;

// The database tasks of sourcebroker/deployer-extended-database (db:copy, db:import, ...) call each
// other as nested Deployer processes, on the CI runner and on the remote host. Every one of these
// processes caps its commands with default_timeout (300 seconds), which is too short to copy a
// large database, e.g. when db:init fills a new feature branch environment.
// Every Deployer process that runs a db:* task therefore uses db_timeout instead. 0 disables the limit.
set('db_timeout', 1800);

if (preg_match('/(?:^|\s)db:[a-z]/i', implode(' ', $_SERVER['argv'] ?? []))) {
    set('default_timeout', fn () => (int)get('db_timeout'));
}
