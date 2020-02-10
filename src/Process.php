<?php
namespace Bobby\MultiProcesses;

use Bobby\MultiProcesses\Ipcs\IpcContract;
use Bobby\MultiProcesses\Ipcs\IpcFactory;

/** 子进程封装类
 * Class Process
 * @package Bobby\MultiProcesses
 */
class Process
{
    public $ipc;

    protected $ipcType;

    protected $callback;

    protected $isDaemon;

    protected $isMaster = true;

    protected $name;

    protected $pid;

    /**
     * Process constructor.
     * @param mixed $callback 子进程启动执行该回调
     * @param bool $isDaemon 子进程是否设置为守护进程
     * @param int $ipcType 进程间通信方式,IpcFactory::UNIX_SOCKET_IPC为unix socket方式,默认方式.IpcFactory::PIPES_IPC为有名管道方式
     */
    public function __construct($callback, bool $isDaemon = false, int $ipcType = IpcFactory::UNIX_SOCKET_IPC)
    {
        $this->callback = $callback;
        $this->isDaemon = $isDaemon;
        $this->ipcType = $ipcType;
    }

    /** 获取子进程PID
     * @return int|null
     */
    public function getPid(): ?int
    {
        if ($this->isMaster) {
            return $this->pid;
        } 

        return $this->pid?: $this->pid = posix_getpid();
    }

    /** 设置进程名称
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
        if (!$this->isMaster) {
            cli_set_process_title($name);
        }
    }

    /** 获取setName设置的进程名称(并一定真实)
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /** 获取进程真实名称
     * @return string
     */
    public function getRealName(): string
    {
        return cli_get_process_title();
    }

    /** 写入消息,可以写入任务类型。会字段对消息进行序列化
     * @param $message
     */
    public function write($message)
    {
        $this->writeString(MessagePacker::serialize($message));
    }

    /** 写入字符串消息,消息仅允许字符串类型
     * @param string $message
     * @throws ProcessException
     */
    public function writeString(string $message)
    {
        $this->makeIpc()->write($message);
    }

    /** 读取消息(对消息进行反序列化)
     * @param bool $block
     * @return string
     */
    public function read(bool $block = true)
    {
        return MessagePacker::unserialize($this->readString($block));
    }

    /** 读取字符串消息(不对消息进行反序列化)
     * @param bool $block
     * @return string
     */
    public function readString(bool $block = true): string
    {
        return $this->makeIpc()->read($block);
    }

    /** 初始化并创建进程间通信对象
     * @return IpcContract
     */
    protected function initIpc(): IpcContract
    {
        if (!$this->ipc) {
            $this->ipc = IpcFactory::make($this->ipcType, new MessagePacker(md5(__FILE__ . '~!@')));
        }

        return $this->ipc;
    }

    /** 创建进程间通信通道
     * @return IpcContract
     */
    protected function makeIpc(): IpcContract
    {
        $this->ipc->bindPortWithProcess($this->isMaster);
        return $this->ipc;
    }

    /** 运行子进程
     * @return int|string
     * @throws ProcessException
     */
    public function run()
    {
        $this->initIpc();
      
        if ($this->isDaemon)
            return $this->startAsDaemon();

        return $this->startAsNotDaemon();
    }

    /** 以守护进程方式运行子进程
     * @return string
     * @throws ProcessException
     */
    protected function startAsDaemon()
    {
        if (($pid = pcntl_fork()) < 0) {
            throw new ProcessException("Fork child process fail.");
            Quit::exceptionQuit();
        }

        if ($pid === 0) {
            $this->isMaster = false;

            if (posix_setsid() === -1) {
                throw new ProcessException("Create session fail.");
                Quit::exceptionQuit();
            };

            if (($daemonPid = pcntl_fork()) < 0) {
                throw new ProcessException("Fork damon child process fail.");
                Quit::exceptionQuit();
            }

            if ($daemonPid > 0) {
                $this->write($daemonPid);
            } else {
                if ($this->name) {
                    $this->setName($this->name);
                }

                umask(0);

                chdir('/');

                call_user_func_array($this->callback, array_merge([$this], func_get_args()));

                $this->closeIpc();
                $this->clearIpc();
            }

            Quit::normalQuit();
        }

        return $this->pid = $this->read();
    }

    /** 以非守护进程方式运行子进程
     * @return int
     * @throws ProcessException
     */
    protected function startAsNotDaemon()
    {
        if (($pid = pcntl_fork()) < 0) {
                throw new ProcessException("Fork child process fail.");
                exit(static::EXCEPTION_EXIT);
        }

        if ($pid > 0) {
            return $this->pid = $pid;
        } else {
            $this->isMaster = false;

            if ($this->name) {
                $this->setName($this->name);
            }

            call_user_func_array($this->callback, array_merge([$this], func_get_args()));

            $this->closeIpc();
            $this->clearIpc();

            Quit::normalQuit();
        }
    }

    /**
     *  关闭进程通信通道
     */
    public function closeIpc()
    {
        $this->makeIpc()->close();
    }

    /**
     *  释放进程通信资源
     */
    public function clearIpc()
    {
        $this->makeIpc()->clear();
    }

    /** 子进程信号处理器
     * @param callable|null $callback
     */
    public static function onCollect($callback = null)
    {
        pcntl_signal(SIGCHLD, !is_null($callback)? $callback: function ($signo) {
            while (1) {
                if (pcntl_wait($status, WNOHANG) <= 0) {
                    break;
                }
            }
        });
    }

    /**
     *  阻塞回收子进程
     */
    public static function collect()
    {
        while (1) {
            pcntl_signal_dispatch();
        }
    }
}