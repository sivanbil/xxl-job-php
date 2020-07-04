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

    protected $host;

    protected $port;

    protected $isClientConnected = true;

    public function __construct($host = '127.0.0.1', $port = '8987')
    {

        $this->host = $host;
        $this->port = $port;
        $this->client = new Client(SWOOLE_SOCK_TCP);
        @$this->client->connect($host, $port, -1);
        if (!$this->client->isConnected()) {
            $this->client->close(true);
            $this->isClientConnected = false;
        }
    }

    public function getHost()
    {
        return $this->host . ':' . $this->port;
    }
    /**
     * 注册执行器
     *
     * @param $time
     * @param $appName
     * @param $address
     * @return bool|null
     */
    public function registry($time, $appName, $address)
    {
        if (!$this->openRegistry || ! $this->isClientConnected) {
            return null;
        }
        try {
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
                return true;
            } else {
                // 注册失败
                return false;
            }
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * 摘除执行器
     *
     * @param $time
     * @param $appName
     * @param $address
     * @return bool
     */
    public function registryRemove($time, $appName, $address)
    {
        if (!$this->openRegistry || ! $this->isClientConnected) {
            return null;
        }
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
            return true;
        } else {
            // 摘除失败
            return false;
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
            // 回调成功
            return true;
        } else {
            // 回调失败
            return false;
        }
    }
}