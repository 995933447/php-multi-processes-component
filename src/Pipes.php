<?php
namespace Bobby\MultiProcesses;

class Pipes
{
    protected $writePort;

    protected $readPort;

    protected $writablePipe;

    protected $readablePipe;

    protected $messagePacker;

    public function __construct(string $writablePipe, string $readablePipe, MessagePacker $messagePacker = null)
    {
        $this->writablePipe = $writablePipe;
        $this->readablePipe = $readablePipe;
        $this->messagePacker = $messagePacker?: new MessagePacker(md5(__FILE__ . '~!@'));
    }

    public function makePipe(string $file)
    {
        if (!file_exists($file)) {
            if (!posix_mkfifo($file, 0777)) {
                throw new ProcessException("Crate pipe fifo fail.");
                Quit::exceptionQuit();
            }
        }
    }

    public function write(string $message)
    {
        if (!$this->writePort) {
            $this->makePipe($this->writablePipe);
            $this->writePort = fopen($this->writablePipe, 'w');
        }

        if (($written = fwrite($this->writePort, $this->messagePacker->pack($message))) === false) {
            throw new ProcessException("write pipe $message fail.");
            Quit::exceptionQuit();
        };

        fflush($this->writePort);

        return $written;
    }

    public function read(bool $block = true): string
    {
        if ($this->messagePacker->hasMessageFromBuffer()) {
            return $this->messagePacker->getMessageFromBuffer();
        }

        if (!$this->readPort) {
            $this->makePipe($this->readablePipe);
            $this->readPort = fopen($this->readablePipe, 'r');
            stream_set_blocking($this->readPort, false);
        }

        $reads = $writes = $excepts = [];
        while (1) {
            $reads[] = $this->readPort;
            if (stream_select($reads, $writes, $excepts, null)) {
                if ($content = stream_get_contents($this->readPort)) {
                    $this->messagePacker->unpackToBuffer($content);
                    if ($this->messagePacker->hasMessageFromBuffer()) {
                        return $this->messagePacker->getMessageFromBuffer();
                    }
                };

                if (!$block) {
                    return '';
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