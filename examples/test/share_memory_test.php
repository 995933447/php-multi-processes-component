<?php
// 共享内存方案使用测试

// $shm_key = ftok(__FILE__, 't');
// $shm_id = shmop_open($shm_key, "c", 0644, 100);

if (($pid = pcntl_fork()) < 0) {
    die('Fork fail.');
}
if ($pid > 0) {
    // apcu可以再父子进程间共享内存,非父子进程不行
    // apcu_store('foo', 'bar');

//    $shm_key = ftok(__FILE__, 't');
//    $shm_id = shmop_open($shm_key, "c", 0644, 100);
    // shm模块可以垮进程跨脚本实现真正的共享内存
//    $shm_bytes_written = shmop_write($shm_id, "en", 0);
//    $shm_data = shmop_read($shm_id, 0, 50);
//    var_dump($shm_data);

    $shm_key2 = ftok(__FILE__, 'x');
    $shm_id2 = shm_attach($shm_key2);
    shm_put_var($shm_id2, 1, "hello world");
    shm_put_var($shm_id2, sprintf("%x", bin2hex("hello world")), "hello world2");
} else {
    sleep(1);
    var_dump(apcu_fetch('foo'));

//    $shm_key = ftok(__FILE__, 't');
//    $shm_id = shmop_open($shm_key, "c", 0644, 100);
//    $shm_data = shmop_read($shm_id, 0, 50);
//    var_dump($shm_data);

    $shm_key2 = ftok(__FILE__, 'x');
    $shm_id2 = shm_attach($shm_key2);
    $shm_data = shm_get_var($shm_id2, 1);
    var_dump($shm_data);
    $shm_data = shm_get_var($shm_id2, sprintf("%x", bin2hex("hello world")));
    var_dump($shm_data);
    exit(0);
}