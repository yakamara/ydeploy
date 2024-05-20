<?php

final class rex_ydeploy
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
}
