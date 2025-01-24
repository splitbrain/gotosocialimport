<?php

namespace App;

class Thread
{

    protected string $id;
    /** @var Status[] */
    protected array $statuses = [];

    /** @var Thread[] origin => Thread */
    static protected array $threads = [];

    public static function getInstance(Status $status, ?string $reply): Thread
    {
        if ($reply && isset(self::$threads[$reply])) {
            $instance = self::$threads[$reply];
            $instance->addStatus($status);
        } else {
            $instance = new Thread($status);
        }

        return $instance;
    }

    protected function __construct(Status $status)
    {
        $this->id = $status->getConfig()->getUlid()->generate(
            $status->getPublished()->getTimestamp() * 1000
        );
        $this->addStatus($status);
    }

    protected function addStatus(Status $status): void
    {
        $this->statuses[] = $status;
        self::$threads[$status->getOrigin()] = $this;
    }

    public function save(): void
    {
        $record = [
            'id' => $this->id,
        ];
        $this->statuses[0]->getConfig()->getDatabase()->saveRecord('threads', $record);

        foreach ($this->statuses as $status) {
            $record = [
                'thread_id' => $this->id,
                'status_id' => $status->getId(),
            ];
            $status->getConfig()->getDatabase()->saveRecord('thread_to_statuses', $record);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get another status in the same thread
     *
     * @param string $origin
     * @return Status|null
     */
    public function getOtherStatus(string $origin): ?Status
    {
        foreach ($this->statuses as $status) {
            if ($status->getOrigin() === $origin) {
                return $status;
            }
        }

        return null;
    }
}
