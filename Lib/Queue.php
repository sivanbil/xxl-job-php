<?php
/**
 * 队列支持
 *
 * User: sivan
 * Date: 2019/2/1
 * Time: 3:26 PM
 */
namespace Lib;

class Queue
{
    // 超时设置
    public $timeout = 30;

    // 阻塞模式
    public $blocking = true;

    public $queue = NULL;

    public $pid;

    public function __construct()
    {
        $this->queue = new \swoole_process(function(\swoole_process $worker) {
            $worker->exec('/usr/local/bin/php', array(__DIR__.'/swoole_server.php'));
        }, true);
    }

}
