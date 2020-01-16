<?php
/**
 * 多进程封装类
 */
namespace Bobby\MultiProcesses;

class Process
{
    protected $masterWritablePipe;

    protected $workerWritablePipe;

    protected $writePort;

    protected $readPort;

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

    public function getName(): string
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
            $this->setPipes(sprintf("/tmp/%s", uniqid()), sprintf("/tmp/%s", uniqid()));
        }
    }

    public function write($message)
    {
        $this->writeString(serialize($message));
    }

    public function writeString(string $message)
    {
        if (!$this->writePort) {
            $file = $this->isMaster? $this->masterWritablePipe: $this->workerWritablePipe;
            $this->makePipe($file);
            $this->writePort = fopen($file, 'w');
        }
        fwrite($this->writePort, $message);
    }

    public function read()
    {
        return unserialize($this->readString());
    }

    public function readString(): string
    {
        if (!$this->readPort) {
            $file = $this->isMaster? $this->workerWritablePipe: $this->masterWritablePipe;
            $this->makePipe($file);
            $this->readPort = fopen($file, 'r');
            stream_set_blocking($this->readPort, false);
        }

        $reads = $writes = $excepts = [];
        $reads[] = $this->readPort;
        if (stream_select($reads, $writes, $excepts, null)) {
            return stream_get_contents($this->readPort);
        }
    }

    protected function makePipe(string $file)
    {
        if (!file_exists($file)) {
            if (!posix_mkfifo($file, 0777)) {
                throw new ProcessException("Crate fifo fail.");
            }
        }
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
                    cli_set_process_title($this->name);
                }

                umask(0);

                chdir('/');

                call_user_func_array($this->callback, array_merge([$this], func_get_args()));

                $this->closePipes();

                exit(0);
            }
        }

        return $this->read(true);
    }

    protected function startAsNotDaemon()
    {
        if (($pid = pcntl_fork()) < 0) {
                throw new ProcessException("Fork child process fail.");
        }

        if ($pid > 0) {
            return $pid;
        } else {
            if ($this->name) {
                cli_set_process_title($this->name);
            }

            $this->isMaster = false;

            call_user_func_array($this->callback, array_merge([$this], func_get_args()));

            $this->closePipes();

            exit(0);
        }
    }

    public function closePipes()
    {
        $this->writePort && fclose($this->writePort) && $this->readPort && fclose($this->readPort);
    }

    public function clearPipes()
    {
        file_exists($this->masterWritablePipe) && unlink($this->masterWritablePipe) && file_exists($this->workerWritablePipe) && unlink($this->workerWritablePipe);
    }

    public static function listenChildSignal($callable = SIG_IGN)
    {
        pcntl_signal(SIGCHLD, $callable);
        static::$hasListenedChildSignal = true;
    }

    public static function collect()
    {
        if (!static::$hasListenedChildSignal) {
            static::listenChildSignal();
        }

        while (1) {
            pcntl_signal_dispatch();
        }
    }
}