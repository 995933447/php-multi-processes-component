<?php
namespace Bobby\MultiProcesses;

class Quit
{
    public static function exceptionQuit()
    {
        exit(-1);
    }

    public static function normalQuit()
    {
        exit(0);
    }
}