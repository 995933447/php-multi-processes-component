<?php
declare(ticks=1);
// posxi_mkfifo 使用测试

// 普通文件可以同时对一个文件描述符进行读写,mkfifo只能用户进程间通信。必须一个进程只能进行读操作（文件描述符操作方式为r，r+,w+,a+均不合法）或写操作（文件描述符操作方式为w，r+,w+,a+均不合法）其中之一
$nomalFile = '1.txt';
$normalFd = fopen($nomalFile, 'w+');
fwrite($normalFd,1234);
fseek($normalFd, 0);
$content = fread($normalFd, 1024);
var_dump($content);
echo "normal file content: $content" . PHP_EOL;

cli_set_process_title("Test fifo");
$fifoFile = 'queue';
if (!file_exists($fifoFile))
    if (!posix_mkfifo($fifoFile, 0666))
        die('make fifo fail.' . PHP_EOL);        

if (($pid = pcntl_fork()) < 0) {
    die('fork fail.');
}

// pcntl_mkfifo 相当于创建UNIX的有名管道，所以没有读端，是不可写的
// if ($pid > 0) {
//     pcntl_signal(SIGCHLD, SIG_IGN);
//     $wfd = fopen($fifoFile, 'w');
//     echo "parent write start." . PHP_EOL;
//     fwrite($wfd, 'master sended.'); // 这里会一直阻塞，英文没有读端
//     echo "parent write finish." . PHP_EOL;
// } else {
//     echo "child exit." . PHP_EOL;
//     exit(0);
// }

// if ($pid > 0) {
//     pcntl_signal(SIGCHLD, SIG_IGN);
//     $wfd = fopen($fifoFile, 'w');
//     echo "parent write start." . PHP_EOL;
//     fwrite($wfd, 'master sended.'); // 这里可以写成功，因为有读端
//     echo "parent write finish." . PHP_EOL;
// } else {
//     $rfd = fopen($fifoFile, 'r');
//     echo "child exit." . PHP_EOL;
//     exit(0);
// }

// 这里$wfd = fopen($fifoFile, 'w') 或者 $rfd = fopen($fifoFile, 'r') 则塞了一直不往下执行,使用其他打开模式如w+会产生不可预知的后果, 只能在多进程间打开
// $rfd = fopen($fifoFile, 'r');

if ($pid > 0) {
    pcntl_signal(SIGCHLD, SIG_IGN);

    $wfd = fopen($fifoFile, 'w');
    echo "parent write start." . PHP_EOL;
    fwrite($wfd, 'master sended.'); // 这里可以写成功，因为有读端
    echo "parent write finish." . PHP_EOL;
} else if ($pid == 0) {
    $rfd = fopen($fifoFile, 'r');
    sleep(1); // 保证写端可以已经写入
    
    stream_set_blocking($rfd, false);
    $content = stream_get_contents($rfd);
    echo "child read:$content" . PHP_EOL;
    fclose($rfd);
    
    echo "child exit." . PHP_EOL;
    exit(0);
}

sleep(5);
echo "fork 2" . PHP_EOL;
if (($pid = pcntl_fork()) < 0) {
    die('fork fail.');
}

if ($pid > 0) {
    // 这里写入后并没有被读取到
    echo "parent write start2." . PHP_EOL;
    fwrite($wfd, 'master sended2.');
    echo "parent write finish2." . PHP_EOL;
    sleep(5);

    // 这里写入可以读到,所以fifo在不同的进程间通信必须是r端和w端每次都是一次性成对打开的,配对使用的.非配对打开的端口无法进行通信
    $wfd = fopen($fifoFile, 'w');
    echo "parent write start3." . PHP_EOL;
    fwrite($wfd, 'master sended3.');
    echo "parent write finish3." . PHP_EOL;
} else if ($pid == 0) {
    echo "child 2 open read" . PHP_EOL;
    $rfd = fopen($fifoFile, 'r');

    echo "child 2 start read." . PHP_EOL;
    $reads = $writes = $exceps = [];
    $reads[] = $rfd;
    stream_set_blocking($rfd, false);
    if (stream_select($reads, $writes, $exceps, null)) {
        $content = stream_get_contents($rfd);
    }
    echo "child 2 read:$content" . PHP_EOL;

    echo "child 2 exit." . PHP_EOL;
    exit(0);
}

sleep(3);

echo "fork 3" . PHP_EOL;
if (($pid = pcntl_fork()) < 0) {
    die('fork fail.');
}

if ($pid > 0) {
    $wfd = fopen($fifoFile, 'w');
    echo "parent write start4." . PHP_EOL;
    fwrite($wfd, 'master sended3.');
    echo "parent write finish4." . PHP_EOL;

    echo "parent write start5." . PHP_EOL;
    fwrite($wfd, 'master sended5.');
    echo "parent write finish5." . PHP_EOL;

    echo "parent write start6." . PHP_EOL;
    fwrite($wfd, 'master sended6.');
    echo "parent write finish6." . PHP_EOL;

    sleep(1);
    echo "parent write start7." . PHP_EOL;
    fwrite($wfd, 'master sended7.');
    echo "parent write finish7." . PHP_EOL;

} else if ($pid == 0) {
    echo "child 3 open read" . PHP_EOL;
    $rfd = fopen($fifoFile, 'r');
    echo "child 3 start read." . PHP_EOL;
    $content = fread($rfd, 10);
    echo "child 3 read:$content" . PHP_EOL;
    echo "child 3 exit." . PHP_EOL;
    
    echo 'child 3 fork.' . PHP_EOL;
    if (($pid = pcntl_fork()) < 0) {
        die('child 3 fork failed.');
    }

    // 子进程继承父进程的管道端口可以继续用来读写
    if ($pid > 0) {
        echo "child 3.1 start read." . PHP_EOL;
        $content = fread($rfd, 20);
        echo "child 3.1 read:$content" . PHP_EOL;
        echo "child 3.1 exit." . PHP_EOL;
       
        echo "child 3.1 start read2." . PHP_EOL; 
        $reads = $writes = $exceps = [];
        $reads[] = $rfd;
        stream_set_blocking($rfd, false);
        if (stream_select($reads, $writes, $exceps, null)) {
            $content = stream_get_contents($rfd);
        }
        echo "child 3.1 read2:$content" . PHP_EOL;

        echo 'child 3.1 fork.' . PHP_EOL;
        if (($pid = pcntl_fork()) < 0) {
            die('child 3.1 fork failed.');
        }

        if ($pid == 0) {
            echo "child 3.1.1 start read2." . PHP_EOL; 
            $reads = $writes = $exceps = [];
            $reads[] = $rfd;
            stream_set_blocking($rfd, false);
            if (stream_select($reads, $writes, $exceps, null)) {
                $content = stream_get_contents($rfd);
            }
            echo "child 3.1.1 read2:$content" . PHP_EOL;
        }
    }
}

