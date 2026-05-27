<?php

namespace Deployer;

use Deployer\Host\Host;
use Deployer\Host\Localhost;
use Deployer\Task\Context;
use RuntimeException;

use function count;
use function YDeploy\onHost;

desc('Pull database and media files from a remote host into the local installation');
task('pull', new class {
    private Host $source;
    private bool $skipDatabase;
    private bool $skipMedia;
    /** @var list<string> */
    private array $excludeTables;
    /** @var list<string> */
    private array $includeTables;

    public function __invoke(): void
    {
        $target = Context::get()->getHost();

        if (!$target instanceof Localhost) {
            throw new RuntimeException(
                'The "pull" task can only be executed on the "local" host. '
                . 'Run "dep pull" (without a host argument) or "dep pull local".',
            );
        }

        $this->skipDatabase = (bool) get('pull_skip_database', false);
        $this->skipMedia = (bool) get('pull_skip_media', false);
        $this->excludeTables = array_values((array) get('pull_exclude_tables', []));
        $this->includeTables = array_values((array) get('pull_include_tables', []));

        if ($this->skipDatabase && $this->skipMedia) {
            writeln('<comment>Nothing to do: both "pull_skip_database" and "pull_skip_media" are enabled.</comment>');
            return;
        }

        if ($this->includeTables && $this->excludeTables) {
            writeln('<comment>"pull_include_tables" is set; "pull_exclude_tables" will be ignored.</comment>');
            writeln('');
            $this->excludeTables = [];
        }

        $this->source = $this->chooseSource();

        $this->headline('Pull data from <fg=cyan>' . $this->source . '</fg=cyan> to <fg=cyan>local</fg=cyan>');

        $summary = [];
        if (!$this->skipDatabase) {
            $summary[] = $this->includeTables
                ? 'database (only: ' . implode(', ', $this->includeTables) . ')'
                : ($this->excludeTables
                    ? 'database (excluding: ' . implode(', ', $this->excludeTables) . ')'
                    : 'database (all tables)');
        }
        if (!$this->skipMedia) {
            $summary[] = 'media files';
        }
        writeln('Will sync: <info>' . implode(', ', $summary) . '</info>');
        writeln('');

        if (input()->isInteractive() && !askConfirmation('Continue?', true)) {
            writeln('<comment>Aborted.</comment>');
            return;
        }
        writeln('');

        if (!$this->skipDatabase) {
            $this->backupLocalDatabase();
            $this->copyDatabase();
        }

        if (!$this->skipMedia) {
            $this->copyMedia();
        }

        writeln('');
        writeln('<info>✔</info> Pull from <fg=cyan>' . $this->source . '</fg=cyan> finished.');
    }

    private function chooseSource(): Host
    {
        $hosts = Deployer::get()->hosts;
        $hostsArray = $hosts->all();
        unset($hostsArray['local']);
        $hostsArray = array_keys($hostsArray);

        if (0 === count($hostsArray)) {
            throw new RuntimeException('No remote hosts are configured in deploy.php.');
        }

        if (1 === count($hostsArray)) {
            $host = $hosts->get($hostsArray[0]);
            writeln('Source host: <fg=cyan>' . $host . '</fg=cyan>');
            writeln('');
            return $host;
        }

        writeln('From which host shall the data be pulled?');
        writeln('');
        $name = askChoice('Select source host:', $hostsArray);
        writeln('');

        return $hosts->get($name);
    }

    private function backupLocalDatabase(): void
    {
        $this->headline('Backup local database');

        $path = get('data_dir') . '/addons/ydeploy/backup-' . date('YmdHis') . '.sql';

        run('mkdir -p ' . escapeshellarg(dirname($path)));
        run('{{bin/php}} {{bin/console}} db:connection-options | xargs {{bin/mysqldump}} > ' . escapeshellarg($path));

        writeln('Local backup written to: <info>' . $path . '</info>');
        $this->ok();
    }

    private function copyDatabase(): void
    {
        $this->headline('Copy database from <fg=cyan>' . $this->source . '</fg=cyan> to local');

        $path = get('data_dir') . '/addons/ydeploy/pull-' . date('YmdHis') . '.sql';

        // export source database
        onHost($this->source, function () use ($path) {
            cd('{{current_path}}');
            run('mkdir -p ' . escapeshellarg(dirname($path)));

            if ($this->includeTables) {
                $tableArgs = '';
                foreach ($this->includeTables as $table) {
                    $tableArgs .= ' ' . escapeshellarg($table);
                }
                // xargs appends the connection-options (incl. db name) as args; sh -c reorders so tables come after
                run('{{bin/php}} {{bin/console}} db:connection-options | xargs sh -c \'exec {{bin/mysqldump}} "$@"' . $tableArgs . '\' sh > ' . escapeshellarg($path));
            } else {
                $ignoreFlags = '';
                if ($this->excludeTables) {
                    $dbName = trim(run('{{bin/php}} {{bin/console}} db:connection-options | awk \'{print $NF}\''));
                    if ('' === $dbName) {
                        throw new RuntimeException('Could not determine database name on host ' . $this->source . '.');
                    }
                    foreach ($this->excludeTables as $table) {
                        $ignoreFlags .= ' --ignore-table=' . escapeshellarg($dbName . '.' . $table);
                    }
                }
                run('{{bin/php}} {{bin/console}} db:connection-options | xargs {{bin/mysqldump}}' . $ignoreFlags . ' > ' . escapeshellarg($path));
            }

            try {
                download("{{current_path}}/$path", $path);
            } finally {
                run('rm -f ' . escapeshellarg($path));
            }
        });

        // import the dump locally
        try {
            run('{{bin/php}} {{bin/console}} db:connection-options | xargs sh -c \'exec {{bin/mysql}} "$@" < ' . escapeshellcmd(escapeshellarg($path)) . '\' sh');
        } finally {
            if (file_exists(getcwd() . '/' . $path)) {
                unlink(getcwd() . '/' . $path);
            }
        }

        $this->ok();
    }

    private function copyMedia(): void
    {
        $this->headline('Copy media files from <fg=cyan>' . $this->source . '</fg=cyan> to local');

        $path = get('data_dir') . '/addons/ydeploy/media-' . date('YmdHis') . '.tar.gz';

        onHost($this->source, static function () use ($path) {
            run('mkdir -p ' . escapeshellarg(dirname($path)));
            run('COPYFILE_DISABLE=1 tar -zcf ' . escapeshellarg($path) . ' -C {{media_dir}} .');

            try {
                download("{{current_path}}/$path", $path);
            } finally {
                run('rm -f ' . escapeshellarg($path));
            }
        });

        try {
            run('mkdir -p {{media_dir}}');
            run('tar -zxf ' . escapeshellarg($path) . ' -C {{media_dir}}/');
        } finally {
            if (file_exists(getcwd() . '/' . $path)) {
                unlink(getcwd() . '/' . $path);
            }
        }

        $this->ok();
    }

    private function headline(string $headline): void
    {
        writeln('<comment>' . $headline . '</comment>');
        writeln('');
    }

    private function ok(): void
    private function ok(): void
    {
        writeln('<info>✔</info> Ok');
        writeln('');
    }
})->once();
