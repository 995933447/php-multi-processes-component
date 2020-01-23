<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Worker;
use Bobby\MultiProcesses\Pool;

$worker = new Worker(function (Worker $worker) {
    $workerId = $worker->getWorkerId();

    while ($masterData = $worker->read()) {
        $worker->use();
        echo "I am worker:$workerId,My master send data:$masterData to me." . PHP_EOL;
        sleep(2);
        $worker->free();
    }

    echo "Work:$workerId exit($masterData)" . PHP_EOL;
}, true);
$worker->setName('Pool worker');

$pool = new Pool(3, $worker);
$pool->getMinIdleWorkerNum(2);

declare(ticks = 1);
Pool::onCollect();

$pool->run();

$workersNum = $pool->getWorkersNum();
for ($i = 0; $i < $workersNum; $i++) {
    $worker = $pool->getWorker();
    $msg =  "Master sending to worker:" . $worker->getWorkerId();
    $worker->write($msg);
}

$pool->broadcast("broadcasting.");
sleep(5);

while ($worker = $pool->getIdleWorker()) {
    echo "poped:" . $worker->getWorkerId() . "\r\n";
    $worker->write("\ ^ . ^ /");
}

// Pool::collect();