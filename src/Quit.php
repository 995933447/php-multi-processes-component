<?php
namespace Bobby\MultiProcesses;

/** 脚本退出封装处理类
 * Class Quit
 * @package Bobby\MultiProcesses
 */
class Quit
{
    /**
     *  脚本异常退出
     */
    public static function exceptionQuit()
    {
        exit(-1);
    }

    /**
     *  脚本正常退出
     */
    public static function normalQuit()
    {
        exit(0);
    }
}