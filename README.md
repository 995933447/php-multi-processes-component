### 子进程管理类
\Bobby\MultiProcesses\Process\
\
快速入门:
```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Ipcs\IpcFactory;
use Bobby\MultiProcesses\Process;

$process = new Process(function (Process $process) {
    echo "Hello, Im children, My pid is " . $process->getPid() . PHP_EOL . PHP_EOL;

    $masterData = $process->read();
    echo "My master send data:$masterData to me." . PHP_EOL . PHP_EOL;

    $masterData = $process->read();
    echo "My master send data2:$masterData to me." . PHP_EOL . PHP_EOL;

    $masterData = $process->read();
    echo "My master send data3:$masterData to me." . PHP_EOL . PHP_EOL;

    $process->clearIpc();
    
    echo "exit " . $process->getPid() . PHP_EOL;
}, true, IpcFactory::UNIX_SOCKET_IPC);

$process->setName("child php process.");

cli_set_process_title("parent php process.");

declare(ticks = 1);
// 信号注册的时机要合适 因为如果产生子信号 而这个时候父进程还没有注册处理器 PHP就会使用系统默认的信号处理器
Process::onCollect();

$processes = [];
for ($i = 0; $i < 4; $i++) {
    $processCloned = clone $process;
    $pid = $processCloned->run();
    echo "I am father, my pid is " . posix_getpid() . ", my children is $pid" . PHP_EOL . PHP_EOL;
    $processCloned->write("Hello my child!");
    $processCloned->write('Hello my child 2!');
    $processes[] = $processCloned;
}

foreach ($processes as $process) {
    $process->write('Hello my child 3!');
}

// Process::collect();
```
public \Bobby\MultiProcesses\Process::__construct(callable $callback, bool $isDaemon = false, int $ipcType = \Bobby\MultiProcesses\Ipcs\IpcFactory::UNIX_SOCKET_IPC)\
定义子进程\
$callback 子进程启动时执行该方法\
$isDaemon 子进程是否设置为守护进程,true代表设置为守护模式,false为否\
$ipcType 进程间通信方式,IpcFactory::UNIX_SOCKET_IPC为unix socket方式,默认方式.IpcFactory::PIPES_IPC为有名管道方式

public \Bobby\MultiProcesses\Process::run()\
执行子fork操作,创建并启动子进程

public \Bobby\MultiProcesses\Process::gePid(): int\
获取子进程PID,必须在run后执行

public \Bobby\MultiProcesses\Process::setName(string $name)\
设置进程名称\
$name 进程名称

public \Bobby\MultiProcesses\Process::getName(): string\
获取子上次setName方法设置,不一定是真实进程名称.如setName操作后执行了cli_set_title或者通过其他接口更改了进程名称，则getName获取的是上次setName的名称。

public \Bobby\MultiProcesses\Process::getRealName(): string\
获取真实的进程名称

public \Bobby\MultiProcesses\Process::write($message)\
如果父进程调用该方法则往子进程写入消息,如果子进程调用该方法则往父进程写入消息\
$message 消息,可以为任意数据类型,该方法会自动序列化消息

public \Bobby\MultiProcesses\Process::writeString(string $message)\
如果父进程调用该方法则往子进程写入消息,如果子进程调用该方法则往父进程写入消息\
$message 字符串类型消息,该方法效率比write效率高,因为不会对消息进行序列化处理

public \Bobby\MultiProcesses\Process::read()\
如果父进程调用该方法则往子进程读取消息,如果子进程调用该方法则往父进程读取消息,只能读取write方法写入的消息\

public \Bobby\MultiProcesses\Process::writeString(string $message)\
如果父进程调用该方法则往子进程读取消息,如果子进程调用该方法则往父进程读取消息,只能读取writeString写入的消息\

public \Bobby\MultiProcesses\Process::closeIpc()\
关闭当前进程通信通道,关闭后当前进程无法进行读写.

public \Bobby\MultiProcesses\Process::clearIpc()\
释放父子进程双方通信通道资源.

public static \Bobby\MultiProcesses\Process::onCollect($callback = null)\
安装子进程退出时异步回调的信号处理器,当子进程退出时将触发该信号处理器\
$callback 为NULL时组件将自动回收子进程资源避免成为僵尸进程.如为自定义回调函数时,子进程退出时将触发自定义回调函数,你需要编写逻辑手动回收进程资源.你也可以使用php的pcntl_signal.该方法就是基于pcntl_signal封装实现的.
注意:使用该方法后一定要在父进程declare(ticks = 1)或者在脚本尾部使用\Bobby\MultiProcesses\Process::collect进行监信号,否则子进程不会触发。

public static \Bobby\MultiProcesses\Process::collect()
阻塞监听子进程信号,该方法会一直导致脚本阻塞,需要手动中断脚本退出

### 进程池
进程池的实现需要两个类来配合实现。Pool进程池管理类，Worker子进程管理类

快速入门:
```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\Worker;
use Bobby\MultiProcesses\Pool;

$worker = new Worker(function (Worker $worker) {
    $workerId = $worker->getWorkerId();
    $requestTime = 0;

    while ($masterData = $worker->read()) {
        // 将当前进程设置为任务进行中状态
        $worker->use();
        $requestTime++;
        echo "I am worker:$workerId,My master send data:$masterData to me." . PHP_EOL;
        sleep(2);
        if ($requestTime >= 100) break;
        // 将当前进程设置为闲置可用状态
        $worker->free();
    }

    echo "Work:$workerId exit($masterData)" . PHP_EOL;
}, true);
$worker->setName('Pool worker');

$pool = new Pool(5, $worker);
// 设置启动时最少可用worker进程数量。不设置的话则默认和进程池最大数量相同
$pool->setMinIdleWorkersNum(2);

declare(ticks = 1);
$pool->onCollect();

$pool->run();

$workersNum = $pool->getWorkersNum();
for ($i = 0; $i < $workersNum; $i++) {
    $msg =  "Master sending to worker:" . $worker->getWorkerId();
    $pool->getWorker()->write($msg);
}

$pool->broadcast("broadcasting.");

// 这里和功能示例无关,只是为了说明在多进程中使用sleep会碰到的意外情况.
// sleep函数会被进程信号中断
// 此函数使调用进程被挂起，直到满足以下条件之一：
// 1)已经过了seconds所指定的墙上时钟时间
// 2)调用进程捕捉到一个信号并从信号处理程序返回
echo "Sleep函数被打断的剩余时间:" . sleep(10);

$n = 0;
// 当发现进程池中没有可用闲置进程时 将动态fork出新的子进程知道到达进程池最大进程数量为止
while (1) {
    if (!$worker = $pool->getIdleWorker()) {
        continue;
    }
    echo "poped:" . $worker->getWorkerId() . PHP_EOL;
    $worker->write("\ ^ . ^ /");
    sleep(1);
    $n++;
    echo "Workers num:" . ($runningWorkersNum = $pool->getWorkersNum()) . PHP_EOL;
    if ($n >= 100 * $runningWorkersNum) {
        var_dump($n);
        break;
    }
}

//Pool::collect();
```

\Bobby\MultiProcesses\Worker\
该类继承自\Bobby\MultiProcesses\Process类.只是方法和\Bobby\MultiProcesses\Process基本一致.使用拓展了一些方法配合\Bobby\MultiProcesses\Pool类一起工作.\
\
public \Bobby\MultiProcesses\Worker::__construct(callable $callback, bool $isDaemon = false, int $ipcType = \Bobby\MultiProcesses\Ipcs\IpcFactory::UNIX_SOCKET_IPC, int $workerId = 0)\
定义子一个worker进程\
$callback 子进程启动时执行该方法.\
$isDaemon 子进程是否设置为守护进程,true代表设置为守护模式,false为否.\
$ipcType 进程间通信方式,IpcFactory::UNIX_SOCKET_IPC为unix socket方式,默认方式.IpcFactory::PIPES_IPC为有名管道方式.\
$workerId 进程池中,Worker工作单元标识.不同于Pid.用于配合Pool类使用,稍后将进行说明.

public \Bobby\MultiProcesses\Worker::getWorkerId(): int\
获取worker id.

public \Bobby\MultiProcesses\Worker::use()\
将当前worker进程设置为繁忙状态.配合Pool类使用,稍后将做说明.

public \Bobby\MultiProcesses\Worker::free()\
将当前worker进程设置为空闲状态.配合Pool类使用,稍后将做说明.

public \Bobby\MultiProcesses\Worker::isUsing(): bool\
判断worker进程是否为繁忙状态.

\Bobby\MultiProcesses\Pool\
进程池管理类.

public \Bobby\MultiProcesses\Pool::__construct(int $maxWorkersNum, \Bobby\MultiProcesses\Worker $worker, $poolId = __ CLASS __)\
定义一个进程池.\
$maxWorkersNum 进程池最大允许进程数量.\
$worker  \Bobby\MultiProcesses\Worker对象.进程池将根据传入Worker对象的worker ID为起点,为创建的进程递增复制workerID.\
$poolId 进程池ID.
```php
// worker id 默认为0
$worker = new Worker(function (Worker $worker) {
    $workerId = $worker->getWorkerId();
    $requestTime = 0;

    while ($masterData = $worker->read()) {
        // 将当前进程设置为任务进行中状态
        $worker->use();
        $requestTime++;
        echo "I am worker:$workerId,My master send data:$masterData to me." . PHP_EOL;
        sleep(2);
        if ($requestTime >= 100) break;
        // 将当前进程设置为闲置可用状态
        $worker->free();
    }

    echo "Work:$workerId exit($masterData)" . PHP_EOL;
}, true);
$worker->setName('Pool worker');

// 设置最大允许产生5个进程,5个进程的worker ID分别是0,1,2,3,4.
$pool = new Pool(5, $worker);
```
public \Bobby\MultiProcesses\Pool::setMinIdleWorkersNum(int $num)\
设置进程池实际运行时的worker进程数量.配合public \Bobby\MultiProcesses\Pool::getIdleWorker()使用.当所有worker都处于繁忙状态,进程池将动态fork出新的worker进程知道到达最大允许进程数量.如果调用该方法设置.则默认值为最大允许进程数量,由构造函数传入.

\Bobby\MultiProcesses\Pool::getIdleWorker(): null|\Bobby\MultiProcesses\Worker\
获取空闲状态的进程对象.通常用于给进程池均衡负载地分发任务执行.

\Bobby\MultiProcesses\Pool::getWorker(): \Bobby\MultiProcesses\Worker\
轮询获取worker进程对象.和getIdleWorker方法不同的是该方法仅轮询获取进程池存在的worker进程对象,而不管worker对象是否为繁忙状态,也不会导致进程池动态fork进程.

public \Bobby\MultiProcesses\Pool::getWorkersNum(): int\
获取当前进程池的进程数量.

public \Bobby\MultiProcesses\Pool::getMaxWorkersNum(): int\
获取进程池最大允许进程数量.

public \Bobby\MultiProcesses\Pool::getMinIdleWorkersNum(): int\
获取进程池初始化允许时的进程数量.

public \Bobby\MultiProcesses\Pool::run()\
启动进程池.fork work进程并执行worker进程对象设置的回调.

public \Bobby\MultiProcesses\Pool::broadcast($message)\
往进程池的所有进程广播消息.\
$message 任意数据类型.该方法将自动序列化$message.worker进程需要用read方法接收消息.

public \Bobby\MultiProcesses\Pool::broadcastString($message)\
往进程池的所有进程广播消息(仅允许字符串类型).效率比broadcast高,因为该方法不会对消息进行序列化.worker进程需要用readString方法接收消息.

public \Bobby\MultiProcesses\Pool::onCollect($callback = null)\
注册子进程信号处理器\
$callback 回收子进程前触发的回调函数.回调里请不要写子进程回收逻辑,该方法执行完$callback后将自动回收子进程资源并释放相应进程池内于进程相关的资源,和\Bobby\MultiProcesses\Process::onCollect方法不同.\
注意:使用该方法后一定要在父进程declare(ticks = 1)或者在脚本尾部使用\Bobby\MultiProcesses\Process::collect进行监信号,否则子进程不会触发.

public static \Bobby\MultiProcesses\Pool::collect()
阻塞监听子进程信号,该方法会一直导致脚本阻塞,需要手动中断脚本退出
