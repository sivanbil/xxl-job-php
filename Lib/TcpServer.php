<?php
/**
 * 常驻进程的tcp 执行器server
 *
 * 执行器提供给调度中心的api
 *
 * @author sivan
 * @description tcp server class
 */
namespace Lib;

class TcpServer
{
    use JobTool;
    /**
     * @var Server
     */
    public $server;

    public $conf;

    /**
     * TcpServer constructor.
     * @param $conf
     */
    public function __construct($conf) {

        $this->conf = $conf;

        $this->server = new Server($conf['server']['ip'], $conf['server']['port']);
        $this->server->set($conf['setting']);

        // 注册回调事件
        $this->server->on('start',   [$this, 'onStart']);
        $this->server->on('connect', [$this, 'onConnect']);
        $this->server->on('receive', [$this, 'onReceive']);
        $this->server->on('close',   [$this, 'onClose']);

        if (isset($conf['setting']['task_worker_num'])) {
            $this->server->on('Task', array($this, 'onTask'));
            $this->server->on('Finish', array($this, 'onFinish'));
        }
    }

    /**
     * @param $name
     */
    public function setProcessName($name)
    {
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        } else if (function_exists('swoole_set_process_name')) {
            swoole_set_process_name($name);
        } else {
            trigger_error(__METHOD__ . " failed. require cli_set_process_title or swoole_set_process_name.");
        }
    }

    /**
     * 启动
     */
    public function start()
    {
        $this->server->start();
        // 定时器去注册
        $this->server->tick(20000, function() {
            $biz_center = new BizCenter();
            $time = ceil(microtime(true) * 1000);
            $biz_center->registry($time, $this->conf['server']['app_name'], $this->conf['server']['ip'] . ':' . $this->conf['server']['port']);
        });
    }

    /**
     * 注册回调事件的启动
     *
     * @param $server
     */
    public function onStart(Server $server )
    {
        error_log('Start server success.' . PHP_EOL, 3, '/tmp/SuperMaster.log');
    }

    /**
     * @param $server
     * @param $fd
     * @param $from_id
     */
    public function onConnect(Server $server, $fd, $from_id )
    {
    }

    /**
     * @param  $server
     * @param $fd
     * @param $from_id
     * @param $data
     */
    public function onReceive( Server $server, $fd, $from_id, $data )
    {
        // 解包通信数据
        $req = JobTool::unpackData($data);

        $invoke_name = $req['methodName'];

        // 根据调用不同方法
        switch ($invoke_name) {
            case 'run':
                // 任务处理
                ExecutorCenter::run($req['parameters']);
                // 调度结果
                $result = ['code' => Code::SUCCESS_CODE];
                break;
            case 'idleBeat':
                $result = ExecutorCenter::idleBeat($req['parameters']);
                break;
            case 'kill':
                $result = ExecutorCenter::kill($req['job_id']);
                break;
            case 'log':
                $result = ExecutorCenter::log($req['logDateTim'], $req['job_id'], $req['from_line_num']);
                break;
            case 'beat':
                $result = ExecutorCenter::beat();
                break;
            default:
                $result = ['code' => Code::ERROR_CODE];
        }
        // 打包通信数据
        $message = ['result' => $result, 'requestId' => $req['requestId'], 'errorMsg' => null];

        // 发送数据包
        $server->send($fd, JobTool::packSendData(json_encode($message)));
    }

    /**
     * @param $server
     * @param $fd
     * @param $from_id
     */
    public function onClose( Server $server, $fd, $from_id )
    {

    }

    public function onTask($server, $taskId, $fromId, $data)
    {

    }

    public function onFinish($server, $taskId, $data)
    {

    }
}
