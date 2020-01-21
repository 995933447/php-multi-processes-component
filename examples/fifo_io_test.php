<?php
$fifoFile = 'fifo_queue';

posix_mkfifo($fifoFile, 0666);

if ($pid = pcntl_fork()) {
   sleep(3);
   $fsrc = fopen($fifoFile, 'w');
   fwrite($fsrc, 123);
   fwrite($fsrc, 456);
   fwrite($fsrc, 789);
} else {
   $fsrc = fopen($fifoFile, 'r');
    $reads = $writes = $excepts = [];
    while (1) {
        $reads[] = $fsrc;
        if (stream_select($reads, $writes, $excepts, null)) {
            if ($content = stream_get_contents($fsrc)) {
                var_dump($content);
                break;
            };
        }
    }
}

sleep(5);