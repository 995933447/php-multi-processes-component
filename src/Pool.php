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
        for ($i = 0; $i < $this->getMinIdleWorkersNum(); $i++) {
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
    protected function addWorker(int $workerId)
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
        while(($worker = $this->getWorker()) && $worker->isUsing() && ++$n <= $runningWorkersNum);
        if (!$worker->isUsing()) return $worker;

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

    /** 注册子进程信号处理器
     * @param null $callback 自定义信号处理回调函数, null代表使用默认的当前注册信号处理器(自动回收子进程并释放资源)
     * @param bool $autoCollectChild 执行$callback后是否自动回收子进程并释放资源
     */
    public function onCollect($callback = null, bool $autoCollectChild = true)
    {
        if (in_array($callback, [SIG_IGN, SIG_DFL], true)) {
            pcntl_signal(SIGCHLD, $callback);
        } else {
            pcntl_signal(SIGCHLD, function ($signo) use ($callback, $autoCollectChild) {
                if (!is_null($callback)) {
                    $callback($signo);
                }

                while (is_null($callback) || $autoCollectChild) {
                    if ($pid = pcntl_wait($status, WNOHANG) <= 0) {
                        break;
                    }

                    foreach ($this->runningWorkers as $index => $worker) {
                        if ($worker->getPid() == $pid) {
                            $worker->free();

                            unset($this->runningWorkers[$index]);
                            
                            if ($this->runningWorkers->isEmpty()) {
                                $this->closeInterProcessShareMemory();
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
    public function openInterProcessShareMemory()
    {
        if (!$this->interProcessShareMemory) { 
           $this->interProcessShareMemory = new InterProcessShareMemory($this->poolId, false);
        }
        return $this->interProcessShareMemory;
    }

    /**
     *  关闭进程池共享内存段
     */
    public function closeInterProcessShareMemory()
    {
        if ($this->interProcessShareMemory instanceof InterProcessShareMemory) $this->interProcessShareMemory->release();
    }
}