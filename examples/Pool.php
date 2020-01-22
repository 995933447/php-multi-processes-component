<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Worker;
use Bobby\MultiProcesses\Pool;

$worker = new Worker(0, function (Worker $worker) {
    $workerId = $worker->getWorkerId();

    while ($masterData = $worker->read()) {
        $worker->use();
        echo "I am worker:$workerId,My master send data:$masterData to me." . PHP_EOL;
        sleep(2);
        $worker->free();
        var_dump("worker id:$workerId using state:" . (int)$worker->isUsing());
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
    echo $msg =  "Master sending to worker:" . $worker->getWorkerId(), PHP_EOL;
    $worker->write($msg);
}

$pool->broadcast("broadcasting.");
sleep(10);

// while ($worker = $pool->getIdleWorker()) {
//     echo "poped:" . $worker->getWorkerId() . "\r\n";
//     $worker->write("\ ^ . ^ /");
// }

echo "finish\r\n";
// Pool::collect();