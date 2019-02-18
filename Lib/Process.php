<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/13
 * Time: 2:15 PM
 */

namespace Lib;


class Process extends \Swoole\Process
{

    public static function shutdown($return)
    {
        //先杀掉所有的run server
        foreach ($return['data'] as $server) {
            // array('php'=>,'name'=)
            $ret = system("ps aux | grep " . $server['name'] . " | grep -v grep ");
            preg_match('/\d+/', $ret, $match);//匹配出来进程号
            $server_id = $match['0'];
            if (posix_kill($server_id, 15)) {//如果成功了
                echo 'stop ' . $server['name'] . "\033[32;40m [SUCCESS] \033[0m" . PHP_EOL;
            } else {
                echo 'stop ' . $server['name'] . "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
            }
        };
        //然后开始杀Swoole-Controller
        $ret = system("  ps aux | grep " . SUPER_PROCESS_NAME . " | grep -v grep");
        preg_match('/\d+/', $ret, $match);
        $server_id = $match['0'];
        if (posix_kill($server_id, 15)) {//如果成功了
            echo 'stop ' . SUPER_PROCESS_NAME . "\033[32;40m [SUCCESS] \033[0m" . PHP_EOL;
        } else {
            echo 'stop ' . SUPER_PROCESS_NAME . "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
        }
    }
}