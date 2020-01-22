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
           $this->interProcessShareMemory = new InterProcessShareMemory("BOBBY_PHP_WORKER_" . $this->getPid());
        }
        return $this->interProcessShareMemory;
    }

    public function isUsing(): bool
    {
        return (bool)$this->openInterProcessShareMemory()->has(static::WORKER_USING_KEY);
    }

    public function use()
    {
        return $this->openInterProcessShareMemory()->set(static::WORKER_USING_KEY, 1);
    }

    public function free()
    {
        return $this->openInterProcessShareMemory()->delete(static::WORKER_USING_KEY);
    }

    public function __toString()
    {
        return $this->getWorkerId();
    }
}