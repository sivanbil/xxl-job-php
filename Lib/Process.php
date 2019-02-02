<?php
/**
 * @author sivan
 * @description 进程管理
 */
namespace Lib;

class Process
{
    public static $mpid = 0;

    /**
     * @param $server_info
     * @return bool
     */
    public static function start($conf_info)
    {
        // 创建并启动进程
        $process = new \swoole_process(function (\swoole_process $worker) use ($conf_info) {
            $worker->exec($conf_info['server']['php'], [SRC_PATH . "/index.php", json_encode($conf_info)]);
        }, false);
        self::$mpid = $process->start();

        $wait_res = \swoole_process::wait();
        if ($wait_res['code']) {
            echo  "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
            exit;
        }
        return true;
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
