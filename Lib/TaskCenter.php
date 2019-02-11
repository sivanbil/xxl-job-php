<?php
/**
 * 任务调度中心配置细腻
 * User: liaoxianwen
 * Date: 2019/2/3
 * Time: 10:25 AM
 */

namespace Lib;


class TaskCenter
{
    public $url = '127.0.0.1';
    public $port = '8187';
    public $api_mapping = '/xxl-job-admin/api';
    public $enable_ssl = false;

    public $client = NULL;

    public function __construct()
    {
        $this->client = new \swoole_http_client($this->url, $this->port, $this->enable_ssl);
        
    }

    public function get()
    {
        $this->client->get($this->api_mapping, function () {
            echo "Length: " . strlen($this->client->body) . "\n";
            echo $this->client->body;
        });

    }

    public function post()
    {

        // 执行注册 server
//        $params = '{
//                    "createMillisTime":' . time() . ',
//                    "accessToken":"",
//                    "className":"com.xxl.job.core.biz.ExecutorBiz",
//                    "methodName":"registry",
//                    "parameterTypes":["com.xxl.job.core.biz.model.TriggerParam"],
//                    "parameters":[{"registGroup":3,"registryKey":"add", "registryValue": ""}],
//                    "version":null
//                    }';
        $this->client->post($this->api_mapping, array("a" => '1234', 'b' => '456'), function () {
            echo "Length: " . strlen($this->client->body) . "\n";
            echo $this->client->body;
        });
    }
}