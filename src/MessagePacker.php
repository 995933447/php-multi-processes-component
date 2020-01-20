<?php
namespace Bobby\MultiProcesses;

class MessagePacker
{
    public static function serialize($message): string
    {
        return serialize($message);
    }

    public static function unserialize($message): string
    {
        return unserialize($message);
    }
}