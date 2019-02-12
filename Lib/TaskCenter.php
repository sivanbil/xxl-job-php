<?php
/**
 * 任务调度中心配置细腻
 * User: sivan
 * Date: 2019/2/3
 * Time: 10:25 AM
 */

namespace Lib;


class TaskCenter
{
    public $host = '127.0.0.1';
    public $port = '8987';

    public $client = NULL;

    public function __construct()
    {
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP);
        $this->client->connect($this->host, $this->port, -1);
    }

    /**
     * @param $time
     */
    public function registy($time)
    {
        // 执行注册 server
        $params = '{
                    "createMillisTime":'. $time .',
                    "accessToken":"",
                    "className":"com.xxl.job.core.biz.AdminBiz",
                    "methodName":"registry",
                    "parameterTypes":["com.xxl.job.core.biz.model.RegistryParam"],
                    "parameters":[{"registGroup":"EXECUTOR","registryKey":"swoole", "registryValue": "127.0.0.1:9501"}],
                    "version":null
                    }';
        $message = JobTool::packSendData($params);
        $this->client->send($message);
        $data = $this->client->recv();
        $result = JobTool::unpackData($data);
        if ($result['result']['code'] === 200) {
            // 注册成功
            echo '注册成功';
        } else {
            // 注册失败
            echo '注册失败';
        }
    }
}