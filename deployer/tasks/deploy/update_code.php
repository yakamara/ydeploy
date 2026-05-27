<?php

namespace Deployer;

use Deployer\Exception\ConfigurationException;

/*
 * Overrides the default `deploy:update_code` task from `recipe/common.php`
 * to always clone submodules (including private repos via SSH) and to keep
 * the local mirror up to date so that the `.gitmodules` file reflects the
 * current state of the repository.
 *
 * The previous workaround required setting
 * `set('update_code_strategy', 'clone_plus_submodules');` in the project's
 * `deploy.php`. This is no longer necessary – a deprecation notice is
 * emitted when the option is still set.
 */
desc('Updates code with submodules');
task('deploy:update_code', static function () {
    $strategy = get('update_code_strategy');
    if ('archive' !== $strategy) {
        writeln('');
        writeln('<comment>The "update_code_strategy" option is no longer required and can be removed from the project\'s deploy.php.</comment>');
        writeln('<comment>Submodules are now always fetched as part of "deploy:update_code".</comment>');
        writeln('');
    }

    $git = get('bin/git');
    $repository = (string) get('repository');
    $target = get('target');

    if ('' === $repository) {
        throw new ConfigurationException("Missing 'repository' configuration.");
    }

    $targetWithDir = $target;
    if (!empty(get('sub_directory'))) {
        $targetWithDir .= ':{{sub_directory}}';
    }

    $bare = parse('{{deploy_path}}/.dep/repo');
    $env = [
        'GIT_TERMINAL_PROMPT' => '0',
        'GIT_SSH_COMMAND' => get('git_ssh_command'),
    ];

    start:
    // Clone the repository to a bare repo.
    run("[ -d $bare ] || mkdir -p $bare");
    run("[ -f $bare/HEAD ] || $git clone --mirror --recurse-submodules $repository $bare 2>&1", env: $env);

    cd($bare);

    // If remote url changed, drop `.dep/repo` and reinstall.
    if (run("$git config --get remote.origin.url") !== $repository) {
        cd('{{deploy_path}}');
        run("rm -rf $bare");
        goto start;
    }

    run("$git remote update 2>&1", env: $env);

    // Copy to release_path.
    cd('{{release_path}}');
    run("$git clone -l $bare .");
    run("$git remote set-url origin $repository", env: $env);
    run("$git checkout --force $target");
    run("$git submodule init", env: $env);
    run("$git submodule update --recursive --remote", env: $env);

    // Save git revision in REVISION file.
    $rev = escapeshellarg(run("$git rev-list $target -1"));
    run("echo $rev > {{release_path}}/REVISION");
});
