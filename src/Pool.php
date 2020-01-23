<?php
namespace Bobby\MultiProcesses;

class Pool
{
    protected $maxWorkersNum;

    protected $minIdleWorkerNum;

    protected $maxIdleWorkerNum;

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

    public function setMaxIdleWorkerNum(int $num)
    {
        $this->maxIdleWorkerNum = $num;
    }

    public function getMaxIdleWorkerNum(): int
    {
        return !is_null($this->maxIdleWorkerNum)? $this->maxIdleWorkerNum: $this->maxWorkersNum;
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
    }

    public function getWorker(): Worker
    {
        $this->runningWorkers->enqueue($worker = $this->runningWorkers->dequeue());
        return $worker;
    }

    public function getIdleWorker($block = true): ?Worker
    {
        foreach ($this->runningWorkers as $worker) {
            if (!$worker->isUsing()) {
                return $worker;
            }
        }

        if (($runningWorkersNum = count($this->runningWorkers)) < $this->getMaxIdleWorkerNum()) {
            $this->addWorker($this->workerPrototype->getWorkerId() + (--$runningWorkersNum));
            $worker = $this->getWorker();
            return $worker;
        }

        if ($block) {
            return $this->getIdleWorker();
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

    public static function onCollect()
    {
        Worker::onCollect();
    }

    public static function collect()
    {
        Worker::collect();
    }

    public function openInterProcessShareMemory()
    {
        if (!$this->interProcessShareMemory) { 
           $this->interProcessShareMemory = new InterProcessShareMemory($this->poolId);
        }
        return $this->interProcessShareMemory;
    }
}