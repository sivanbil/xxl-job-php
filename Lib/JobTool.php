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
        return ['code' => 0, 'conf' => $config];
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
     * @param $cmd
     * @param $name
     * @return mixed|string
     */
    public static function sendCmdToSock($cmd, $name)
    {
        $client = new \swoole_client(SWOOLE_UNIX_STREAM, SWOOLE_SOCK_SYNC);
        $client->connect(UNIX_SOCK_PATH, 0, 3);
        $client->send(json_encode(['cmd' => $cmd, 'name' => $name]));
        $ret = $client->recv();
        $ret = json_decode($ret, true);
        $client->close();

        return $ret;
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
        $server_id = $match['0'];
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
        $server_id = $match['0'];
        if ($server_id) {
            //如果成功了
            return true;
        } else {
            return false;
        }
    }
}
