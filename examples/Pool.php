<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Worker;
use Bobby\MultiProcesses\Pool;

$worker = new Worker(function (Worker $worker) {
    $workerId = $worker->getWorkerId();

    while ($masterData = $worker->read()) {
        // 将当前进程设置为任务进行中状态
        $worker->use();
        echo "I am worker:$workerId,My master send data:$masterData to me." . PHP_EOL;
        sleep(3);
        // 将当前进程设置为闲置可用状态
        $worker->free();
    }

    echo "Work:$workerId exit($masterData)" . PHP_EOL;
}, true);
$worker->setName('Pool worker');

$pool = new Pool(4, $worker);
// 设置启动时最少可用worker进程数量。不设置的话则默认和进程池最大数量相同
$pool->setMinIdleWorkerNum(2);

declare(ticks = 1);
Pool::onCollect();

$pool->run();

$workersNum = $pool->getWorkersNum();
for ($i = 0; $i < $workersNum; $i++) {
    $msg =  "Master sending to worker:" . $worker->getWorkerId();
    $pool->getWorker()->write($msg);
}

$pool->broadcast("broadcasting.");

// sleep函数会被进程信号中断
// 此函数使调用进程被挂起，直到满足以下条件之一：
// 1)已经过了seconds所指定的墙上时钟时间
// 2)调用进程捕捉到一个信号并从信号处理程序返回
var_dump(sleep(10));

$n = 0;
// 当发现进程池中没有可用闲置进程时 将动态fork出新的子进程知道到达进程池最大进程数量为止
while ($worker = $pool->getIdleWorker()) {
    echo "poped:" . $worker->getWorkerId() . "\r\n";
    $worker->write("\ ^ . ^ /");
    $n++;
    sleep(1);
    if ($n > 2000) break;
}

Pool::collect();