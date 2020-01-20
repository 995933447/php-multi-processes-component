<?php
namespace Bobby\MultiProcesses;

class Pipes
{
    protected $writePort;

    protected $readPort;

    protected $writablePipe;

    protected $readablePipe;

    public function __construct(string $writablePipe, string $readablePipe)
    {
        $this->writablePipe = $writablePipe;
        $this->readablePipe = $readablePipe;
    }

    public function makePipe(string $file)
    {
        if (!file_exists($file)) {
            if (!posix_mkfifo($file, 0777)) {
                throw new ProcessException("Crate pipe fifo fail.");
            }
        }
    }

    public function write(string $message)
    {
        if (!$this->writePort) {
            $this->makePipe($this->writablePipe);
            $this->writePort = fopen($this->writablePipe, 'w');
        }
        if (!fwrite($this->writePort, $message)) {
            throw new ProcessException("write pipe $message fail.");
        };
        fflush($this->writePort);
    }

    public function read(bool $block = true): string
    {
        if (!$this->readPort) {
            $this->makePipe($this->readablePipe);
            $this->readPort = fopen($this->readablePipe, 'rw');
            stream_set_blocking($this->readPort, false);
        }

        $reads = $writes = $excepts = [];
        while (1) {
            $reads[] = $this->readPort;
            if (stream_select($reads, $writes, $excepts, null)) {
                if ($content = stream_get_contents($this->readPort)) {
                    return $content;
                };
      
                if (!$block) {
                    return $content;
                }
            }
        }
    }

    public function closePipes()
    {
        $this->writePort && fclose($this->writePort) && $this->readPort && fclose($this->readPort);
    }

    public function clearPipes()
    {
        file_exists($this->writablePipe) && unlink($this->writablePipe) && file_exists($this->readablePipe) && unlink($this->readablePipe);
    }
}