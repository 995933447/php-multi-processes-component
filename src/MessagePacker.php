<?php
namespace Bobby\MultiProcesses;

/** 进程消息传递处理类
 * Class MessagePacker
 * @package Bobby\MultiProcesses
 */
class MessagePacker
{
    protected $messageEof;

    protected $unpackMessages = '';

    protected $packedMessages = [];

    public function __construct(string $messageEof)
    {
        $this->messageEof = $messageEof;
    }

    /** 获取消息结束边界符
     * @return string
     */
    public function getMessageEof(): string
    {
        return $this->messageEof;
    }

    /** 检查缓冲中是否有未读消息
     * @return bool
     */
    public function hasMessageFromBuffer(): bool
    {
        return (bool)count($this->packedMessages);
    }

    /** 冲缓存中返回未读消息
     * @return string
     */
    public function getMessageFromBuffer(): string
    {
        return array_shift($this->packedMessages);
    }

    /** 对消息进行拆包并放入到缓存中
     * @param string $message
     */
    public function unpackToBuffer(string $message)
    {
        $this->unpackMessages .= $message;
        
        if (($lastEofPos = strrpos($this->unpackMessages, $messageEof = $this->getMessageEof())) !== false) {
            $ablePackMessage = mb_substr($this->unpackMessages, 0, ++$lastEofPos + ($eofLen = mb_strlen($messageEof)));
            $this->unpackMessages = mb_substr($this->unpackMessages, $lastEofPos + $eofLen);
            if (($messageNum = substr_count($ablePackMessage, $messageEof)) === 1) {
                $this->packedMessages[] = mb_substr($ablePackMessage, 0, $lastEofPos - 1);
            } else {
                $this->packedMessages += explode($messageEof, $ablePackMessage, $messageNum);
            }
        }
    }

    /** 对消息添加结束边界符
     * @param string $message
     * @return string
     */
    public function pack(string $message): string
    {
        return $message . $this->getMessageEof();
    }

    /** 对非字符串消息进行序列化
     * @param $message
     * @return string
     */
    public static function serialize($message): string
    {
        return serialize($message);
    }

    /** 对消息进行反序列化
     * @param $message
     * @return string
     */
    public static function unserialize($message): string
    {
        return unserialize($message);
    }
}