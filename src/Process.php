<?php
/**
 * 多进程封装类
 */
namespace Bobby\MultiProcesses;

use Bobby\MultiProcesses\Ipcs\IpcContract;
use Bobby\MultiProcesses\Ipcs\IpcDrivers\UnixSocket;
use Bobby\MultiProcesses\Ipcs\IpcFactory;

class Process
{
    public $ipc;

    protected $ipcType;

    protected $callback;

    protected $isDaemon;

    protected $isMaster = true;

    protected $name;

    protected $pid;

    public function __construct(callable $callback, bool $isDaemon = false, int $ipcType = IpcFactory::UNIX_SOCKET_IPC)
    {
        $this->callback = $callback;
        $this->isDaemon = $isDaemon;
        $this->ipcType = $ipcType;
    }

    public function getPid(): ?int
    {
        if ($this->isMaster) {
            return $this->pid;
        } 

        return $this->pid?: $this->pid = posix_getpid();
    }

    public function setName(string $name)
    {
        $this->name = $name;
        if (!$this->isMaster) {
            cli_set_process_title($name);
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function getRealName(): string
    {
        return cli_get_process_title();
    }

    public function write($message)
    {
        $this->writeString(MessagePacker::serialize($message));
    }

    public function writeString(string $message)
    {
        $this->makeIpc()->write($message);
    }

    public function read(bool $block = true)
    {
        return MessagePacker::unserialize($this->readString($block));
    }

    public function readString(bool $block = true): string
    {
        return $this->makeIpc()->read($block);
    }

    protected function initIpc(): IpcContract
    {
        if (!$this->ipc) {
            $this->ipc = IpcFactory::make($this->ipcType, new MessagePacker(md5(__FILE__ . '~!@')));
        }

        return $this->ipc;
    }

    protected function makeIpc(): IpcContract
    {
        $this->ipc->bindPortWithProcess($this->isMaster);
        return $this->ipc;
    }

    public function run()
    {
        $this->initIpc();
      
        if ($this->isDaemon)
            return $this->startAsDaemon();

        return $this->startAsNotDaemon();
    }

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

                Quit::normalQuit();
            } else {
                if ($this->name) {
                    $this->setName($this->name);
                }

                umask(0);

                chdir('/');

                call_user_func_array($this->callback, array_merge([$this], func_get_args()));

                $this->closeIpc();
                $this->clearIpc();

                Quit::normalQuit();
            }
        }

        return $this->pid = $this->read();
    }

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

    public function closeIpc()
    {
        $this->makeIpc()->close();
    }

    public function clearIpc()
    {
        $this->makeIpc()->clear();
    }

    public static function onCollect(?callable $callback = null)
    {
        pcntl_signal(SIGCHLD, $callback?: function ($signo) {
            while (1) {
                if (pcntl_wait($status, WNOHANG) <= 0) {
                    break;
                }
            }
        });
    }

    public static function collect()
    {
        while (1) {
            pcntl_signal_dispatch();
        }
    }
}