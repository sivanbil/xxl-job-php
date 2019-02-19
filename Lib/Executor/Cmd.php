<?php
/**
 * 命令行启动守护进程
 *
 * User: sivan
 * Date: 2019/2/1
 * Time: 11:08 AM
 */
namespace Lib\Executor;

use Lib\Common\Code;
use Lib\Common\JobTool;

class Cmd
{
    use JobTool;

    public static $running_server;
    /**
     * 需要支持以下几种命令
     * start    启动
     * stop     停止
     * reload   重载
     * restart  重启
     * shutdown 关闭
     * status   查看状况
     * list     列表
     * startAll 启动所有
     */

    public static function exec($cmd, $name)
    {
        $server_info = self::getServerIni($name);

        $biz_center = null;
        if (!empty($server_info['conf']['xxljob']['open_registry'])) {
            $biz_center = new BizCenter($server_info['conf']['xxljob']['host'], $server_info['conf']['xxljob']['port']);
        }
        // 进程检测
        if (CmdProcess::processCheckExist()) {
            // reload stop restart
            $return = self::sendCmdToSock($cmd, $name);

            if ($cmd == 'stop') {

                // 调度中心rpc实例化
                $time = self::convertSecondToMicroS();
                if ($biz_center && !empty($server_info['conf']['xxljob']['open_registry'])) {
                    $biz_center->open_registry = $server_info['conf']['xxljob']['open_registry'];
                    // 先移除
                    $biz_center->registryRemove($time, $server_info['conf']['server']['app_name'], $server_info['conf']['server']['ip'] . ':' . $server_info['conf']['server']['port']);
                }

                //获取status 之后去杀掉进程
                if ($return['code'] == Code::SUCCESS_CODE) {
                    //先杀掉所有的run server
                    if (empty($return['data'])) {
                        echo 'shutdown' . "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
                        exit;
                    }

                    $ret = system("  ps aux | grep " . SUPER_PROCESS_NAME . " | grep -v grep");
                    preg_match('/\d+/', $ret, $match);
                    $server_id = $match['0'];
                    if (posix_kill($server_id, 15)) {//如果成功了
                        echo 'stop ' . SUPER_PROCESS_NAME . "\033[32;40m [SUCCESS] \033[0m" . PHP_EOL;
                    } else {
                        echo 'stop ' . SUPER_PROCESS_NAME . "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
                    }
                } else {
                    echo 'cmd is ' . $cmd . PHP_EOL . ' and return is ' . print_r($return, true) . PHP_EOL;
                }
                exit;
            }

            // 命令发给服务
            if ($return['code'] == Code::SUCCESS_CODE) {
                // 临时的status优化
                if ($cmd == 'status') {
                    if (empty($return['data'])) {
                        echo 'No Server is Running' . PHP_EOL;
                    } else {
                        echo SUPER_PROCESS_NAME . ' is ' . "\033[32;40m [RUNNING] \033[0m" . PHP_EOL;
                        foreach ($return['data'] as $single) {
                            echo 'Server Name is ' . "\033[32;40m " . $single['name'] . " \033[0m" . '  '  . PHP_EOL;
                        }
                    }
                } else {
                    echo 'cmd is ' . $cmd . PHP_EOL . ' and return is ' . print_r($return['msg'], true) . PHP_EOL;
                }
            } else {
                echo 'cmd is ' . $cmd . PHP_EOL . ' and return is ' . print_r($return['msg'], true) . PHP_EOL;
            }
            exit;
        } else {
            if ($cmd == 'start') {
                // 启动完毕后
                if (CmdProcess::execute($server_info['conf'], $cmd)) {
                    $running_servers[$name] = ['server_info' => $server_info, 'name' => $name];
                    self::startServerSock($running_servers);
                    // 调度中心rpc实例化
                    $time = self::convertSecondToMicroS();
                    if (!empty($server_info['conf']['xxljob']['open_registry'])) {
                        $biz_center->disable_registry = $server_info['conf']['xxljob']['open_registry'];
                        // 第一次注册
                        $biz_center->registry($time, $server_info['conf']['server']['app_name'], $server_info['conf']['server']['ip'] . ':' . $server_info['conf']['server']['port']);
                    }
                }
            } else {
                if ($cmd == 'shutdown' || $cmd == 'status' || $cmd == 'stop' || $cmd == 'restart') {
                    echo SUPER_PROCESS_NAME . ' is not running, please check it' ."\033[31;40m [FAIL] \033[0m" . PHP_EOL;
                    exit;
                }
            }
        }
    }


    /**
     * 获取所有支持的命令
     * php Bin/index.php serverName start
     * php Bin/index.php stop
     * @return array
     */
    public static function getSupportCmds()
    {
        return [
            'start', 'stop', 'reload', 'restart',
            'status', 'list'
        ];
    }

    /**
     * 检测命令行传参
     *
     * @param $argv
     * @return bool
     */
    public static function checkArgvValid($cmd, $name)
    {
        if (!$cmd || (!$name && ($cmd != 'status' && $cmd != 'shutdown' && $cmd != 'list')) || !in_array($cmd, self::getSupportCmds())) {
            return false;
        }

        if ($cmd != 'status' && $cmd != 'shutdown' && $cmd != 'list') {
            $server_path = CONF_PATH . '/' .$name . ".ini";
            if (!file_exists($server_path)) {
                echo "your server name  $name not exist" . PHP_EOL;
                exit;
            }
        }

        //输出所有可以执行的server
        if ($cmd == 'list') {
            $config_dir = CONF_PATH . "/*.ini";
            $config_arr = glob($config_dir);
            // 配置名必须是server name
            echo "your server list：" . PHP_EOL;
            foreach ($config_arr as $server_name) {
                echo basename($server_name, '.ini') . PHP_EOL;
            };
            echo '----------------------------' . PHP_EOL;
            exit;
        }

        if (!$name) {
            echo "your server name is invalid：" . PHP_EOL;
            return false;
        }

        return true;
    }

    /**
     * 命令操作tips
     */
    public static function tips()
    {
        echo "please input server name and cmd:  php index.php myServerName start " . PHP_EOL;
        echo "support cmds: start stop reload restart status list" . PHP_EOL;
        echo "if you want to stop server please input :  php index.php shutdown" . PHP_EOL;
        echo "if you want to know running server name please input :  php index.php status" . PHP_EOL;
        echo "if you want to know server list that you can start please input :  php index.php list" . PHP_EOL;
        exit;
    }


}