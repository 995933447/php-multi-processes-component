<?php
$tmpfile = tmpfile();
$meta = stream_get_meta_data($tmpfile);

$pid  = pcntl_fork();
$tmpfile2 = fopen($meta['uri'], 'w');
if ($pid > 0) {
    var_dump("master", flock($tmpfile2, LOCK_EX|LOCK_NB));
} else {

//    var_dump();
    var_dump(is_writable($meta['uri']));
    var_dump("child", flock($tmpfile2, LOCK_EX));
}

sleep(5);
echo "exit.\n";