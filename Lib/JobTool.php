<?php
/**
 * @author sivan
 * @description tools
 */
namespace Job\Lib;

trait JobTool
{
    /**
     * 整型转字节数组 用于通信协议拼上返回数据
     *
     * @param $length
     * @return array
     */
    public function intToByteArray($length)
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
    public function convertByteArrToStr($byte_arr)
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
    public function convertStrToBytes($str)
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
    public function getAllJobs()
    {
        // @todo 扫描全部的任务执行
    }


    /**
     * 记录日志
     *
     * @param $log_data
     * @param $file_name
     */
    public function recordLog($log_data, $file_name)
    {

    }

    /**
     * 操作tips
     */
    public function tips()
    {
        echo "welcome to use Swoole-Controller,we can help you to monitor your swoole server!" . PHP_EOL;
        echo "please input server name and cmd:  php swoole.php myServerName start " . PHP_EOL;
        echo "support cmds: start stop reload restart status startall list" . PHP_EOL;
        echo "if you want to stop Swoole-Controller please input :  php swoole.php shutdown" . PHP_EOL;
        echo "if you want to know running servername please input :  php swoole.php status" . PHP_EOL;
        echo "if you want to know server list that you can start please input :  php swoole.php list" . PHP_EOL;
        echo "if you want to start all your servers please input :  php swoole.php startall" . PHP_EOL;
        exit;
    }

    /**
     * 获取数据流并流入不同的解包器中
     *
     * @param $stream_data
     * @param $rpc_protocol
     * @return int|mixed
     */
    public function getDataStream($stream_data, $rpc_protocol)
    {
        $rpc_protocol = strtolower($rpc_protocol);
        switch ($rpc_protocol) {
            case 'json':
                $stream_data = $this->unpackJackson($stream_data);
                break;
            default:
                return 0;
        }

        return $stream_data;
    }

    /**
     * 最后输出返回的数据组合
     *
     * @param $result
     * @param $request_id
     * @param null $error_msg
     * @return mixed
     */
    public function outputStream($result, $request_id, $error_msg = null)
    {
        $main_json = json_encode(['result' => $result, 'requestId' => $request_id, 'errorMsg' => $error_msg]);

        $main_json_bytes = $this->convertStrToBytes($main_json);

        $json_len_bytes = $this->intToByteArray($main_json_bytes);

        $output_data = array_merge($json_len_bytes, $main_json_bytes);

        return $this->convertByteArrToStr($output_data);
    }

    /**
     * 解包json数据
     *
     * @param $stream_data
     * @return mixed
     */
    protected function unpackJackson($stream_data)
    {
        $stream_data = preg_replace('/[\x00-\x1F]/', '', $stream_data);

        return json_decode($stream_data, true);
    }
}
