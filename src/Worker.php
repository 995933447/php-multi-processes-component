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

    protected $lockFileName;

    protected $lockFile;

    protected $isRealWorker = false;

    protected $isInited = false;

    /**
     * Worker constructor.
     * @param mixed $callback 子进程启动执行该回调
     * @param bool $isDaemon 子进程是否设置为守护进程
     * @param int $ipcType 进程间通信方式,IpcFactory::UNIX_SOCKET_IPC为unix socket方式,默认方式.IpcFactory::PIPES_IPC为有名管道方式
     * @param int $poolFirstWorkerId
     */
    public function __construct(callable $callback, bool $isDaemon = false, int $ipcType = IpcFactory::UNIX_SOCKET_IPC, int $poolFirstWorkerId = 0)
    {
        $this->workerId = $poolFirstWorkerId;
        parent::__construct($this->upgradeWorkerCallback($callback), $isDaemon, $ipcType);
    }

    protected function upgradeWorkerCallback(callable $callback)
    {
        return function ($worker) use ($callback) {
            $worker->isRealWorker = true;
            call_user_func_array($callback, [$worker]);
        };
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
            throw new ProcessException("The Worker project didn't bind pool project.");
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
    protected function setWorkerId(int $workerId)
    {
        $this->workerId = $workerId;
    }

    public function init(int $realWorkerId)
    {
        $this->isInited = true;
        $this->setWorkerId($realWorkerId);
        $this->createLockFile();
    }

    protected function createLockFile()
    {
        $this->lockFileName = sys_get_temp_dir() . '/' . str_replace('\\', '_', __CLASS__). "_{$this->workerId}_" . date('Y_m_d_H_i_s');
        touch($this->lockFileName);
    }

    protected function getLockFile()
    {
        if (is_null($this->lockFile)) {
            $this->lockFile = fopen($this->lockFileName, 'r');
        }

        return $this->lockFile;
    }

    /** 判断当前Worker对象是否正在执行任务
     * @return bool
     */
    public function isBusy(): bool
    {
        if (!file_exists($this->lockFileName)) {
            return true;
        }

        if ($isFree = flock($this->getLockFile(), LOCK_EX|LOCK_NB)) {
            flock($this->lockFile, LOCK_UN);
        }

        return !file_exists($this->lockFileName) || !$isFree;
    }

    /** 设置当前Worker对象是正在执行任务状态
     * @return void
     */
    public function lock()
    {
        flock($this->getLockFile(), LOCK_EX);
    }

    /** 设置当前Worker对无任务执行状态
     * @return void
     */
    public function free()
    {
        flock($this->getLockFile(), LOCK_UN);
    }

    public function __destruct()
    {
        if ($this->isRealWorker && file_exists($this->lockFileName)) {
            unlink($this->lockFileName);
        }
    }

    public function run()
    {
        if (!$this->isInited) {
            throw new ProcessException( __CLASS__ . " must be used on " . Pool::class);
        }
        parent::run();
    }
}