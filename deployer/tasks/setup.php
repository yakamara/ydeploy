<?php

namespace Deployer;

use Deployer\Exception\RunException;
use Deployer\Host\Host;
use Deployer\Host\Localhost;
use Deployer\Task\Context;
use Symfony\Component\Yaml\Yaml;

use function count;
use function is_string;
use function YDeploy\downloadContent;
use function YDeploy\onHost;
use function YDeploy\uploadContent;

desc('Setup redaxo instance');
task('setup', new class() {
    private Host $target;
    private bool $local;
    private Host|string $source;
    private string $mysqlOptions;
    private ?string $server = null;

    public function __invoke(): void
    {
        $this->target = Context::get()->getHost();
        $this->local = $this->target instanceof Localhost;

        if (!$this->local) {
            cd('{{release_path}}');

            if (test('[ -f {{data_dir}}/core/config.yml ]')) {
                return;
            }
        }

        writeln('');

        $this->mysqlOptions = get('data_dir') . '/addons/ydeploy/mysql-options';

        $this->source = $this->chooseSource();

        try {
            $this->setConfigYml();
            $this->copyDatabase();
            $this->configureDeveloper();
            $this->replaceYrewriteDomains();
            $this->copyMedia();
        } finally {
            run('rm -f ' . escapeshellarg($this->mysqlOptions));
        }
    }

    private function chooseSource(): Host|string
    {
        $this->headline('Setup <fg=cyan>{{hostname}}</fg=cyan>');

        $hosts = Deployer::get()->hosts;

        if ($this->local && count($hosts) < 2) {
            writeln('The data must be imported from an existing dump file.');

            return $this->chooseSourceFile();
        }

        if ($this->local) {
            writeln('The data can be imported from one of the hosts, or from dump file.');
            writeln('');

            if (!askConfirmation('Import from host', true)) {
                return $this->chooseSourceFile();
            }

            writeln('');
        }

        $hostsArray = $hosts->all();
        unset($hostsArray[$this->target->getAlias()]);
        $hostsArray = array_keys($hostsArray);

        if (count($hostsArray) < 2) {
            $host = $hosts->get($hostsArray[0]);

            writeln("The host <fg=cyan>{{hostname}}</fg=cyan> will be initialized by data from <fg=cyan>{$host}</fg=cyan>.");
            writeln('');

            return $host;
        }

        writeln('The data from which host shall be used for initializing <fg=cyan>{{hostname}}</fg=cyan>?');
        writeln('');

        $host = askChoice('Select source host:', $hostsArray);

        writeln('');

        return $hosts->get($host);
    }

    private function chooseSourceFile(): string
    {
        writeln('');

        while (true) {
            $file = ask('Dump file', getcwd() . '/dump.sql');
            writeln('');

            if (file_exists($file)) {
                return $file;
            }

            writeln('<error>The file does not exist: ' . $file . '</error>');
            writeln('');
        }
    }

    private function setConfigYml(): void
    {
        $this->headline('Create config.yml for <fg=cyan>{{hostname}}</fg=cyan>');

        if ($this->source instanceof Localhost) {
            $config = file_get_contents(getcwd() . '/' . get('data_dir') . '/core/config.yml');
        } elseif (is_string($this->source)) {
            $config = file_get_contents(getcwd() . '/' . get('src_dir') . '/core/default.config.yml');
        } else {
            $config = onHost($this->source, static function () {
                return downloadContent('{{data_dir}}/core/config.yml');
            });
        }

        $config = Yaml::parse($config);

        $config['setup'] = false;
        $config['debug'] = $this->local;
        $config['instname'] = 'rex' . date('YmdHis');

        $config['server'] = ask('Server:', get('url'));
        $config['servername'] = ask('Server name:', $config['servername']);
        $config['error_email'] = ask('Error email:', $config['error_email']);

        $this->server = $config['server'];

        writeln('');

        $db = $config['db'][1];
        $db['host'] = 'localhost';
        $db['name'] = null;
        $db['login'] = 'root';
        $db['password'] = null;

        $dbValid = false;

        do {
            $db['host'] = ask('Database host:', $db['host']);
            $db['name'] = ask('Database name:', $db['name']);
            $db['login'] = ask('Database user:', $db['login']);
            $db['password'] = askHiddenResponse('Database password:');

            uploadContent($this->mysqlOptions, implode("\n", [
                '--host=' . escapeshellarg($db['host']),
                '--user=' . escapeshellarg($db['login']),
                '--password=' . escapeshellarg($db['password']),
                $this->local ? '' : escapeshellarg($db['name']),
            ]));

            try {
                $cmd = $this->local ? 'CREATE DATABASE IF NOT EXISTS ' . escapeshellcmd($db['name']) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' : '';
                run('< ' . escapeshellarg($this->mysqlOptions) . ' xargs {{bin/mysql}} -e "' . $cmd . ';"');
                $dbValid = true;
            } catch (RunException $e) {
                writeln('');
                writeln('<error>Could not ' . ($this->local ? 'create/' : '') . 'connect to database: ' . trim($e->getErrorOutput()) . '</error>');
                writeln('');
            }
        } while (!$dbValid);

        if ($this->local) {
            file_put_contents($this->mysqlOptions, implode("\n", [
                '--host=' . escapeshellarg($db['host']),
                '--user=' . escapeshellarg($db['login']),
                '--password=' . escapeshellarg($db['password']),
                escapeshellarg($db['name']),
            ]));
        }

        $config['db'][1] = $db;

        $config = Yaml::dump($config, 3);

        run('mkdir -p {{data_dir}}/core');
        uploadContent('{{data_dir}}/core/config.yml', $config);

        writeln('');
        $this->ok();
    }

    private function copyDatabase(): void
    {
        if (is_string($this->source)) {
            $this->headline('Import database dump');

            $path = $this->source;
        } else {
            $this->headline("Copy database from <fg=cyan>{$this->source}</fg=cyan> to <fg=cyan>{{hostname}}</fg=cyan>");

            $path = get('data_dir') . '/addons/ydeploy/' . date('YmdHis') . '.sql';

            // export source database
            onHost($this->source, function () use ($path) {
                cd('{{current_path}}');
                run('{{bin/php}} {{bin/console}} db:connection-options | xargs {{bin/mysqldump}} > ' . escapeshellarg($path));

                if ($this->source instanceof Localhost) {
                    return;
                }

                download("{{current_path}}/$path", $path);
                run('rm -f ' . escapeshellarg($path));
            });
        }

        // upload and import the dump
        if (!$this->local) {
            upload($path, "{{release_path}}/$path");
        }
        run('< ' . escapeshellarg($this->mysqlOptions) . ' xargs sh -c \'{{bin/mysql}} "$0" "$@" < ' . escapeshellcmd(escapeshellarg($path)) . '\'');

        if (!$this->local) {
            run('rm -f ' . escapeshellarg($path));
        }
        unlink(getcwd() . '/' . $path);

        $this->ok();
    }

    private function configureDeveloper(): void
    {
        $this->headline('Configure developer addon for ' . ($this->local ? 'local' : 'production') . ' usage');

        if ($this->local) {
            run('< ' . escapeshellarg($this->mysqlOptions) . ' xargs {{bin/mysql}} -e "UPDATE rex_config SET value = \"true\" WHERE namespace=\"developer\" AND \`key\` IN (\"sync_frontend\", \"sync_backend\", \"rename\", \"dir_suffix\", \"delete\")"');
        } else {
            run('< ' . escapeshellarg($this->mysqlOptions) . ' xargs {{bin/mysql}} -e "UPDATE rex_config SET value = \"false\" WHERE namespace=\"developer\" AND \`key\` NOT IN (\"templates\", \"modules\", \"actions\", \"items\")"');
        }

        $this->ok();
    }

    private function replaceYrewriteDomains(): void
    {
        try {
            $data = run('< ' . escapeshellarg($this->mysqlOptions) . ' xargs {{bin/mysql}} --silent --raw --skip-column-names -e "SELECT id, domain FROM rex_yrewrite_domain"');
            $data = trim($data);
        } catch (RunException $exception) {
            if (str_contains($exception->getMessage(), 'ERROR 1146')) {
                // Table does not exist (yrewite not activated)
                return;
            }

            throw $exception;
        }

        if (!$data) {
            return;
        }

        $this->headline('Replace yrewrite domains');

        foreach (explode("\n", $data) as $line) {
            [$id, $domain] = explode("\t", $line, 2);
            $id = (int) $id;
            $domain = ask($domain . ':', $this->server ?: get('url'));
            run('< ' . escapeshellarg($this->mysqlOptions) . ' xargs {{bin/mysql}} -e "UPDATE rex_yrewrite_domain SET domain = \"' . addslashes($domain) . '\" WHERE id = ' . $id . '"');
        }

        writeln('');
        $this->ok();
    }

    private function copyMedia(): void
    {
        if (is_string($this->source)) {
            return;
        }

        $this->headline("Copy media files from <fg=cyan>{$this->source}</fg=cyan> to <fg=cyan>{{hostname}}</fg=cyan>");

        $path = get('data_dir') . '/addons/ydeploy/media_' . date('YmdHis') . '.tar.gz';

        // create source archive
        onHost($this->source, static function () use ($path) {
            run('COPYFILE_DISABLE=1 tar -zcvf ' . escapeshellarg($path) . ' -C {{media_dir}} .');

            if (Context::get()->getHost() instanceof Localhost) {
                return;
            }

            try {
                download("{{current_path}}/$path", $path);
            } finally {
                run('rm -f ' . escapeshellarg($path));
            }
        });

        try {
            upload($path, "{{release_path}}/$path");
            run('mkdir -p {{media_dir}}');
            run('tar -zxvf ' . escapeshellarg($path) . ' -C {{media_dir}}/');
        } finally {
            unlink($path);
            run('rm -f ' . escapeshellarg($path));
        }

        $this->ok();
    }

    private function headline(string $headline): void
    {
        writeln('<comment>' . $headline . '</comment>');
        writeln('');
    }

    private function ok(): void
    {
        writeln('<info>✔</info> Ok');
        writeln('');
    }
});
