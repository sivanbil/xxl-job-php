<?php
/**
 *
 * @author sivan
 * @description 进程管理
 */
namespace Lib\Executor;

use Lib\Core\Client;
use Lib\Core\Process;

class CmdProcess
{
    /**
     * @param $confInfo
     * @param string $cmd
     * @return bool
     */
    public static function execute($confInfo, $cmd = 'start')
    {
        // 创建并启动进程
        $process = new Process(function (Process $worker) use ($confInfo, $cmd) {
            // 启动进程守护
            $worker->exec($confInfo['server']['php'], [SRC_PATH . "/index.php", json_encode($confInfo), $cmd]);
        }, false);
        $process->start();
        // 结束的子进程回收
        $wait_res = Process::wait();
        if ($wait_res['code']) {
            echo  "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
            return false;
        }
        return true;
    }

    /**
     * 发信号
     *
     * @param $conf_info
     * @param $cmd
     * @return bool
     */
    public static function sendSignal($conf_info, $cmd)
    {
        $client = new Client(SWOOLE_SOCK_TCP);
        $client->connect($conf_info['server']['ip'], $conf_info['server']['port']);

        return true;
    }

    /**
     * 杀掉进程
     *
     * @param $serverId
     * @param int $n
     * @return bool
     */
    public static function kill($serverId, $n = 15)
    {
        if (posix_kill($serverId, $n)) {
            return true;
        }

        return false;
    }

    /**
     * 进程守护执行
     */
    public static function daemon()
    {
        Process::daemon();
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
