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
     * @var \swoole_server
     */
    public $server;

    public $conf;

    /**
     * TcpServer constructor.
     * @param $conf
     */
    public function __construct($conf) {

        $this->conf = $conf;

        $this->server = new \swoole_server($conf['server']['ip'], $conf['server']['port']);
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
    public function run()
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
    public function onStart(\swoole_server $server )
    {

    }

    /**
     * @param $server
     * @param $fd
     * @param $from_id
     */
    public function onConnect(\swoole_server $server, $fd, $from_id )
    {
    }

    /**
     * @param \swoole_server $server
     * @param $fd
     * @param $from_id
     * @param $data
     */
    public function onReceive( \swoole_server $server, $fd, $from_id, $data )
    {
        $req = JobTool::unpackData($data);

        $message = JobTool::packSendData(json_encode(['result' => ['code' => 200, "content" => 'success'], 'requestId' => $req['requestId'], 'errorMsg' => null]));

        // 任务处理


        $server->send($fd, $message);
    }

    /**
     * @param $server
     * @param $fd
     * @param $from_id
     */
    public function onClose( \swoole_server $server, $fd, $from_id )
    {

    }

    public function onTask($server, $taskId, $fromId, $data)
    {

    }

    public function onFinish($server, $taskId, $data)
    {

    }
}
