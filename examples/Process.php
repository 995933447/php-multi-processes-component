<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Ipcs\IpcFactory;
use Bobby\MultiProcesses\Process;

$process = new Process(function (Process $process) {
    echo "Hello, Im children, My pid is " . $process->getPid() . PHP_EOL;

    $masterData = $process->read();
    echo "My master send data:$masterData to me." . PHP_EOL . PHP_EOL;

    $masterData = $process->read();
    echo "My master send data2:$masterData to me." . PHP_EOL . PHP_EOL;

    $masterData = $process->read();
    echo "My master send data3:$masterData to me." . PHP_EOL;

    $process->clearIpc();
    
    echo "exit " . posix_getpid() . PHP_EOL;
}, true);

$process->setName("child php process.");

cli_set_process_title("parent php process.");
declare(ticks = 1);
// 信号注册的时机要合适 因为如果产生子信号 而这个时候父进程还没有注册处理器 PHP就会使用系统默认的信号处理器
Process::onCollect();

$processes = [];
for ($i = 0; $i < 4; $i++) {
    $processCloned = clone $process;
    $pid = $processCloned->run();
    var_dump($processCloned->getPid());
    echo "I am father, my pid is " . posix_getpid() . ", my children is $pid" . PHP_EOL;
    $processCloned->write("Hello my child!");
    $processCloned->write('u;uu');
    $processes[] = $processCloned;
}

foreach ($processes as $process) {
    $process->write('U;uu2');
}

// Process::collect();