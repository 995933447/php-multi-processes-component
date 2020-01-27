<?php
namespace Bobby\MultiProcesses;

class Pool
{
    protected $maxWorkersNum;

    protected $minIdleWorkersNum;

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

    public function setMinIdleWorkersNum(int $num)
    {
        $this->minIdleWorkersNum = $num;
    }

    public function getMinIdleWorkersNum(): int
    {
        return !is_null($this->minIdleWorkersNum)? $this->minIdleWorkersNum: $this->maxWorkersNum;
    }

    public function run()
    {
        for ($i = 0; $i < $this->getMinIdleWorkersNum(); $i++) {
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

    public function onCollect(callable $callback = null, bool $autoCollectChild = true)
    {
        if ($callback == SIG_IGN || $callback == SIG_DFL) {
            pcntl_signal(SIGCHLD, $callback);
        } else {
            pcntl_signal(SIGCHLD, function ($signo) {
                if (!is_null($callback)) {
                    $callback($signo);
                }

                while ($autoCollectChild) {
                    if ($pid = pcntl_wait($status, WNOHANG) <= 0) {
                        break;
                    }

                    foreach ($this->runningWorkers as $index => $worker) {
                        if ($worker->getPid() == $pid) {
                            $worker->free();

                            unset($this->runningWorkers[$index]);
                            
                            if ($this->runningWorkers->isEmpty()) {
                                $this->closeInterProcessShareMemory();
                            }
                        }
                    }
                }
            });
        }
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

    public function closeInterProcessShareMemory()
    {
        if ($this->interProcessShareMemory instanceof InterProcessShareMemory) $this->interProcessShareMemory->release();
    }
}