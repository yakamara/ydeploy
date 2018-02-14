<?php

namespace Deployer;

require 'recipe/common.php';

require __DIR__.'/deployer/config.php';

require __DIR__.'/deployer/tasks/build/assets.php';
require __DIR__.'/deployer/tasks/build/info.php';

require __DIR__.'/deployer/tasks/database/migration.php';

require __DIR__.'/deployer/tasks/build.php';
require __DIR__.'/deployer/tasks/deploy.php';
require __DIR__.'/deployer/tasks/release.php';
require __DIR__.'/deployer/tasks/upload.php';
