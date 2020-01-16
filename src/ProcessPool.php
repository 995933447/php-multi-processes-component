<?php
namespace Bobby\MultiProcesses;

use Bobby\MultiProcesses\Process;
use Bobby\MultiProcesses\ProcessException;

class ProcessPool
{
    protected $maxProcessesNum;

    public function __construct(int $maxProcessesNum)
    {
        $this->maxProcessesNum = $maxProcessesNum;
    }
}