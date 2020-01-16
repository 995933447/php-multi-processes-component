<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Process;

$process = new Process(function (Process $process) {
    echo "Hello, Im children, My pid is " . posix_getpid() . PHP_EOL;
    $masterData = $process->read();
    echo "My master send data:$masterData to me." . PHP_EOL;
    
    $process->clearPipes();
    sleep(80);
}, true);

$process->setName("child php process.");

$pid = $process->run();

cli_set_process_title("parent php process.");

echo "I am father, my pid is " . posix_getpid() . ", my children is $pid" . PHP_EOL;

$process->write("Hello my child!");

Process::collect();