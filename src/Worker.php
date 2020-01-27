<?php
namespace Bobby\MultiProcesses;

use Bobby\MultiProcesses\Ipcs\IpcFactory;

/** Worker子进程类.配合进程池Pool类使用
 * Class Worker
 * @package Bobby\MultiProcesses
 */
class Worker extends Process
{
    protected $workerId;

    protected $pool;

    /**
     * Worker constructor.
     * @param callable $callback 子进程启动执行该方法
     * @param bool $isDaemon 子进程是否设置为守护进程
     * @param int $ipcType 进程间通信方式,IpcFactory::UNIX_SOCKET_IPC为unix socket方式,默认方式.IpcFactory::PIPES_IPC为有名管道方式
     * @param int $workerId 当前工作进程标识,不同于pid
     */
    public function __construct(callable $callback, bool $isDaemon = false, int $ipcType = IpcFactory::UNIX_SOCKET_IPC, int $workerId = 0)
    {
        $this->workerId = $workerId;
        parent::__construct($callback, $isDaemon, $ipcType);
    }

    /** 设置进程池Pool对象
     * @param Pool $pool
     */
    public function setPool(Pool $pool)
    {
        $this->pool = $pool;
    }

    /** 获取当前绑定Pool对象
     * @return Pool
     * @throws ProcessException
     */
    public function getPool(): Pool
    {
        if (is_null($this->pool)) {
            throw new ProcessException("Property pool dose not set.");
        }

        return $this->pool;
    }

    /** 获取当前工作worker单元ID
     * @return int
     */
    public function getWorkerId(): int
    {
        return $this->workerId;
    }

    /** 设置worker id
     * @param int $workerId
     */
    public function setWorkId(int $workerId)
    {
        $this->workerId = $workerId;
    }

    /** 判断当前Worker对象是否正在执行任务
     * @return bool
     * @throws ProcessException
     */
    public function isUsing(): bool
    {
        return (bool)$this->getPool()->openInterProcessShareMemory()->has($this->getPid());
    }

    /** 设置当前Worker对象是正在执行任务状态
     * @return bool
     * @throws ProcessException
     */
    public function use()
    {
        return $this->getPool()->openInterProcessShareMemory()->set($this->getPid(), 1);
    }

    /** 设置当前Worker对无任务执行状态
     * @return bool
     * @throws ProcessException
     */
    public function free()
    {
        return $this->getPool()->openInterProcessShareMemory()->delete($this->getPid());
    }

    public function __toString()
    {
        return $this->getWorkerId();
    }
}