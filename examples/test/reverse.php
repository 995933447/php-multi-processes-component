<?php
class Test
{
    public function reverse()
    {
       return $this->reverse();
    }
}

(new Test)->reverse(); // 内存泄露