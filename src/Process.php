<?php
/**
 * 多进程封装类
 */
namespace Bobby\MultiProcesses;

class Process
{    
    protected $name;

    protected $masterWritablePipe;

    protected $workerWritablePipe;

    protected $writePort;

    protected $readPort;

    protected $callback;

    protected $isDaemon;

    protected $isMaster = true;

    public function __construct(callable $callback, bool $isDaemon = false)
    {
        $this->callback = $callback;
        $this->isDaemon = $isDaemon;
    }

    public function setName(string $name)
    {
        $this->name = $name;
        cli_set_process_title($name);
    }

    public function getName(): ?string
    {
        return $this->name;
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
        if (!$this->writePort) {
            $file = $this->isMaster? $this->masterWritablePipe: $this->workerWritablePipe;
            $this->makePipe($file);
            $this->writePort = fopen($file, 'w');
        }
        fwrite($this->writePort, serialize($message));
    }

    public function read(bool $isBlock = false)
    {
        if (!$this->readPort) {
            $file = $this->isMaster? $this->workerWritablePipe: $this->masterWritablePipe;
            $this->makePipe($file);
            $this->readPort = fopen($file, 'r');
            stream_set_blocking($this->readPort, false);
        }

        $reads = $writes = $exceps = [];
        $reads[] = $this->readPort;
        if (stream_select($reads, $writes, $exceps, null)) {
            return unserialize(stream_get_contents($this->readPort));
        }
    }

    protected function makePipe(string $file)
    {
        if (!file_exists($file)) {
            if (!posix_mkfifo($file, 0777)) {
                throw new \Exception("Crate fifo fail.");
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
            throw new \Exception("Fork child process fail.");
        }

        if ($pid === 0) {
            $this->isMaster = false;

            if (posix_setsid() === -1) {
                die("Create session fail.");
            };

            if (($daemonPid = pcntl_fork()) < 0) {
                 die("Fork damon child process fail.");
            }

            if ($daemonPid > 0) {
                $this->write($daemonPid);
                exit(0);
            } else {
                if (!empty($name = $this->getName()) && cli_get_process_title() != $name) {
                    $this->setName($name);
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
            if ($this->name) {
                throw new \Exception("Fork child process: {$this->name} fail.");
            } else {
                throw new \Exception("Fork child process fail.");
            }
        }

        if ($pid > 0) {
            return $pid;
        } else {
            $this->isMaster = false;

            if (!empty($name = $this->getName()) && cli_get_process_title() != $name) {
                $this->setName($name);
            }

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

    public static function signal($callable = SIG_IGN)
    {
        pcntl_signal(SIGCHLD, $callable);
    }

    public static function wait()
    {
        while (1) {
            pcntl_signal_dispatch();
        }
    }
}