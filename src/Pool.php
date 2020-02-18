<?php
namespace Bobby\MultiProcesses;

/** 进程池管理类
 * Class Pool
 * @package Bobby\MultiProcesses
 */
class Pool
{
    protected $maxWorkersNum;

    protected $minIdleWorkersNum;

    protected $workerPrototype;

    protected $runningWorkers;

    protected $poolShareMemory;

    protected $poolId;

    public function __construct(int $maxWorkersNum, Worker $worker, $poolId = null)
    {
        $this->maxWorkersNum = $maxWorkersNum;
        $this->workerPrototype = $worker;
        $this->workerPrototype->setPool($this);
        $this->runningWorkers = new \SplQueue();
        if (is_null($this->poolId)) {
            $this->poolId = uniqid();
        } else {
            $this->poolId = $poolId;
        }
    }

    /** 获取进程池当前进程数量
     * @return int
     */
    public function getWorkersNum(): int
    {
        return count($this->runningWorkers);
    }

    /** 获取进程池最大允许进程数量
     * @return int
     */
    public function getMaxWorkersNum(): int
    {
        return $this->maxWorkersNum;
    }

    /** 设置进程池初始化运行最小可用进程数量
     * @param int $num
     */
    public function setMinIdleWorkersNum(int $num)
    {
        $this->minIdleWorkersNum = $num;
    }

    /** 获取进程池初始化运行最小可用进程数量
     * @return int
     */
    public function getMinIdleWorkersNum(): int
    {
        return !is_null($this->minIdleWorkersNum)? $this->minIdleWorkersNum: $this->maxWorkersNum;
    }

    /**
     *  启动进程池
     */
    public function run()
    {
        $minIdleWorkerNum = $this->getMinIdleWorkersNum();
        for ($i = 0; $i < $minIdleWorkerNum; $i++) {
            $this->addWorker($i + $this->workerPrototype->getWorkerId());
        }

        foreach ($this->runningWorkers as $worker) {
            $worker->run();
        }
    }

    /** 添加进程
     * @param int $workerId
     * @return Worker
     */
    public function addWorker(int $workerId): Worker
    {
        $worker = clone $this->workerPrototype;
        $worker->setWorkId($workerId);
        $this->runningWorkers->enqueue($worker);
        return $worker;
    }

    /** 轮询方式获取进程池里面的一个进程
     * @return Worker
     */
    public function getWorker(): Worker
    {
        $this->runningWorkers->enqueue($worker = $this->runningWorkers->dequeue());
        return $worker;
    }

    /** 从进程池里获取一个没有在执行任务的可用的进程,如果所有进程都繁忙状态且进程数小于最大允许进程数则动态添加新的子进程并返回
     * @return Worker|null
     * @throws ProcessException
     */
    public function getIdleWorker(): ?Worker
    {
        $runningWorkersNum = count($this->runningWorkers);
        $n = 0;      
        while(($worker = $this->getWorker()) && $worker->isLock() && ++$n <= $runningWorkersNum);
        if (!$worker->isLock()) return $worker;

        if ($runningWorkersNum < $this->getMaxWorkersNum()) {
            $worker = $this->addWorker($this->workerPrototype->getWorkerId() + $runningWorkersNum);
            $worker->run();
            return $worker;
        }

        return null;
    }

    /** 往进程池的所有进程广播消息
     * @param $message
     */
    public function broadcast($message)
    {
        $this->broadcastString(MessagePacker::serialize($message));
    }

    /** 往进程池的所有进程广播消息(仅允许字符串类型)
     * @param string $message
     */
    public function broadcastString(string $message)
    {
        foreach ($this->runningWorkers as $worker) {
            $worker->writeString($message);
        }
    }

    /** 监听收到子进程退出信号时回收子进程
     * @param null $callback 回收子进程前触发的回调函数.回调里请不要写子进程回收逻辑,该方法执行完$callback后将自动回收子进程资源并释放相应进程池内于进程相关的资源
     */
    public function onCollect($callback = null)
    {
        if (function_exists("pcntl_async_signals") && !pcntl_async_signals()) {
            pcntl_async_signals(true);
        }

        if (in_array($callback, [SIG_IGN, SIG_DFL], true)) {
            pcntl_signal(SIGCHLD, $callback);
        } else {
            pcntl_signal(SIGCHLD, function ($signo) use ($callback) {
                if (!is_null($callback) && is_callable($callback)) {
                    $callback($signo);
                }

                while (($pid = pcntl_wait($status, WNOHANG)) > 0) {
                    foreach ($this->runningWorkers as $index => $worker) {
                        if ($worker->getPid() == $pid) {
                            $worker->free();

                            unset($this->runningWorkers[$index]);
                            
                            if ($this->runningWorkers->isEmpty()) {
                                $this->closePoolShareMemory();
                                break;
                            }
                        }
                    }
                }
            });
        }
    }

    /**
     * 阻塞回收子进程
     */
    public static function collect()
    {
        Worker::collect();
    }

    /** 打开进程池共享内存段
     * @return InterProcessShareMemory
     * @throws ProcessException
     */
    public function openPoolShareMemory(): InterProcessShareMemory
    {
        if (!$this->poolShareMemory) {
           $this->poolShareMemory = new InterProcessShareMemory($this->poolId, false);
        }
        return $this->poolShareMemory;
    }

    /**
     *  关闭进程池共享内存段
     */
    public function closePoolShareMemory()
    {
        if ($this->poolShareMemory instanceof InterProcessShareMemory) $this->poolShareMemory->release();
    }
}