<?php
namespace Bobby\MultiProcesses;

class MessagePacker
{
    protected $messageEof;

    protected $unpackMessages = '';

    protected $packedMessages = [];

    public function __construct(string $messageEof)
    {
        $this->messageEof = $messageEof;
    }

    public function hasMessageFromBuffer(): bool
    {
        return (bool)count($this->packedMessages);
    }

    public function getMessageFromBuffer(): string
    {
        return array_shift($this->packedMessages);
    }

    public function unpackToBuffer(string $message)
    {
        $this->unpackMessages .= $message;
        if (($lastEofPos = strrpos($this->unpackMessages, $this->messageEof)) !== false) {
            $ablePackMessage = mb_substr($this->unpackMessages, 0, ++$lastEofPos + ($eofLen = mb_strlen($this->messageEof)));
            $this->unpackMessages = mb_substr($this->unpackMessages, $lastEofPos + $eofLen);
            if (($messageNum = substr_count($ablePackMessage, $this->messageEof)) === 1) {
                $this->packedMessages[] = mb_substr($ablePackMessage, 0, $lastEofPos);
            };
            $this->packedMessages += explode($this->messageEof, $ablePackMessage, $messageNum);
        }
    }

    public function pack(string $message): string
    {
        return $message . $this->messageEof;
    }

    public static function serialize($message): string
    {
        return serialize($message);
    }

    public static function unserialize($message): string
    {
        return unserialize($message);
    }
}