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
        // 进程检测
        if (CmdProcess::processCheckExist()) {
            // reload stop restart
            $return = self::sendCmdToSock($cmd, $name);
        } else {
            if ($cmd == 'start') {
                $server_info = self::getServerIni($name);
                if (CmdProcess::start($server_info['conf'])) {
                    self::startServerSock();
                    $biz_center = new BizCenter($server_info['conf']['xxljob']['host'], $server_info['conf']['xxljob']['port']);
                    $time = self::convertSecondToMicroS();
                    if (empty($server_info['conf']['xxljob']['disable_registry'])) {
                        $biz_center->disable_registry = $server_info['conf']['xxljob']['disable_registry'];
                    }
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
            'shutdown', 'status', 'list'
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

    /**
     * @description 通过unix sock信息
     */
    public static function startServerSock()
    {
        //cli_set_process_title(SUPER_PROCESS_NAME);
        //这边其实也是也是demon进程
        $sock_server = new Server(UNIX_SOCK_PATH, 0, SWOOLE_BASE, SWOOLE_UNIX_STREAM);

        $sock_server->set([
            'worker_num' => 1,
            'daemonize' => 1
        ]);

        $sock_server->on('connect', function() {

        });

        // 处理各种信号指令
        $sock_server->on('receive', function ($server, $fd, $from_id, $data) {
            $info = json_decode($data, true);
            $cmd = $info['cmd'];
            switch ($cmd) {
                case 'start':
                    break;
                case 'stop':
                    break;
                case 'shutdown':

                    break;
                case 'reload':

                    break;
                case 'restart':

                    break;
                case 'status':
                default:
                    break;
            }

        });

        $sock_server->start();
    }
}