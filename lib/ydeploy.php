<?php

namespace Alexplusde\Deploy;

use DateTimeImmutable;
use DateTimeZone;
use rex;
use rex_addon;
use rex_file;
use rex_path;
use rex_sql;
use rex_sql_exception;
use rex_type;

final class YDeploy
{
    private static ?self $instance = null;

    private bool $deployed;
    private ?string $host = null;
    private ?string $stage = null;
    private ?string $branch = null;
    private ?string $commit = null;
    private ?DateTimeImmutable $timestamp = null;

    private function __construct()
    {
        $path = rex_path::addonData('ydeploy', 'info.json');

        if (!file_exists($path)) {
            $this->deployed = false;

            return;
        }

        $this->deployed = true;

        $info = rex_file::getCache($path);

        $this->host = rex_type::string($info['host']);
        $this->stage = rex_type::nullOrString($info['stage']);
        $this->branch = rex_type::string($info['branch']);
        $this->commit = rex_type::string($info['commit']);
        $this->timestamp = rex_type::instanceOf(DateTimeImmutable::createFromFormat('U', $info['timestamp']), DateTimeImmutable::class)
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    public static function factory(): self
    {
        if (self::$instance) {
            return self::$instance;
        }

        return self::$instance = new self();
    }

    /**
     * Returns whether this a deployed (`true`) or local (`false`) instance.
     */
    public function isDeployed(): bool
    {
        return $this->deployed;
    }

    /**
     * Returns the host name (from deploy.php).
     */
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * Returns the stage name (from deploy.php).
     */
    public function getStage(): ?string
    {
        return $this->stage;
    }

    /**
     * Returns the deployed branch.
     */
    public function getBranch(): ?string
    {
        return $this->branch;
    }

    /**
     * Returns the hash of the deployed commit.
     */
    public function getCommit(): ?string
    {
        return $this->commit;
    }

    /**
     * Returns timestamp of last deployment.
     */
    public function getTimestamp(): ?DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Returns all migrations that have not yet been executed.
     *
     * The returned array is keyed by the migration timestamp (`Y-m-d H:i:s.u`)
     * and contains the absolute file path of the migration as value.
     *
     * @return array<string, string> Map of `timestamp => path`
     */
    public static function getPendingMigrations(): array
    {
        $addon = rex_addon::get('ydeploy');
        $migrationTable = rex::getTable('ydeploy_migration');

        try {
            $sql = rex_sql::factory();
            $migrated = $sql->getArray('SELECT `timestamp` FROM ' . $sql->escapeIdentifier($migrationTable));
            $migrated = array_column($migrated, 'timestamp', 'timestamp');
        } catch (rex_sql_exception) {
            return [];
        }

        $glob = glob($addon->getDataPath('migrations/*-*-* *.*.php')) ?: [];
        $pending = [];

        foreach ($glob as $path) {
            $timestamp = substr(basename($path), 0, -4);

            if (!preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2})[-:](\d{2})[-:](\d{2}\.\d+)$/', $timestamp, $match)) {
                continue;
            }

            $timestamp = $match[1] . ' ' . $match[2] . ':' . $match[3] . ':' . $match[4];

            if (!isset($migrated[$timestamp])) {
                $pending[$timestamp] = $path;
            }
        }

        ksort($pending);

        return $pending;
    }
}

\class_alias(YDeploy::class, 'rex_ydeploy');
