<?php

namespace Deployer;

// disable branch check because it fails in pipeline (confirmation message for branch override cannot be accepted)
task('deploy:check_branch')->disable();
