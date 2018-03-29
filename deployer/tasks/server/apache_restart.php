<?php

namespace Deployer;

set('bin/apachectl', function () {
    return locateBinaryPath('apachectl');
});

desc('Restart apache');
task('server:apache:restart', function () {
    run('{{bin/apachectl}} configtest && {{bin/apachectl}} graceful');
});
