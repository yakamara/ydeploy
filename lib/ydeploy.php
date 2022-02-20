<?php

final class rex_ydeploy
{
    /** @var self|null */
    private static $instance;

    /** @var bool */
    private $deployed;

    /** @var string|null */
    private $host;

    /** @var string|null */
    private $stage;

    /** @var string|null */
    private $branch;

    /** @var string|null */
    private $commit;

    /** @var DateTimeImmutable|null */
    private $timestamp;

    private function __construct()
    {
        $path = rex_path::addonData('ydeploy', 'info.json');

        if (!file_exists($path)) {
            $this->deployed = false;

            return;
        }

        $this->deployed = true;

        $info = rex_file::getCache($path);

        $this->host = $info['host'];
        $this->stage = $info['stage'];
        $this->branch = $info['branch'];
        $this->commit = $info['commit'];
        $this->timestamp = DateTimeImmutable::createFromFormat('U', $info['timestamp']);
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
