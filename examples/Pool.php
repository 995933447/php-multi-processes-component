<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Worker;
use Bobby\MultiProcesses\Pool;

$times = 10;
$minIdleWorkersNum = 2;
$maxWorkerNum = 5;

$worker = new Worker(function (Worker $worker) use ($times, $minIdleWorkersNum) {
    $workerId = $worker->getWorkerId();
    $workTimes = 0;

    while ($masterData = $worker->read()) {
        // 将当前进程设置为任务进行中状态
        $worker->lock();

        $workTimes++;

        echo "I am worker:$workerId,My master send data:$masterData to me." . PHP_EOL;

        sleep(1);

        if ($workerId < $minIdleWorkersNum && $workTimes >= (2 + $times)) break;
        if ($workerId >= $minIdleWorkersNum && $workTimes >= $times) break;

        // 将当前进程设置为闲置可用状态
        $worker->free();
    }

    $worker->write("Worker:$workerId exited, finish work times: $workTimes." . PHP_EOL);
}, true);

$worker->setName('Pool worker');

$pool = new Pool($maxWorkerNum, $worker);

// 设置启动时最少可用worker进程数量。不设置的话则默认和进程池最大数量相同
$pool->setMinIdleWorkersNum($minIdleWorkersNum);

// PHP7意思可以异步监听子进程信号不需要声明TICKS
$pool->onCollect();

$pool->run();

$workersNum = $pool->getWorkersNum();
for ($i = 0; $i < $workersNum; $i++) {
    $worker = $pool->getWorker();
    $worker->write("Master sending to worker:" . $worker->getWorkerId());
}

$pool->broadcast("broadcasting.");

// sleep函数会被进程信号中断
// 此函数使调用进程被挂起，直到满足以下条件之一：
// 1)已经过了seconds所指定的墙上时钟时间
// 2)调用进程捕捉到一个信号并从信号处理程序返回
//var_dump(sleep(10));

$n = 0;
// 当发现进程池中没有可用闲置进程时 将动态fork出新的子进程知道到达进程池最大进程数量为止
while (1) {
    if (!$worker = $pool->getIdleWorker()) {
        continue;
    }

    echo "Using worker:" . $worker->getWorkerId() . PHP_EOL;

    $worker->write("\ ^ . ^ /");

    usleep(50000);

    $n++;
    $runningWorkersNum = $pool->getWorkersNum();
    if ($n >= $times * $runningWorkersNum) {
        echo "Master send total messages:$n.\n";
        break;
    }
}

while ($maxWorkerNum--) {
    echo ($worker = $pool->getWorker())->read();
    $worker->clearIpc();
}