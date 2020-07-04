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

    public static $runningServer;
    /**
     * 需要支持以下几种命令
     * start    启动
     * stop     停止
     * reload   重载
     * restart  重启
     * shutdown 关闭
     * status   查看状况
     * list     列表
     *
     */


    /**
     * @param $cmd
     * @param $name
     * @param string $processName
     */
    public static function exec($cmd, $name, $processName = '')
    {
        $serverInfo = self::getServerIni($name);
        if ($processName) {
            $serverInfo['conf']['server']['process_name'] = $processName;
        }
        $processExists = CmdProcess::processCheckExist();
        if ($cmd == 'start' &&  $processExists) {
            echo 'Server is Running, Please check ' . PHP_EOL;
            exit;
        }
        // 进程检测
        if ($processExists) {
            // reload stop restart
            $return = self::sendCmdToSock($cmd, $name);
            if ($cmd == 'stop') {
                // 获取status 之后去杀掉进程
                if ($return['code'] == Code::SUCCESS_CODE) {
                    // 先杀掉所有的run server
                    if (empty($return['data'])) {
                        echo 'shutdown' . "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
                        exit;
                    }

                    $ret = system("  ps aux | grep " . SUPER_PROCESS_NAME . " | grep -v grep");
                    preg_match('/\d+/', $ret, $match);
                    $serverId = $match['0'];
                    if (posix_kill($serverId, 15)) {
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
                if (CmdProcess::execute($serverInfo['conf'], $cmd)) {
                    $runningServers[$name] = ['server_info' => $serverInfo, 'name' => $name];
                    self::startServerSock($runningServers);
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
            $serverPath = CONF_PATH . '/' .$name . ".ini";
            if (!file_exists($serverPath)) {
                echo "your server name  $name not exist" . PHP_EOL;
                exit;
            }
        }

        // 输出所有可以执行的server
        if ($cmd == 'list') {
            $configDir = CONF_PATH . "/*.ini";
            $configArr = glob($configDir);
            // 配置名必须是server name
            echo "your server list：" . PHP_EOL;
            foreach ($configArr as $serverName) {
                echo basename($serverName, '.ini') . PHP_EOL;
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