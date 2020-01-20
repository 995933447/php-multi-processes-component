<?php
/**
 * 多进程封装类
 */
namespace Bobby\MultiProcesses;

class Process
{
    protected $masterWritablePipe;

    protected $workerWritablePipe;

    protected $pipes;

    protected $callback;

    protected $isDaemon;

    protected $isMaster = true;

    protected $name;

    protected static $hasListenedChildSignal = false;

    public function __construct(callable $callback, bool $isDaemon = false)
    {
        $this->callback = $callback;
        $this->isDaemon = $isDaemon;
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

    public function setPipes(string $masterWritablePipe, string $workerWritablePipe)
    {
        $this->masterWritablePipe = $masterWritablePipe;
        $this->workerWritablePipe = $workerWritablePipe;
    }

    public function setDefaultPipesIfNotSet()
    {
        if (!$this->masterWritablePipe || !$this->workerWritablePipe) {
            $format = sys_get_temp_dir() . "/BOBBY_PROCESS_%s";
            $this->setPipes(sprintf($format, uniqid()), sprintf($format, uniqid()));
        }
    }

    public function write($message)
    {
        $this->writeString(MessagePacker::serialize($message));
    }

    public function writeString(string $message)
    {
        $this->makePipes()->write($message);
    }

    public function read()
    {
        return MessagePacker::unserialize($this->readString());
    }

    public function readString(): string
    {
        return $this->makePipes()->read();
    }

    protected function makePipes()
    {
        if (!$this->pipes) {
            return $this->pipes = $this->isMaster? new Pipes($this->masterWritablePipe, $this->workerWritablePipe): new Pipes($this->workerWritablePipe, $this->masterWritablePipe);
        }

        return $this->pipes;
    }

    public function run()
    {
        $this->setDefaultPipesIfNotSet();

        if ($this->isDaemon)
            return $this->startAsDaemon();

        return $this->startAsNotDaemon();
    }

    protected function startAsDaemon()
    {
        if (($pid = pcntl_fork()) < 0) {
            throw new ProcessException("Fork child process fail.");
        }

        if ($pid === 0) {
            $this->isMaster = false;

            if (posix_setsid() === -1) {
                throw new ProcessException("Create session fail.");
            };

            if (($daemonPid = pcntl_fork()) < 0) {
                throw new ProcessException("Fork damon child process fail.");
            }

            if ($daemonPid > 0) {
                $this->write($daemonPid);

                exit(0);
            } else {
                if ($this->name) {
                    $this->setName($this->name);
                }

                umask(0);

                chdir('/');

                call_user_func_array($this->callback, array_merge([$this], func_get_args()));

                $this->closePipes();

                exit(0);
            }
        }

        return $this->read();
    }

    protected function startAsNotDaemon()
    {
        if (($pid = pcntl_fork()) < 0) {
                throw new ProcessException("Fork child process fail.");
        }

        if ($pid > 0) {
            return $pid;
        } else {
            $this->isMaster = false;

            if ($this->name) {
                $this->setName($this->name);
            }

            call_user_func_array($this->callback, array_merge([$this], func_get_args()));

            $this->closePipes();

            exit(0);
        }
    }

    public function closePipes()
    {
        $this->pipes->closePipes();
    }

    public function clearPipes()
    {
        $this->pipes->clearPipes();
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