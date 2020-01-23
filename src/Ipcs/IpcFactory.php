<?php
namespace Bobby\MultiProcesses\Ipcs;

use Bobby\MultiProcesses\MessagePacker;
use Bobby\MultiProcesses\Ipcs\IpcDrivers\UnixSocket;
use Bobby\MultiProcesses\Ipcs\IpcDrivers\Pipes;

class IpcFactory
{
    const UNIX_SOCKET_IPC = 1;
    const PIPES_IPC = 2;

    public static function make(int $ipcType, MessagePacker $messagePacker = null): IpcContract
    {
        $messagePacker = $messagePacker?: new MessagePacker(md5(__FILE__ . '~!@'));
        switch ($ipcType) {
            case static::UNIX_SOCKET_IPC:
                return new UnixSocket($messagePacker);
            case static::PIPES_IPC:
                return new Pipes($messagePacker);
        }
    }
}