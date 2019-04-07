<?php

final class rex_ydeploy
{
    private static $instance;

    private $deployed;

    private $host;
    private $stage;
    private $branch;
    private $commit;
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
     *
     * @return bool
     */
    public function isDeployed(): bool
    {
        return $this->deployed;
    }

    /**
     * Returns the host name (from deploy.php).
     *
     * @return null|string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Returns the stage name (from deploy.php).
     *
     * @return null|string
     */
    public function getStage()
    {
        return $this->stage;
    }

    /**
     * Returns the deployed branch.
     *
     * @return null|string
     */
    public function getBranch()
    {
        return $this->branch;
    }

    /**
     * Returns the hash of the deployed commit.
     *
     * @return null|string
     */
    public function getCommit()
    {
        return $this->commit;
    }

    /**
     * Returns timestamp of last deployment.
     *
     * @return null|DateTimeImmutable
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }
}
