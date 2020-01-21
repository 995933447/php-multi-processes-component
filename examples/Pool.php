<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Worker;
use Bobby\MultiProcesses\Pool;

$worker = new Worker(0, function (Worker $worker) {
    $workerId = $worker->getWorkerId();

    while ($masterData = $worker->read()) {
        $worker->asUsing();
        echo "I am worker:$workerId,My master send data:$masterData to me." . PHP_EOL;
        $worker->finish();
    }

    echo "Work:$workerId exit($masterData)" . PHP_EOL;
}, true);

$worker->setName('Pool worker');

$pool = new Pool(3, $worker);

declare(ticks = 1);
Pool::onCollect();

$pool->run();

$workersNum = $pool->getWorkersNum();
for ($i = 0; $i < $workersNum; $i++) {
    $worker = $pool->getWorker();
    echo $msg =  "Master sending to worker:" . $worker->getWorkerId(), PHP_EOL;
    $worker->write($msg);
}

sleep(1);

$pool->broadcast("broadcasting.");

echo "finish\r\n";
// Pool::collect();