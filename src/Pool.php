<?php
namespace Bobby\MultiProcesses;

class Pool
{
    protected $maxWorkersNum;

    protected $minIdleWorkerNum;

    protected $workerPrototype;

    protected $runningWorkers;

    protected $interProcessShareMemory;

    protected $poolId;

    public function __construct(int $maxWorkersNum, Worker $worker, $poolId = __CLASS__)
    {
        $this->maxWorkersNum = $maxWorkersNum;
        $this->workerPrototype = $worker;
        $this->workerPrototype->setPool($this);
        $this->runningWorkers = new \SplQueue();
        $this->poolId = $poolId;
    }

    public function getWorkersNum(): int
    {
        return count($this->runningWorkers);
    }

    public function getMaxWorkersNum(): int
    {
        return $this->maxWorkersNum;
    }

    public function setMinIdleWorkerNum(int $num)
    {
        $this->minIdleWorkerNum = $num;
    }

    public function getMinIdleWorkerNum(): int
    {
        return !is_null($this->minIdleWorkerNum)? $this->minIdleWorkerNum: $this->maxWorkersNum;
    }

    public function run()
    {
        for ($i = 0; $i < $this->getMinIdleWorkerNum(); $i++) {
            $this->addWorker($i + $this->workerPrototype->getWorkerId());
        }

        foreach ($this->runningWorkers as $worker) {
            $worker->run();
        }
    }

    protected function addWorker(int $workerId)
    {
        $worker = clone $this->workerPrototype;
        $worker->setWorkId($workerId);
        $this->runningWorkers->enqueue($worker);
        return $worker;
    }

    public function getWorker(): Worker
    {
        $this->runningWorkers->enqueue($worker = $this->runningWorkers->dequeue());
        return $worker;
    }

    public function getIdleWorker(): ?Worker
    {
        $runningWorkersNum = count($this->runningWorkers);
        $n = 0;      
        while(($worker = $this->getWorker()) && $worker->isUsing() && ++$n <= $runningWorkersNum);
        if (!$worker->isUsing()) return $worker;

        if ($runningWorkersNum < $this->getMaxWorkersNum()) {
            $worker = $this->addWorker($this->workerPrototype->getWorkerId() + $runningWorkersNum);
            $worker->run();
            return $worker;
        }

        return null;
    }

    public function broadcast($message)
    {
        $this->broadcastString(MessagePacker::serialize($message));
    }

    public function broadcastString(string $message)
    {
        foreach ($this->runningWorkers as $worker) {
            $worker->writeString($message);
        }
    }

    public function onCollect(callable $callback = null)
    {
        pcntl_signal(SIGCHLD, $callback?: function ($signo) {
            while (1) {
                if ($pid = pcntl_wait($status, WNOHANG) <= 0) {
                    break;
                } else {
                    foreach ($this->runningWorkers as $index => $worker) {
                        if ($worker->getPid() == $pid) {
                            unset($this->runningWorkers[$index]);
                        }
                    }
                }
            }
        });
    }

    public static function collect()
    {
        Worker::collect();
    }

    public function openInterProcessShareMemory()
    {
        if (!$this->interProcessShareMemory) { 
           $this->interProcessShareMemory = new InterProcessShareMemory($this->poolId, false);
        }
        return $this->interProcessShareMemory;
    }
}