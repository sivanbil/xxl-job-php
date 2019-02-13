<?php
/**
 * 调度中心api
 *
 * User: sivan
 * Date: 2019/2/3
 * Time: 10:25 AM
 */

namespace Lib;


class BizCenter
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
     * 注册执行器
     *
     * @param $time
     * @param $app_name
     * @param $address
     */
    public function registry($time, $app_name, $address)
    {
        // 执行注册 server
        $params = json_encode([
            'createMillisTime' => $time,
            'accessToken' => '',
            'className' => 'com.xxl.job.core.biz.AdminBiz',
            'methodName' => 'registry',
            'parameterTypes' => ['com.xxl.job.core.biz.model.RegistryParam'],
            'parameters' => [['registGroup' => 'EXECUTOR', 'registryKey' => $app_name, 'registryValue' => $address]]
        ]);

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

    /**
     * 摘除执行器
     *
     * @param $time
     * @param $app_name
     * @param $address
     */
    public function registryRemove($time, $app_name, $address)
    {
        // 摘除执行器
        $params = json_encode([
            'createMillisTime' => $time,
            'accessToken' => '',
            'className' => 'com.xxl.job.core.biz.AdminBiz',
            'methodName' => 'registryRemove',
            'parameterTypes' => ['com.xxl.job.core.biz.model.RegistryParam'],
            'parameters' => [['registGroup' => 'EXECUTOR', 'registryKey' => $app_name, 'registryValue' => $address]]
        ]);
        $message = JobTool::packSendData($params);
        $this->client->send($message);
        $data = $this->client->recv();
        $result = JobTool::unpackData($data);
        if ($result['result']['code'] === 200) {
            // 注册成功
            echo '摘除成功';
        } else {
            // 注册失败
            echo '摘除失败';
        }
    }

    /**
     * 任务执行回调
     *
     * @param $time
     * @param $job_id
     * @param $logDateTim
     * @param $execute_result
     */
    public function callback($time, $job_id, $logDateTim, $execute_result)
    {
        $params = json_encode([
            'createMillisTime' => $time,
            'accessToken' => '',
            'className' => 'com.xxl.job.core.biz.AdminBiz',
            'methodName' => 'callback',
            'parameterTypes' => ['com.xxl.job.core.biz.model.HandleCallbackParam'],
            'parameters' => [
                [
                    'logId' => $job_id,
                    'logDateTim' => $logDateTim,
                    'executeResult' => $execute_result
                ]
            ]
        ]);

        $message = JobTool::packSendData($params);
        $this->client->send($message);
        $data = $this->client->recv();
        $result = JobTool::unpackData($data);
        if ($result['result']['code'] === 200) {
            // 注册成功
            echo '回调成功';
        } else {
            // 注册失败
            echo '回调失败';
        }
    }
}