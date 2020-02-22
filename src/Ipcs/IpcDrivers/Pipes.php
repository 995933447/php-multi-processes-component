<?php
namespace Bobby\MultiProcesses\Ipcs\IpcDrivers;

use Bobby\MultiProcesses\Ipcs\IpcContract;
use Bobby\MultiProcesses\Quit;
use Bobby\MultiProcesses\ProcessException;
use Bobby\MultiProcesses\MessagePacker;

class Pipes extends IpcContract
{
    protected $writePort;

    protected $readPort;

    protected $writablePipe;

    protected $readablePipe;

    protected $messagePacker;

    protected $masterWritablePipe;

    protected $workerWritablePipe;

    protected $hasBoundPortWithProcess;

    public function __construct(MessagePacker $messagePacker)
    {
        parent::__construct($messagePacker);
        $format = sys_get_temp_dir() . "/BOBBY_PHP_PROCESS_" . posix_getpid() . "_%s";
        $this->masterWritablePipe = sprintf($format, uniqid());
        $this->workerWritablePipe = sprintf($format, uniqid());
    }

    public function bindPortWithProcess(bool $currentIsMaster)
    {
        if ($this->hasBoundPortWithProcess) return;

        $this->hasBoundPortWithProcess = true;
        $this->writablePipe = $currentIsMaster? $this->masterWritablePipe: $this->workerWritablePipe;
        $this->readablePipe = $currentIsMaster? $this->workerWritablePipe: $this->masterWritablePipe;
    }

    protected function makePipe(string $file)
    {
        if (!file_exists($file)) {
            if (!posix_mkfifo($file, 0777)) {
                throw new ProcessException("Crate pipe fifo fail.");
                Quit::exceptionQuit();
            }
        }
    }

    public function getWritePort()
    {
        if (!$this->writePort) {
            $this->makePipe($this->writablePipe);
            $this->writePort = fopen($this->writablePipe, 'w');
        }
        return $this->writePort;
    }

    public function getReadPort()
    {
        if (!$this->readPort) {
            $this->makePipe($this->readablePipe);
            $this->readPort = fopen($this->readablePipe, 'r');
            stream_set_blocking($this->readPort, false);
        }
        return $this->readPort;
    }

    public function close()
    {
        $this->writePort && @fclose($this->writePort) && $this->readPort && @fclose($this->readPort);
    }

    public function clear()
    {
        $this->close();
        file_exists($this->writablePipe) && unlink($this->writablePipe) && file_exists($this->readablePipe) && unlink($this->readablePipe);
    }
}