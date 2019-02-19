<?php
/**
 * 任务通用工具
 *
 * @author sivan
 * @description tools
 */
namespace Lib;

trait JobTool
{
    /**
     * 整型转字节数组 用于通信协议拼上返回数据
     *
     * @param $length
     * @return array
     */
    public static function intToByteArray($length)
    {
        $byte_arr = [
            $length >> 24 & 0xff,
            $length >> 16 & 0xff,
            $length >> 8  & 0xff,
            $length       & 0xff
        ];

        return $byte_arr;
    }

    /**
     * 转换字节数组到字符串
     *
     * @param $byte_arr
     * @return string
     */
    public static function convertByteArrToStr($byte_arr)
    {
        $convert_str = '';

        foreach ($byte_arr as $byte) {
            $convert_str .= chr($byte);
        }

        return $convert_str;
    }

    /**
     * 转换字符串到字节数组
     *
     * @param $str
     * @return array
     */
    public static function convertStrToBytes($str)
    {
        $len = strlen($str);

        $bytes = [];

        for ($i = 0; $i < $len; $i++) {
            if (ord($str[$i]) >= 128) {
                $byte = ord($str[$i]) - 256;
            } else {
                $byte = ord($str[$i]);
            }
            $bytes[] = $byte;
        }

        return $bytes;
    }

    /**
     * 获取数据流并流入不同的解包器中
     *
     * @param $stream_data
     * @param $rpc_protocol
     * @return int|mixed
     */
    public static function getDataStream($stream_data, $rpc_protocol)
    {
        $rpc_protocol = strtolower($rpc_protocol);
        switch ($rpc_protocol) {
            case 'json':
                $stream_data = self::unpackJackson($stream_data);
                break;
            default:
                return 0;
        }

        return $stream_data;
    }

    /**
     * @param $serverName
     * @return array
     */
    public static function getServerIni($serverName)
    {
        $configPath = CONF_PATH . '/' . $serverName . ".ini";
        if (!file_exists($configPath)) {
            return ['code' => 404, 'msg' => 'missing config path' . $configPath];
        }
        $config = parse_ini_file($configPath, true);
        return ['code' => Code::SUCCESS_CODE, 'conf' => $config];
    }

    /**
     * @param $msg
     */
    public static function serverMasterLog($msg)
    {
        error_log($msg . PHP_EOL, 3, '/tmp/SuperMaster.log');
    }


    /**
     * @param $msg
     */
    public static function serverMasterLogTimer($msg)
    {
        error_log($msg . PHP_EOL, 3, '/tmp/SuperMasterTimer.log');
    }


    /**
     * 设置发送的data
     *
     * @param $send_data
     * @return string
     */
    public static function packSendData($send_data)
    {
        $bytes = self::convertStrToBytes($send_data);
        $length_bytes = self::intToByteArray(sizeof($bytes));
        $total_data =  array_merge($length_bytes, $bytes);

        return self::convertByteArrToStr($total_data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public static function unpackData($data)
    {
        $bytes = self::convertStrToBytes($data);
        $data = self::convertByteArrToStr(array_slice($bytes, 4));

        $req = json_decode($data, true);
        return $req;
    }

    /**
     * 最后输出返回的数据组合
     *
     * @param $result
     * @param $request_id
     * @param null $error_msg
     * @return mixed
     */
    public static function outputStream($result, $request_id, $error_msg = null)
    {
        $main_json = json_encode(['result' => $result, 'requestId' => $request_id, 'errorMsg' => $error_msg]);

        $main_json_bytes = self::convertStrToBytes($main_json);

        $json_len_bytes = self::intToByteArray($main_json_bytes);

        $output_data = array_merge($json_len_bytes, $main_json_bytes);

        return self::convertByteArrToStr($output_data);
    }

    /**
     * 解包json数据
     *
     * @param $stream_data
     * @return mixed
     */
    protected static function unpackJackson($stream_data)
    {
        $stream_data = preg_replace('/[\x00-\x1F]/', '', $stream_data);

        return json_decode($stream_data, true);
    }

    /**
     * 追加执行日志
     *
     * @param $log_time
     * @param $log_id
     * @param $content
     */
    public static function appendLog($log_time, $log_id, $content)
    {
        $filename = self::makeLogFileName($log_time, $log_id);
        $handle = fopen($filename, "a+");
        $date = date('Y-m-d H:i:s', time());
        fwrite($handle, '【' . $date . '】' .$content . "\n");
        fclose($handle);
    }

    /**
     * jobid log_time拼日志文件名称
     *
     * @param $log_time
     * @param $log_id
     */
    public static function makeLogFileName($log_time, $log_id)
    {

        $time = '/data/wwwroot/xxl-job-swoole/Log/'. date('Y-m-d', self::convertMicroSToSecond($log_time));
        if (!file_exists($time)) {
            mkdir($time);
        }
        $filename =  $time . DIRECTORY_SEPARATOR . $log_id . '.log';
        return $filename;
    }

    /**
     * 读取日志文件
     *
     * @param $log_file_name
     * @param $from_line_num
     */
    public static function readLog($log_file_name, $from_line_num)
    {

        $filter = [];
        if (file_exists($log_file_name)){
            $file_handle = fopen($log_file_name, "r");
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                $filter[] = $line;
            }
            fclose($file_handle);
        }
        return ['fromLineNum' => $from_line_num, 'toLineNum' => count($filter), 'logContent' => implode('', array_slice($filter, ($from_line_num -1 )))];
    }

    /**
     * 发信号到server
     *
     * @param $cmd
     * @param $name
     * @return mixed|string
     */
    public static function sendCmdToSock($cmd, $name)
    {
        $client = new Client(SWOOLE_UNIX_STREAM, SWOOLE_SOCK_SYNC);
        var_dump($client);
        $client->connect(UNIX_SOCK_PATH, 0, 10);
        $client->send(json_encode(['cmd' => $cmd, 'name' => $name]));
        $ret = $client->recv();
        var_dump($ret);
        $ret = json_decode($ret, true);
        $client->close();

        return $ret;
    }

    /**
     * @description 通过unix sock信息
     */
    public static function startServerSock($running_servers)
    {
        self::setProcessName(SUPER_PROCESS_NAME);
        //这边其实也是也是demon进程
        $sock_server = new Server(UNIX_SOCK_PATH, 0, SWOOLE_BASE, SWOOLE_UNIX_STREAM);

        // running servers
        $sock_server->running_servers = $running_servers;

        $sock_server->set([
            'worker_num' => 1,
            'daemonize' => 1,
            'log_file' => '/data/wwwroot/xxl-job-swoole/Log/runtime.log'
        ]);

        $sock_server->on('connect', function() {

        });

        // 处理各种信号指令
        $sock_server->on('receive', function (Server $server, $fd, $from_id, $data) {
            var_dump($data);
            $info = json_decode($data, true);
            $cmd = $info['cmd'];
            $server_name = $info['server'];
            // 不存在则启动
            $server_conf = self::getServerIni($server_name);

            switch ($cmd) {
                case 'start':
                    if (isset($serv->running_servers[$server_name])) {
                        $server->send($fd, json_encode(['code' => 200, "msg" => $server_name . ' is already running']));
                        return;
                    }

                    if ($server_conf['code'] != Code::SUCCESS_CODE) {
                        $server->send($fd, json_encode($server_conf));
                        return ;
                    }

                    // 进程守护
                    if (CmdProcess::execute($server_conf, $cmd)) {
                        $server->running_servers[$server_name] = ['server_info' => $server_conf, 'name' => $server_name];
                        $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'msg' => "server {$server_name} start" . " \033[32;40m [SUCCESS] \033[0m"]));
                        return;
                    }
                    break;
                case 'stop':
                    CmdProcess::execute($server_conf, 'stop');
                    $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'msg' => "server {$server_name} stop" . " \033[32;40m [SUCCESS] \033[0m"]));
                    unset($server->running_servers[$server_name]);
                    return;
                    break;
                case 'shutdown':
                    CmdProcess::execute($server_conf, 'shutdown');
                    $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'data' => $server->running_servers, 'msg' => "server {$server_name} shutdown" . " \033[32;40m [SUCCESS] \033[0m"]));
                    //清除所有的runServer序列
                    unset($server->running_servers);
                    break;
                case 'reload':
                    CmdProcess::execute($server_conf, $cmd);
                    $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'data' => $server->running_servers, 'msg' => "server {$server_name}  reload " . " \033[32;40m [SUCCESS] \033[0m"]));
                    return;
                    break;
                case 'restart':
                    //首先unset 防止被自动拉起，然后停止，然后sleep 然后start
                    unset($server->running_servers[$server_name]);//从runserver中干掉
                    CmdProcess::execute($server_conf, 'stop');
                    sleep(2);
                    CmdProcess::execute($server_conf, 'start');
                    $server->running_servers[$server_name] = ['server_info' => $server_conf, 'name' => $server_name]; //添加到runServer中
                    $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'msg' => "server {$server_name} restart  \033[32;40m [SUCCESS] \033[0m"]));
                    return;
                    break;
                case 'status':
                default:
                    $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'data' => $server->running_servers]));
                    break;
            }

        });

        $sock_server->start();
    }

    /**
     * @return int
     */
    public static function convertSecondToMicroS()
    {
        return intval(ceil(microtime(true) * 1000));
    }

    /**
     * @param $ms
     * @return int
     */
    public static function convertMicroSToSecond($ms)
    {
        return intval(ceil($ms / 1000));
    }

    /**
     * @param $project_name
     * @return array|mixed
     */
    public static function getProjectIndex($project_name)
    {
        $map = [
            'platform' => $project_name .'/ecs/public/index.php',
        ];
        return isset($map[$project_name]) ? $map[$project_name] : '';

    }

    /**
     * @param $name
     * @return string
     */
    public static function killScriptProcess($name)
    {
        $ret = system("ps aux | grep '" . $name . "' | grep -v grep ");
        preg_match('/\d+/', $ret, $match);//匹配出来进程号
        $server_id = $match[0];
        if (posix_kill($server_id, 15)) {
            //如果成功了
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $name
     * @return bool
     */
    public static function checkScriptProcess($name)
    {
        $ret = system("ps aux | grep '" . $name . "' | grep -v grep ");
        preg_match('/\d+/', $ret, $match);
        //匹配出来进程号
        if (!$match) {
            return false;
        }
        $server_id = $match[0];
        if ($server_id) {
            //如果成功了
            return true;
        } else {
            return false;
        }

    }

    /**
     * @param $data
     * @param $conf
     * @return array
     */
    public static function getHandlerParams($data, $conf)
    {
        // 项目名_类名_方法名
        $executor_handler = $data['executorHandler'];
        // 规则解析
        $handler_info_arr = explode('_', $executor_handler);
        // 项目地址
        $project_index = self::getProjectIndex($handler_info_arr[0]);
        // 入口文件地址
        $index_path = $conf['project']['root_path'] . $project_index;
        // 拼成可以调用脚本的样子
        if (empty($handler_info_arr[3])) {
            // 测试用
            $class_path = '';
            $index_path = '/data/wwwroot/xxl-job-swoole/Tests/test_cli.php';
        } else {
            $class_path = $handler_info_arr[1] . '/' . $handler_info_arr[2] . '/' . $handler_info_arr[3];
        }
        $params = [$index_path, $class_path];
        // 带执行参数
        if ($data['executorParams']) {
            $params_key_values = explode('&', $data['executorParams']);

            foreach ($params_key_values as $params_key_value) {
                $params[] = '-' . $params_key_value;
            }
        }
        return $params;
    }

    /**
     * @param $name
     */
    public static function setProcessName($name)
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        } else if (function_exists('swoole_set_process_name')) {
            swoole_set_process_name($name);
        } else {
            trigger_error(__METHOD__ . " failed. require cli_set_process_title or swoole_set_process_name.");
        }
    }
}
