<?php
/**
 * client 发送的格式
 * $params = '{
"createMillisTime":' . $time . ',
"accessToken":"",
"className":"com.xxl.job.core.biz.ExecutorBiz",
"methodName":"registry",
"parameterTypes":["com.xxl.job.core.biz.model.TriggerParam"],
"parameters":[{"registGroup":"","registryKey":"add", "registryValue": ""}],
"version":null
}';
 * User: liaoxianwen
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
        $task_center = new TaskCenter();

        $task_center->post();
        die;
        $time = ceil(microtime(true) * 1000);

        // 进程检测
        if (Process::processCheckExist()) {
            if ($cmd == 'shutdown') {
                $client = new \swoole_client(SWOOLE_UNIX_STREAM, SWOOLE_SOCK_SYNC);
                $client->connect(uniSockPath, 0, 3);
                $client->send(json_encode(['cmd' => $cmd, 'name' => $name]));
                $ret = $client->recv();
                $ret = json_decode($ret, true);
                $client->close();
                return $ret;
            }
        } else {
            if ($cmd == 'start') {
                $server_info = self::getServerIni($name);
                // 执行server
                if (Process::start($server_info['conf'])) {
                    $task_center = new TaskCenter();

                    $task_center->get();

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
}