<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Ipcs\IpcDrivers\UnixSocket;
use Bobby\MultiProcesses\MessagePacker;

$ipc = new UnixSocket(new MessagePacker(md5(__FILE__ . '~!@')));

if (($pid = pcntl_fork()) > 0) {
    $ipc->usePort(0);
    $ipc->write("child PID: $pid" . PHP_EOL);
    echo "read write finish";
    echo $ipc->read();
    $ipc->write("hello childrn" . PHP_EOL);
} else {
    $ipc->usePort(1);
    echo $ipc->read();
    $ipc->write("message from child" . PHP_EOL);
    echo "child write finish" . PHP_EOL;
    echo $ipc->read();
}