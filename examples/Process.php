<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Process;

$process = new Process(function (Process $process) {
    echo "Hello, Im children, My pid is " . ($pid = $process->getPid()) . PHP_EOL . PHP_EOL;

    // 阻塞等待数据
    $masterData = $process->read();
    echo "My master send data:$masterData to me." . PHP_EOL . PHP_EOL;

    // 阻塞等待数据
    $masterData = $process->read();
    echo "My master send data2:$masterData to me." . PHP_EOL . PHP_EOL;

    // 阻塞等待数据
    $masterData = $process->read();
    echo "My master send data3:$masterData to me." . PHP_EOL . PHP_EOL;

    // 关闭进程间通信和释放进程间通信资源
    $process->clearIpc();

    echo "exit $pid" . PHP_EOL;
}, true);

// 设置子进程名称
$process->setName("child php process.");

// 设置主进程名称
cli_set_process_title("parent php process.");

declare(ticks = 1); // PHP7支持异步监听信号，可不声明TICK
// 信号注册的时机要合适 因为如果产生子信号 而这个时候父进程还没有注册处理器 PHP就会使用系统默认的信号处理器。
Process::onCollect();

$processes = [];
for ($i = 0; $i < 6; $i++) {
    $processCloned = clone $process;
    $pid = $processCloned->run();
    echo "I am father, my pid is " . posix_getpid() . ", my children is $pid" . PHP_EOL . PHP_EOL;
    $processes[] = $processCloned;
}

foreach ($processes as $process) {
    $process->write("Hello my child!");
    $process->write('Hello my child 2!');
    $process->write('Hello my child 3!');
}

// Process::collect();