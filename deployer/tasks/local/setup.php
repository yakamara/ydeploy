<?php

namespace Deployer;

use Deployer\Host\Host;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Yaml\Yaml;
use function count;
use function YDeploy\downloadContent;
use function YDeploy\onHost;
use function YDeploy\uploadContent;

desc('Setup local redaxo instance');
task('local:setup', new class() {
    private $mysqlOptions;

    /** @var null|Host */
    private $source;

    /** @var null|string */
    private $sourceFile;

    /** @var null|string */
    private $server;

    public function __invoke(): void
    {
        writeln('');

        $this->mysqlOptions = get('data_dir').'/addons/ydeploy/mysql-options';

        $this->chooseSource();

        try {
            $this->setConfigYml();
            $this->copyDatabase();
            $this->configureDeveloper();
            $this->replaceYrewriteDomains();
            $this->copyMedia();
        } finally {
            run('rm -f '.escapeshellarg($this->mysqlOptions));
        }
    }

    private function chooseSource(): void
    {
        $this->headline('Setup <fg=cyan>local</fg=cyan> instance');

        $hosts = Deployer::get()->hosts;

        if (count($hosts) > 0) {
            writeln('The data can be imported from one of the hosts, or from dump file.');
            writeln('');

            $fromHost = askConfirmation('Import from host');
            writeln('');
        } else {
            writeln('The data must be imported from an existing dump file.');
            writeln('');

            $fromHost = false;
        }

        if (!$fromHost) {
            do {
                $file = ask('Dump file', getcwd().'/dump.sql');

                if (file_exists($file)) {
                    $this->sourceFile = $file;
                } else {
                    writeln('');
                    writeln('<error>The file does not exist: '.$file.'</error>');
                    writeln('');
                }
            } while (!$this->sourceFile);

            writeln('');

            return;
        }

        if (1 === count($hosts)) {
            $this->source = $hosts->first();

            return;
        }

        $hostsArray = $hosts->all();
        $hostsArray = array_keys($hostsArray);

        $host = askChoice('Select source host:', $hostsArray);

        writeln('');

        $this->source = $hosts->get($host);
    }

    private function setConfigYml(): void
    {
        $this->headline('Create <fg=cyan>local</fg=cyan> config.yml');

        if ($this->source) {
            $config = onHost($this->source, static function () {
                return downloadContent('{{data_dir}}/core/config.yml');
            });
        } else {
            $config = file_get_contents(getcwd().'/'.get('src_dir').'/core/default.config.yml');
        }

        $config = Yaml::parse($config);

        $config['setup'] = false;
        $config['debug'] = true;
        $config['instname'] = 'rex'.date('YmdHis');

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

            file_put_contents($this->mysqlOptions, implode("\n", [
                '--host='.escapeshellarg($db['host']),
                '--user='.escapeshellarg($db['login']),
                '--password='.escapeshellarg($db['password']),
            ]));

            try {
                run('< '.escapeshellarg($this->mysqlOptions).' xargs {{bin/mysql}} -e "CREATE DATABASE IF NOT EXISTS '.escapeshellcmd($db['name']).' CHARACTER SET utf8 COLLATE utf8_general_ci"');
                $dbValid = true;
            } catch (ProcessFailedException $e) {
                writeln('');
                writeln('<error>Could not create/connect database: '.trim($e->getProcess()->getErrorOutput()).'</error>');
                writeln('');
            }
        } while (!$dbValid);

        file_put_contents($this->mysqlOptions, implode("\n", [
            '--host='.escapeshellarg($db['host']),
            '--user='.escapeshellarg($db['login']),
            '--password='.escapeshellarg($db['password']),
            escapeshellarg($db['name']),
        ]));

        $config['db'][1] = $db;

        $config = Yaml::dump($config, 3);

        run('mkdir -p {{data_dir}}/core');
        uploadContent('{{data_dir}}/core/config.yml', $config);

        writeln('');
        $this->ok();
    }

    private function copyDatabase(): void
    {
        if ($this->source) {
            $this->headline("Copy database from <fg=cyan>{$this->source}</fg=cyan> to <fg=cyan>local</fg=cyan>");

            $path = get('data_dir').'/addons/ydeploy/'.date('YmdHis').'.sql';

            // export source database
            onHost($this->source, static function () use ($path) {
                run('{{bin/console}} db:connection-options | xargs {{bin/mysqldump}} > '.escapeshellarg($path));
                download("{{release_path}}/$path", $path);
                run('rm -f '.escapeshellarg($path));
            });
        } else {
            $this->headline('Import database dump');

            $path = $this->sourceFile;
        }

        // import the dump
        run('< '.escapeshellarg($this->mysqlOptions).' xargs sh -c \'{{bin/mysql}} "$0" "$@" < '.escapeshellcmd(escapeshellarg($path)).'\'');

        run('rm -f '.escapeshellarg($path));

        $this->ok();
    }

    private function configureDeveloper(): void
    {
        $this->headline('Configure developer addon for local usage');

        run('< '.escapeshellarg($this->mysqlOptions).' xargs {{bin/mysql}} -e "UPDATE rex_config SET value = \"true\" WHERE namespace=\"developer\" AND \`key\` IN (\"sync_frontend\", \"sync_backend\", \"rename\", \"dir_suffix\", \"delete\")"');

        $this->ok();
    }

    private function replaceYrewriteDomains(): void
    {
        try {
            $data = run('< '.escapeshellarg($this->mysqlOptions).' xargs {{bin/mysql}} --silent --raw --skip-column-names -e "SELECT id, domain FROM rex_yrewrite_domain"');
            $data = trim($data);
        } catch (ProcessFailedException $exception) {
            if (false !== strpos($exception->getProcess()->getErrorOutput(), 'ERROR 1146')) {
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
            $domain = ask($domain.':', $this->server ?: get('url'));
            run('< '.escapeshellarg($this->mysqlOptions).' xargs {{bin/mysql}} -e "UPDATE rex_yrewrite_domain SET domain = \"'.addslashes($domain).'\" WHERE id = '.$id.'"');
        }

        writeln('');
        $this->ok();
    }

    private function copyMedia(): void
    {
        if (!$this->source) {
            return;
        }

        $this->headline("Copy media files from <fg=cyan>{$this->source}</fg=cyan> to <fg=cyan>local</fg=cyan>");

        $path = get('data_dir').'/addons/ydeploy/media_'.date('YmdHis').'.tar.gz';

        // create source archive
        onHost($this->source, static function () use ($path) {
            run('tar -zcvf '.escapeshellarg($path).' -C {{media_dir}} .');

            try {
                download("{{release_path}}/$path", $path);
            } finally {
                run('rm -f '.escapeshellarg($path));
            }
        });

        try {
            run('mkdir -p {{media_dir}}');
            run('tar -zxvf '.escapeshellarg($path).' -C {{media_dir}}/');
        } finally {
            run('rm -f '.escapeshellarg($path));
        }

        $this->ok();
    }

    private function headline(string $headline): void
    {
        writeln('<comment>'.$headline.'</comment>');
        writeln('');
    }

    private function ok(): void
    {
        writeln('<info>✔</info> Ok');
        writeln('');
    }
})->local()->shallow();
