<?php
namespace Bobby\MultiProcesses;

class Worker extends Process
{
    protected $workerId;

    protected $pool;

    public function __construct(callable $callback, bool $isDaemon = false, int $workerId = 0)
    {
        $this->workerId = $workerId;
        parent::__construct($callback, $isDaemon);
    }

    public function setPool(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function getPool(): Pool
    {
        if (is_null($this->pool)) {
            throw new ProcessException("Property pool dose not set.");
        }

        return $this->pool;
    }

    public function getWorkerId(): int
    {
        return $this->workerId;
    }

    public function setWorkId(int $workerId)
    {
        $this->workerId = $workerId;
    }

    public function isUsing(): bool
    {
        return (bool)$this->getPool()->openInterProcessShareMemory()->has($this->getPid());
    }

    public function use()
    {
        return $this->getPool()->openInterProcessShareMemory()->set($this->getPid(), 1);
    }

    public function free()
    {
        return $this->getPool()->openInterProcessShareMemory()->delete($this->getPid());
    }

    public function __toString()
    {
        return $this->getWorkerId();
    }
}