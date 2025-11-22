<?php

namespace Deployer;

// disable branch check because it fails in pipeline
task('deploy:check_branch_local')->disable();
