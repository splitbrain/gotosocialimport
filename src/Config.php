<?php


namespace App;

use Psr\Log\LoggerInterface;
use Ulid\Ulid;

class Config
{

    protected Database $database;
    protected string $instanceDir;
    protected string $mastodonJson;
    protected string $mastodonDir;
    protected string $username;
    protected string $userid;
    protected string $instance;
    protected LoggerInterface $logger;
    protected Ulid $ulid;
    protected bool $dryrun = true;
    protected bool $http;


    public function __construct(
        string          $mastodonDir,
        string          $instanceDir,
        string          $account,
        LoggerInterface $logger,
        bool            $dryrun = true,
        bool            $usehttp = false
    )
    {
        $this->ulid = new Ulid();
        $this->logger = $logger;
        $this->dryrun = $dryrun;
        $this->http = $usehttp;

        $this->initInstanceDir($instanceDir);
        $this->initMastodonDir($mastodonDir);
        $this->initAccount($account, $this->database);

        $this->logger->info(
            "Importing from {json} to {instancedir} for @{username}@{instance}",
            [
                'json' => $this->mastodonJson,
                'instancedir' => $this->instanceDir,
                'username' => $this->username,
                'instance' => $this->instance
            ]
        );
    }

    public function isDryrun(): bool
    {
        return $this->dryrun;
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getInstanceDir(): string
    {
        return $this->instanceDir;
    }

    public function getMastodonJson(): string
    {
        return $this->mastodonJson;
    }

    public function getMastodonDir(): string
    {
        return $this->mastodonDir;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserid(): string
    {
        return $this->userid;
    }

    public function getInstance(): string
    {
        return $this->instance;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getUlid(): Ulid
    {
        return $this->ulid;
    }

    public function getProto()
    {
        return $this->http ? 'http' : 'https';
    }

    /**
     * @throws Exception
     */
    protected function initInstanceDir(string $dir): void
    {
        if (!is_dir($dir)) {
            throw new \Exception('Directory not found');
        }

        if (!file_exists($dir . '/sqlite.db')) {
            throw new \Exception('Database not found in directory');
        }

        $this->database = new Database($dir . '/sqlite.db', $this->logger, $this->dryrun);
        $this->instanceDir = $dir;
    }

    /**
     * @throws Exception
     */
    protected function initMastodonDir(string $dir): void
    {
        if (!is_dir($dir)) {
            throw new \Exception("Directory '$dir' not found");
        }

        if (!file_exists($dir . '/outbox.json')) {
            throw new \Exception("outbox.json not found in directory '$dir'");
        }

        $this->mastodonJson = $dir . '/outbox.json';
        $this->mastodonDir = $dir;
    }

    /**
     * @throws Exception
     */
    protected function initAccount(string $account, Database $db): void
    {
        if (!preg_match('/^@(.+?)@(.+)$/', $account, $matches)) {
            throw new \Exception('Provide account in the form @user@instance');
        }
        array_shift($matches);
        [$username, $instance] = $matches;

        $result = $db->queryRecord('SELECT * FROM instances WHERE domain = ?', [$instance]);
        if (!$result) {
            throw new \Exception("Instance $instance not found in database");
        }

        $result = $db->queryRecord('SELECT * FROM accounts WHERE username = ?', [$username]);
        if (!$result) {
            throw new \Exception("Account @$username not found in database");
        }

        $this->userid = $result['id'];
        $this->username = $username;
        $this->instance = $instance;
    }
}
