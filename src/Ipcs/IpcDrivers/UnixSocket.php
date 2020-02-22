<?php
namespace Bobby\MultiProcesses\Ipcs\IpcDrivers;

use Bobby\MultiProcesses\Ipcs\IpcContract;
use Bobby\MultiProcesses\MessagePacker;

class UnixSocket extends IpcContract
{
    protected $usedSocket;

    protected $unixSockets;

    public function __construct(MessagePacker $messagePacker)
    {
        parent::__construct($messagePacker);
        $this->makeSocketPair();
    }

    protected function makeSocketPair()
    {
        $this->unixSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }

    public function bindPortWithProcess(bool $currentIsMaster)
    {
        if (!$this->getUsedPort()) {
            $this->usedSocket = $this->unixSockets[$currentIsMaster? 0: 1];
            stream_set_blocking($this->usedSocket, false);
            fclose($this->unixSockets[$currentIsMaster? 1: 0]);
        }
    }

    public function getUsedPort()
    {
        return $this->usedSocket;
    }

    protected function getWritePort()
    {
        return $this->getUsedPort();
    }

    protected function getReadPort()
    {        
        return $this->getUsedPort();
    }

    public function close()
    {
        @fclose($this->getUsedPort());
    }

    public function clear()
    {
        $this->close();
    }
}