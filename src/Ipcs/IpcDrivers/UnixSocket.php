<?php
namespace Bobby\MultiProcesses\Ipcs\IpcDrivers;

use Bobby\MultiProcesses\Ipcs\IpcContract;
use Bobby\MultiProcesses\MessagePacker;

class UnixSocket extends IpcContract
{
    protected $useSocket;

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
        if (!$this->usePort()) {
            $this->useSocket = $this->unixSockets[$currentIsMaster? 0: 1];
            fclose($this->unixSockets[$currentIsMaster? 1: 0]);
        }
    }

    public function usePort()
    {
        return $this->useSocket;
    }

    protected function getWritePort()
    {
        stream_set_blocking($port = $this->usePort(), true);
        return $port;
    }

    protected function getReadPort()
    {        
        stream_set_blocking($port = $this->usePort(), false);
        return $this->usePort();
    }

    public function close()
    {
        fclose($this->usePort());
    }

    public function clear()
    {

    }
}