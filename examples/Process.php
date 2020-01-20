<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Process;

$process = new Process(function (Process $process) {
    echo "Hello, Im children, My pid is " . posix_getpid() . PHP_EOL;
    $masterData = $process->read();
    echo "My master send data:$masterData to me." . PHP_EOL . PHP_EOL;
    
    $masterData = $process->read();
    echo "My master send data2:$masterData to me." . PHP_EOL . PHP_EOL;

    $masterData = $process->read();
    echo "My master send data3:$masterData to me." . PHP_EOL;

    $process->clearPipes();
    
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
    echo "I am father, my pid is " . posix_getpid() . ", my children is $pid" . PHP_EOL;
    $processCloned->write("Hello my child!");
    sleep(1);
    $processCloned->write('uuu');
    $processes[] = $processCloned;
}

foreach ($processes as $process) {
    sleep(1);
    $process->write('Uuu2');
}

// Process::collect();
sleep(5);