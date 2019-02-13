<?php
/**
 * 命令行启动守护进程
 *
 * User: sivan
 * Date: 2019/2/1
 * Time: 11:08 AM
 */
namespace Lib;

class Cmd
{
    use JobTool;

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
        // 进程检测
        if (Process::processCheckExist()) {
            if ($cmd == 'shutdown') {
                $client = new \swoole_client(SWOOLE_UNIX_STREAM, SWOOLE_SOCK_SYNC);
                $client->connect(UNIX_SOCK_PATH, 0, 3);
                $client->send(json_encode(['cmd' => $cmd, 'name' => $name]));
                $ret = $client->recv();
                $ret = json_decode($ret, true);
                $client->close();

                //先杀掉所有的run server
                foreach ($ret['data'] as $server) {
                    // array('php'=>,'name'=)
                    $ret = system("ps aux | grep " . $server['name'] . " | grep master | grep -v grep ");
                    preg_match('/\d+/', $ret, $match);//匹配出来进程号
                    $ServerId = $match['0'];
                    if (posix_kill($ServerId, 15)) {//如果成功了
                        echo 'stop ' . $server['name'] . "\033[32;40m [SUCCESS] \033[0m" . PHP_EOL;
                    } else {
                        echo 'stop ' . $server['name'] . "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
                    }
                };
                //然后开始杀Swoole-Controller
                $ret = system("  ps aux | grep " . SUPER_PROCESS_NAME . " | grep -v grep");
                preg_match('/\d+/', $ret, $match);
                $ServerId = $match['0'];
                if (posix_kill($ServerId, 15)) {//如果成功了
                    echo 'stop ' . SUPER_PROCESS_NAME . "\033[32;40m [SUCCESS] \033[0m" . PHP_EOL;
                } else {
                    echo 'stop ' . SUPER_PROCESS_NAME . "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
                }
            }

        } else {
            if ($cmd == 'start') {
                $server_info = self::getServerIni($name);
                // 执行server
                if (Process::start($server_info['conf'])) {
                    $biz_center = new BizCenter();
                    $time = ceil(microtime(true) * 1000);
                    // 第一次注册
                    $biz_center->registry($time, $server_info['conf']['server']['app_name'], $server_info['conf']['server']['ip'] . ':' . $server_info['conf']['server']['port']);
                }
            } else {
                if ($cmd == 'shutdown' || $cmd == 'status') {
                    echo SUPER_PROCESS_NAME . ' is not running, please check it' . PHP_EOL;
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
            'shutdown', 'status', 'list', 'registry'
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