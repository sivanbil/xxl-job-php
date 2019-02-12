<?php
/**
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
     * 扫描所有的任务
     */
    public static function getAllJobs()
    {
        // @todo 扫描全部的任务执行
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

    public static function startServer($phpStart, $cmd, $name)
    {

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
}
