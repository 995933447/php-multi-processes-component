<?php
namespace Bobby\MultiProcesses\Ipcs;

use Bobby\MultiProcesses\Quit;
use Bobby\MultiProcesses\ProcessException;
use Bobby\MultiProcesses\MessagePacker;

abstract class IpcContract
{
    protected $messagePacker;

    public function __construct(MessagePacker $messagePacker)
    {
        $this->messagePacker = $messagePacker;
    }

    abstract public function bindPortWithProcess(bool $currentIsMaster);

    abstract protected function getWritePort(); 

    public function write(string $message)
    {
        if (($written = fwrite($writePort = $this->getWritePort(), $this->messagePacker->pack($message))) === false) {
            throw new ProcessException("write pipe $message fail.");
            Quit::exceptionQuit();
        };

        fflush($writePort);

        return $written;
    }

    abstract protected function getReadPort();

    public function read(bool $block = true): string
    {
        if ($this->messagePacker->hasMessageFromBuffer()) {
            return $this->messagePacker->getMessageFromBuffer();
        }

        $readPort = $this->getReadPort();
        $reads = $writes = $excepts = [];
        while (1) {
            $reads[] = $readPort;
            // stream_select系统调用被信号打断会产生warnning,这个是可预知的warning,不会导致处理不了消息
            if (@stream_select($reads, $writes, $excepts, null)) {
                if ($content = stream_get_contents($readPort)) {
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

    abstract public function close();

    abstract public function clear();
}