<?php
require __DIR__ . '/../vendor/autoload.php';

use Bobby\MultiProcesses\InterProcessShareMemory;


$shareMemory = new InterProcessShareMemory('test_');
$shareMemory->set(33, true);
var_dump($shareMemory->get(33));