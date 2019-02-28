<?php
/**
 * 调度中心api
 *
 * User: sivan
 * Date: 2019/2/3
 * Time: 10:25 AM
 */

namespace Lib\Executor;


use Lib\Common\JobTool;
use Swoole\Client;

class BizCenter
{
    public $client = NULL;

    public $openRegistry = 0;

    public function __construct($host = '127.0.0.1', $port = '8987')
    {

        $this->client = new Client(SWOOLE_SOCK_TCP);
        $this->client->connect($host, $port, -1);
    }

    /**
     * 注册执行器
     *
     * @param $time
     * @param $appName
     * @param $address
     * @return null
     */
    public function registry($time, $appName, $address)
    {
        if (!$this->openRegistry) {
            return null;
        }
        // 执行注册 server
        $params = json_encode([
            'createMillisTime' => $time,
            'accessToken' => '',
            'className' => 'com.xxl.job.core.biz.AdminBiz',
            'methodName' => 'registry',
            'parameterTypes' => ['com.xxl.job.core.biz.model.RegistryParam'],
            'parameters' => [['registGroup' => 'EXECUTOR', 'registryKey' => $appName, 'registryValue' => $address]]
        ]);
        $message = JobTool::packSendData($params);
        $this->client->send($message);
        $data = $this->client->recv();
        $result = JobTool::unpackData($data);
        if ($result['result']['code'] === 200) {
            // 注册成功
            echo $appName . ':' . $address . PHP_EOL . '注册成功' . PHP_EOL;
        } else {
            // 注册失败
            echo $appName . ':' . $address . PHP_EOL . '注册失败' . PHP_EOL;
        }
    }

    /**
     * 摘除执行器
     *
     * @param $time
     * @param $appName
     * @param $address
     */
    public function registryRemove($time, $appName, $address)
    {
        // 摘除执行器
        $params = json_encode([
            'createMillisTime' => $time,
            'accessToken' => '',
            'className' => 'com.xxl.job.core.biz.AdminBiz',
            'methodName' => 'registryRemove',
            'parameterTypes' => ['com.xxl.job.core.biz.model.RegistryParam'],
            'parameters' => [['registGroup' => 'EXECUTOR', 'registryKey' => $appName, 'registryValue' => $address]]
        ]);
        $message = JobTool::packSendData($params);
        $this->client->send($message);
        $data = $this->client->recv();
        $result = JobTool::unpackData($data);
        if ($result['result']['code'] === 200) {
            echo $appName . ':' . $address . PHP_EOL . '摘除成功' . PHP_EOL;
        } else {
            // 摘除失败
            echo $appName . ':' . $address . PHP_EOL . '摘除失败' . PHP_EOL;
        }
    }

    /**
     * 任务回调
     *
     * @param $time
     * @param $logId
     * @param $requestId
     * @param $logDateTim
     * @param $executeResult
     * @return bool
     */
    public function callback($time, $logId, $requestId, $logDateTim, $executeResult)
    {
        $params = json_encode([
            'requestId' => $requestId,
            'createMillisTime' => $time,
            'accessToken' => '',
            'className' => 'com.xxl.job.core.biz.AdminBiz',
            'methodName' => 'callback',
            'parameterTypes' => ['java.util.List'],
            'parameters' => [
                [[
                    'logId' => $logId,
                    'logDateTim' => $logDateTim,
                    'executeResult' => $executeResult
                ]]
            ]
        ]);
        $message = JobTool::packSendData($params);
        $this->client->send($message);
        $data = $this->client->recv();
        $result = JobTool::unpackData($data);
        if ($result['result']['code'] === 200) {
            // 注册成功
            return true;
        } else {
            // 注册失败
            return false;
        }
    }
}