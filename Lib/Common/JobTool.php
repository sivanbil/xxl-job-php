<?php
/**
 * 任务通用工具
 *
 * @author sivan
 * @description tools
 */
namespace Lib\Common;

use Lib\Core\Table;
use Lib\Executor\BizCenter;
use Lib\Executor\CmdProcess;
use Lib\Core\Client;
use Lib\Core\Server;
use Rules\RuleConf;

trait JobTool
{
    protected static $retryTimes = 3;
    /**
     * 整型转字节数组 用于通信协议拼上返回数据
     *
     * @param $length
     * @return array
     */
    public static function intToByteArray($length)
    {
        $byteArr = [
            $length >> 24 & 0xff,
            $length >> 16 & 0xff,
            $length >> 8  & 0xff,
            $length       & 0xff
        ];

        return $byteArr;
    }

    /**
     * 转换字节数组到字符串
     *
     * @param $byteArr
     * @return string
     */
    public static function convertByteArrToStr($byteArr)
    {
        $convertStr = '';

        foreach ($byteArr as $byte) {
            $convertStr .= chr($byte);
        }

        return $convertStr;
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
     * @param $streamData
     * @param $rpcProtocol
     * @return array|mixed
     */
    public static function getDataStream($streamData, $rpcProtocol)
    {
        $rpcProtocol = strtolower($rpcProtocol);
        switch ($rpcProtocol) {
            case 'json':
                $streamData = self::unpackJackson($streamData);
                break;
            default:
                return [];
        }

        return $streamData;
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
     * @param $sendData
     * @return string
     */
    public static function packSendData($sendData)
    {
        $bytes = self::convertStrToBytes($sendData);
        $lengthBytes = self::intToByteArray(sizeof($bytes));
        $totalData =  array_merge($lengthBytes, $bytes);

        return self::convertByteArrToStr($totalData);
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
     * @param $requestId
     * @param null $errorMsg
     * @return string
     */
    public static function outputStream($result, $requestId, $errorMsg = null)
    {
        $mainJson = json_encode(['result' => $result, 'requestId' => $requestId, 'errorMsg' => $errorMsg]);

        $mainJsonBytes = self::convertStrToBytes($mainJson);

        $jsonLenBytes = self::intToByteArray($mainJsonBytes);

        $outputData = array_merge($jsonLenBytes, $mainJsonBytes);

        return self::convertByteArrToStr($outputData);
    }

    /**
     * 解包json数据
     *
     * @param $streamData
     * @return mixed
     */
    protected static function unpackJackson($streamData)
    {
        $streamData = preg_replace('/[\x00-\x1F]/', '', $streamData);

        return json_decode($streamData, true);
    }

    /**
     * 追加执行日志
     *
     * @param $logId
     * @param $content
     */
    public static function appendLog($logId, $content)
    {
        $logTime = self::genLogTime();
        $filename = self::makeLogFileName($logTime, $logId);
        $handle = fopen($filename, "a+");
        $date = date('Y-m-d H:i:s', time());
        fwrite($handle, '【' . $date . '】' .$content . "\n");
        fclose($handle);
    }

    /**
     * jobId logTime拼日志文件名称
     *
     * @param $logTime
     * @param $logId
     * @return string
     */
    public static function makeLogFileName($logTime, $logId)
    {

        $time = APP_PATH . '/Log/'. date('Y-m-d', self::convertMicroSToSecond($logTime));
        if (!file_exists($time)) {
            mkdir($time);
        }
        $filename =  $time . DIRECTORY_SEPARATOR . $logId . '.log';
        return $filename;
    }

    /**
     * 读取日志文件
     *
     * @param $logFileName
     * @param $fromLineNum
     * @return array
     */
    public static function readLog($logFileName, $fromLineNum)
    {

        $filter = [];
        if (file_exists($logFileName)){
            $file_handle = fopen($logFileName, "r");
            while (!feof($file_handle)) {
                $line = fgets($file_handle);
                $filter[] = $line;
            }
            fclose($file_handle);
        }
        return ['fromLineNum' => $fromLineNum, 'toLineNum' => count($filter), 'logContent' => implode('', array_slice($filter, ($fromLineNum - 1 )))];
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
        try {
            $client = new Client(SWOOLE_UNIX_STREAM, SWOOLE_SOCK_SYNC);
            $client->connect(UNIX_SOCK_PATH, 0, 10);
            $client->send(json_encode(['cmd' => $cmd, 'name' => $name]));
            $ret = $client->recv();
            $ret = json_decode($ret, true);
            $client->close();

            return $ret;
        } catch (\Exception $exception) {
            echo $exception->getMessage();die;
        }
    }

    /**
     * @description 通过unix sock信息
     */
    public static function startServerSock($runningServers)
    {
        self::setProcessName(SUPER_PROCESS_NAME);
        //这边其实也是也是demon进程
        $sockServer = new Server(UNIX_SOCK_PATH, 0, SWOOLE_BASE, SWOOLE_UNIX_STREAM);

        // running servers
        $sockServer->runningServers = $runningServers;

        $sockServer->set([
            'worker_num' => 1,
            'daemonize' => 1,
            'log_file' => APP_PATH . '/Log/runtime.log',
            'enable_coroutine' => false
        ]);

        $sockServer->on('connect', function() {
        });

        // 处理各种信号指令
        $sockServer->on('receive', function (Server $server, $fd, $fromId, $data) {
            $info = json_decode($data, true);
            $cmd = $info['cmd'];
            $serverName = $info['name'];
            // 不存在则启动
            $serverInfo = self::getServerIni($serverName);
            $serverConf = $serverInfo['conf'];
            switch ($cmd) {
                case 'start':
                    if (isset($serv->runningServers[$serverName])) {
                        $server->send($fd, json_encode(['code' => 200, "msg" => $serverName . ' is already running']));
                        return;
                    }

                    if ($serverInfo['code'] != Code::SUCCESS_CODE) {
                        $server->send($fd, json_encode($serverConf));
                        return ;
                    }

                    // 进程守护
                    if (CmdProcess::execute($serverConf, $cmd)) {
                        $server->runningServers[$serverName] = ['server_info' => $serverConf, 'name' => $serverName];
                        $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'msg' => "server {$serverName} start" . " \033[32;40m [SUCCESS] \033[0m"]));
                        return;
                    }
                    break;
                case 'stop':
                    CmdProcess::execute($serverConf, 'stop');
                    $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'data' => $server->runningServers, 'msg' => "server {$serverName} stop" . " \033[32;40m [SUCCESS] \033[0m"]));
                    unset($server->runningServers[$serverName]);
                    break;
                case 'reload':
                    CmdProcess::execute($serverConf, $cmd);
                    $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'data' => $server->runningServers, 'msg' => "server {$serverName}  reload " . " \033[32;40m [SUCCESS] \033[0m"]));
                    return;
                case 'restart':
                    //首先unset 防止被自动拉起，然后停止，然后sleep 然后start
                    unset($server->runningServers[$serverName]);//从runserver中干掉
                    CmdProcess::execute($serverConf, 'stop');
                    sleep(2);
                    CmdProcess::execute($serverConf, 'start');
                    $server->runningServers[$serverName] = ['server_info' => $serverConf, 'name' => $serverName]; //添加到runServer中
                    $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'msg' => "server {$serverName} restart  \033[32;40m [SUCCESS] \033[0m"]));
                    return;
                case 'status':
                default:
                    $server->send($fd, json_encode(['code' => Code::SUCCESS_CODE, 'data' => $server->runningServers]));
                    break;
            }
        });

        $sockServer->start();
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
     * @param $name
     * @return string
     */
    public static function killScriptProcess($name, $data)
    {
        $name = trim($name, '-');
        $ret = system("ps aux | grep '" . $name . "' | grep -v grep ");
        preg_match('/\d+/', $ret, $match);//匹配出来进程号
        self::appendLog($data['logId'], "执行的命令: ps aux | grep '" . $name . "' | grep -v grep  -> result:" . json_encode($match));
        if (!empty($match[0])) {
            $serverId = $match[0];
            $result = posix_kill($serverId, 15);
            self::appendLog($data['logId'], "kill result:" . json_encode([$result, $serverId, 15]));
            if ($result) {
                //如果成功了
                return true;
            } else {
                return false;
            }
        }
        return true;
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
        $serverId = $match[0];
        if ($serverId) {
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
        $executorHandler = $data['executorHandler'];
        // 规则解析
        $handlerInfoArr = explode('_', $executorHandler);
        // 项目地址
        $projectName = $handlerInfoArr[0];
        $projectInfo = RuleConf::info($projectName);
        !empty($projectInfo['realDir']) && $projectName = $projectInfo['realDir'];
        $runMode = empty($projectInfo['run_mode']) ? PHP_RUN_MODE : $projectInfo['run_mode'];
        // 拼成可以调用脚本的样子
        $isShell = false;
        if ((empty($handlerInfoArr[3]) && empty($projectInfo['run_mode'])) || empty($projectInfo)) {
            // php执行器本地的Tests测试脚本
            $params = RuleConf::supportLocal($handlerInfoArr, $projectName, $projectInfo, $isShell);
        } elseif (RuleConf::ARTISAN_MODE === strtolower($handlerInfoArr[1]) && strtolower($handlerInfoArr[2]) === RuleConf::LARAVEL_COMMAND_NAME_IDENTIFIER) {
            // laravel 框架支持
            $params = RuleConf::supportLaravelFramework($handlerInfoArr, $projectName, $conf, $projectInfo);
        } elseif (PHP_RUN_MODE === $runMode) {
            // 支持其他框架
            $params = RuleConf::supportCommonFramework($handlerInfoArr, $projectName, $conf, $projectInfo);
        } else {
            //throw new \Exception('not support');
            self::appendLog($data['logId'], $projectInfo['file_real_path'] . ' not support.');
        }

        if (!file_exists($projectInfo['file_real_path'])) {
            //throw new \Exception($projectInfo['file_real_path'] . ' not exists.');
            self::appendLog($data['logId'], $projectInfo['file_real_path'] . ' not exists.');
        }
        // 带执行参数
        if ($data['executorParams']) {
            $paramsKeyValues = explode('&', $data['executorParams']);
            $paramIdentifier = empty($projectInfo['param_identify']) ? CLI_PARAM_IDENTIFY : $projectInfo['param_identify'];
            foreach ($paramsKeyValues as $paramsKeyValue) {
                $params[] = $paramIdentifier . $paramsKeyValue;
            }
        }
        self::appendLog($data['logId'], 'handle params过程中projectInfo信息：' . json_encode($projectInfo));

        return ['params' => $params, 'queue_key' => ftok($projectInfo['file_real_path'], $projectInfo['identifier']), 'is_shell' => $isShell];
    }

    /**
     * @param $name
     */
    public static function setProcessName($name)
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        } elseif (function_exists('swoole_set_process_name')) {
            swoole_set_process_name($name);
        } else {
            trigger_error(__METHOD__ . " failed. require cli_set_process_title or swoole_set_process_name.");
        }
    }

    /**
     *  返回格式化数据
     *
     * @param $time
     * @return false|string
     */
    public static function formatDatetime($time, $format = 'Y-m-d H:i:s')
    {
        return date($format, $time);
    }

    public static function getTaskTag($logId)
    {
        return "-logId={$logId}";
    }

    public static function genLogTime()
    {
        return time() * 1000;
    }

    /**
     * @param $appName
     * @param $content
     * @return false|string
     */
    public static function panic($appName, $content)
    {
        try {
            $content = '【'.$appName.'】紧急问题上报，需要尽快处理： ' . $content;
            $dataParam = [
                'msgtype' => 'text',
                'text' => [
                    'content' => $content
                ],
                'at' => [
                    'atMobiles' => AlertConst::MOBILES,
                    'isAtAll' => false
                ]
            ];
            $content = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode($dataParam)
                )
            );
            return @file_get_contents(AlertConst::DING_API_URL, false, stream_context_create($content));
        } catch (\Exception $exception) {

        }
    }

    public static function getBizCenterByHostInfo($hostInfo)
    {
        $hostArr = explode(':', $hostInfo);
        $bizCenter = new BizCenter($hostArr[0], $hostArr[1]);
        return $bizCenter;
    }

    public static function getCurrentServer(Table $cacheTable)
    {
        $info = $cacheTable->get('current_server');
        return $info['server_info'];
    }
}
