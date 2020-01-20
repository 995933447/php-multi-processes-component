<?php
namespace Bobby\MultiProcesses;

class Worker extends Process
{
    protected $workerId;

    protected $interProcessShareMemory;

    const WORKER_USING_KEY = 1;

    public function __construct(int $workerId, callable $callback, bool $isDaemon = false)
    {
        $this->workerId = $workerId;
        parent::__construct($callback, $isDaemon);
    }

    public function getWorkerId(): int
    {
        return $this->workerId;
    }

    public function setWorkId(int $workerId)
    {
        $this->workerId = $workerId;
    }

    protected function openInterProcessShareMemory()
    {
        if (!$this->interProcessShareMemory) {
           $this->interProcessShareMemory = new InterProcessShareMemory("PHP_WORKER_" . $this->getWorkerId());
        }
        return $this->interProcessShareMemory;
    }

    public function isUsing(): bool
    {
        return (bool)$this->openInterProcessShareMemory()->get(static::WORKER_USING_KEY);
    }

    public function asUsing()
    {
        return $this->openInterProcessShareMemory()->set(static::WORKER_USING_KEY, true);
    }

    public function finish()
    {
        return $this->openInterProcessShareMemory()->set(static::WORKER_USING_KEY, false);
    }
}