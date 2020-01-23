<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Ipcs\IpcDrivers\Pipes;
use Bobby\MultiProcesses\MessagePacker;

$format = sys_get_temp_dir() . "/BOBBY_PHP_PROCESS_%s";
$ipc = new Pipes(sprintf($format, uniqid()), sprintf($format, uniqid()), new MessagePacker(md5(__FILE__ . '~!@')));

if (($pid = pcntl_fork()) > 0) {
    // echo "start.";
    // $ipc->write("child PID: $pid" . PHP_EOL);
    // echo "read write finish";
    echo $ipc->read();
    // $ipc->write("hello childrn" . PHP_EOL);
} else {
    // var_dump($pid);
    // echo $ipc->read();
    // $ipc->write("message from child" . PHP_EOL);
    // echo "child write finish" . PHP_EOL;
    // echo $ipc->read();
}