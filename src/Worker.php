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

    protected $lockFile;

    protected $tempFile;

    /**
     * Worker constructor.
     * @param mixed $callback 子进程启动执行该回调
     * @param bool $isDaemon 子进程是否设置为守护进程
     * @param int $ipcType 进程间通信方式,IpcFactory::UNIX_SOCKET_IPC为unix socket方式,默认方式.IpcFactory::PIPES_IPC为有名管道方式
     * @param int $workerId 当前工作进程标识,不同于pid
     */
    public function __construct(callable $callback, bool $isDaemon = false, int $ipcType = IpcFactory::UNIX_SOCKET_IPC, int $workerId = 0)
    {
        $this->workerId = $workerId;
        $this->tempFile = stream_get_meta_data(tmpfile())['uri'];
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

    /** 获得加锁文件
     * @return bool|resource
     */
    protected function getLockFile()
    {
        if (!$this->lockFile) {
            $this->lockFile = fopen($this->tempFile, 'w');
        }
        return $this->lockFile;
    }

    /** 判断当前Worker对象是否正在执行任务
     * @return bool
     * @throws ProcessException
     */
    public function isLock(): bool
    {
        if ($isFree = flock($this->getLockFile(), LOCK_EX|LOCK_NB)) {
            flock($this->getLockFile(), LOCK_UN);
        }
        return !$isFree;
    }

    /** 设置当前Worker对象是正在执行任务状态
     * @return bool
     * @throws ProcessException
     */
    public function lock()
    {
        @flock($this->getLockFile(), LOCK_EX);
    }

    /** 设置当前Worker对无任务执行状态
     * @return bool
     * @throws ProcessException
     */
    public function free()
    {
        @flock($this->lockFile, LOCK_UN);
    }

    public function __destruct()
    {
        file_exists($this->tempFile) && unlink($this->tempFile);
    }
}