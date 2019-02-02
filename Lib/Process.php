<?php
/**
 * @author sivan
 * @description 进程管理
 */
namespace Lib;

class Process
{
    public static $mpid = 0;

    public function __construct()
    {

    }

    // 创建并启动进程
    public static function start()
    {
        swoole_set_process_name(sprintf('php-ps:%s', 'master'));
        static::$mpid = posix_getpid();
    }

    // 进程等待
    public static function wait()
    {
        \swoole_process::wait();
    }

    /**
     * 杀掉进程
     *
     * @param $server_id
     * @param int $n
     * @return bool
     */
    public static function kill($server_id, $n = 15)
    {
        if (posix_kill($server_id, $n)) {
            return true;
        }

        return false;
    }

    /**
     * 进程守护执行
     */
    public static function daemon()
    {
        \swoole_process::daemon();
    }

    /**
     * 检测进程是否存在
     *
     * @return bool
     */
    public static function processCheckExist()
    {
        $ret = system("ps aux | grep " . SUPER_PROCESS_NAME . " | grep -v grep ");
        if (empty($ret)) {
            return false;
        } else {
            return true;
        }
    }
}
