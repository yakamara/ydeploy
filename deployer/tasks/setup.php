<?php

namespace Deployer;

use Deployer\Exception\RuntimeException;
use Deployer\Host\Host;
use Deployer\Host\Localhost;
use Deployer\Task\Context;
use Symfony\Component\Yaml\Yaml;
use function YDeploy\{downloadContent, uploadContent, onHost};

desc('Setup redaxo instance');
task('setup', new class {
    private $mysqlOptions;

    /** @var Host */
    private $source;

    public function __invoke()
    {
        cd('{{release_path}}');

        if (test('[ -f {{data_dir}}/core/config.yml ]')) {
            return;
        }

        writeln('');

        $this->mysqlOptions = get('data_dir').'/addons/ydeploy/mysql-options';

        $this->source = $this->chooseSource();

        $this->setConfigYml();
        $this->copyDatabase();
        $this->configureDeveloper();
        $this->replaceYrewriteDomains();
        $this->copyMedia();

        run('rm -f '.escapeshellarg($this->mysqlOptions));
    }

    private function chooseSource(): Host
    {
        $this->headline('Setup <fg=cyan>{{hostname}}</fg=cyan>');

        $hosts = Deployer::get()->hosts;
        $localhost = new Localhost('local');

        if (count($hosts) < 2) {
            writeln("The host <fg=cyan>{{hostname}}</fg=cyan> will be initialized by data from <fg=cyan>{$localhost}</fg=cyan>.");
            writeln('');

            return $localhost;
        }

        $hosts->set($localhost->getHostname(), $localhost);

        $hostsArray = $hosts->toArray();
        unset($hostsArray[Context::get()->getHost()->getHostname()]);
        $hostsArray = array_values($hostsArray);

        writeln('The data from which host shall be used for initializing <fg=cyan>{{hostname}}</fg=cyan>?');
        writeln('');

        $host = askChoice('Select source host:', $hostsArray);

        writeln('');

        return $hosts->get($host);
    }

    private function setConfigYml()
    {
        $this->headline('Create config.yml for <fg=cyan>{{hostname}}</fg=cyan>');

        if ($this->source instanceof Localhost) {
            $config = file_get_contents(getcwd().'/'.get('data_dir').'/core/config.yml');
        } else {
            $config = onHost($this->source, function () {
                return downloadContent('{{data_dir}}/core/config.yml');
            });
        }

        $config = Yaml::parse($config);

        $config['setup'] = false;
        $config['debug'] = false;
        $config['instname'] = 'rex'.date('YmdHis');

        $config['server'] = ask('Server:', get('url'));
        $config['servername'] = ask('Server name:', $config['servername']);
        $config['error_email'] = ask('Error email:', $config['error_email']);

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
                '--host='.escapeshellarg($db['host']),
                '--user='.escapeshellarg($db['login']),
                '--password='.escapeshellarg($db['password']),
                escapeshellarg($db['name']),
            ]));

            try {
                run('< '.escapeshellarg($this->mysqlOptions).' xargs {{bin/mysql}} -e ";"');
                $dbValid = true;
            } catch (RuntimeException $e) {
                writeln('');
                writeln('<error>Could not connect to database: '.trim($e->getErrorOutput()).'</error>');
                writeln('');
            }
        } while (!$dbValid);

        $config['db'][1] = $db;

        $config = Yaml::dump($config, 3);

        run('mkdir -p {{data_dir}}/core');
        uploadContent('{{data_dir}}/core/config.yml', $config);

        writeln('');
        $this->ok();
    }

    private function copyDatabase()
    {
        $this->headline("Copy database from <fg=cyan>{$this->source}</fg=cyan> to <fg=cyan>{{hostname}}</fg=cyan>");

        $path = get('data_dir').'/addons/ydeploy/'.date('YmdHis').'.sql';

        // export source database
        onHost($this->source, function () use ($path) {
            run('{{bin/console}} db:connection-options | xargs {{bin/mysqldump}} > '.escapeshellarg($path));

            if (Context::get()->getHost() instanceof Localhost) {
                return;
            }

            download("{{release_path}}/$path", $path);
            run('rm -f '.escapeshellarg($path));
        });

        // upload and import the dump
        upload($path, "{{release_path}}/$path");
        run('< '.escapeshellarg($this->mysqlOptions).' xargs sh -c \'{{bin/mysql}} "$0" "$@" < '.escapeshellcmd(escapeshellarg($path)).'\'');

        run('rm -f '.escapeshellarg($path));
        unlink(getcwd().'/'.$path);

        $this->ok();
    }

    private function configureDeveloper()
    {
        $this->headline('Configure developer addon for production usage');

        run('< '.escapeshellarg($this->mysqlOptions).' xargs {{bin/mysql}} -e "UPDATE rex_config SET value = \"false\" WHERE namespace=\"developer\" AND \`key\` NOT IN (\"templates\", \"modules\", \"actions\", \"items\")"');

        $this->ok();
    }

    private function replaceYrewriteDomains()
    {
        try {
            $data = run('< '.escapeshellarg($this->mysqlOptions).' xargs {{bin/mysql}} --silent --raw --skip-column-names -e "SELECT id, domain FROM rex_yrewrite_domain"');
            $data = trim($data);
        } catch (RuntimeException $exception) {
            if (false !== strpos($exception->getMessage(), 'ERROR 1146')) {
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
            list($id, $domain) = explode("\t", $line, 2);
            $id = (int) $id;
            $domain = ask($domain.':', get('url'));
            run('< '.escapeshellarg($this->mysqlOptions).' xargs {{bin/mysql}} -e "UPDATE rex_yrewrite_domain SET domain = \"'.addslashes($domain).'\" WHERE id = '.$id.'"');
        }

        writeln('');
        $this->ok();
    }

    private function copyMedia()
    {
        $this->headline("Copy media files from <fg=cyan>{$this->source}</fg=cyan> to <fg=cyan>{{hostname}}</fg=cyan>");

        $path = get('data_dir').'/addons/ydeploy/media_'.date('YmdHis').'.tar.gz';

        // create source archive
        onHost($this->source, function () use ($path) {
            run('tar -zcvf '.escapeshellarg($path).' -C {{media_dir}} .');

            if (Context::get()->getHost() instanceof Localhost) {
                return;
            }

            try {
                download("{{release_path}}/$path", $path);
            } finally {
                run('rm -f '.escapeshellarg($path));
            }
        });

        try {
            upload($path, "{{release_path}}/$path");
            run('tar -zxvf '.escapeshellarg($path).' -C {{media_dir}}/');
        } finally {
            unlink($path);
            run('rm -f '.escapeshellarg($path));
        }

        $this->ok();
    }

    private function headline(string $headline)
    {
        writeln('<comment>'.$headline.'</comment>');
        writeln('');
    }

    private function ok()
    {
        writeln('<info>✔</info> Ok');
        writeln('');
    }
});
