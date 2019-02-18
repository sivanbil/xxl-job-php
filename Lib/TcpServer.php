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
     * @var
     */
    public $server;

    /**
     * @var
     */
    public $conf;

    public $table;

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

        $this->table = new Table($this->conf['table']['size']);
        $this->table->column('task_id', Table::TYPE_INT);
        $this->table->column('process_name', Table::TYPE_STRING, 100);
        $this->table->create();
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
    }

    /**
     * 注册回调事件的启动
     *
     * @param $server
     */
    public function onStart(Server $server )
    {
        // 定时器去注册
        $server->tick($this->conf['xxljob']['registry_interval_ms'], function() {
            $biz_center = new BizCenter($this->conf['xxljob']['host'], $this->conf['xxljob']['port']);
            $time = self::convertSecondToMicroS();
            $biz_center->registry($time, $this->conf['server']['app_name'], $this->conf['server']['ip'] . ':' . $this->conf['server']['port']);
        });
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
        $req = self::unpackData($data);

        $parameters = $req['parameters'];
        $invoke_name = $req['methodName'];

        // 根据调用不同方法
        switch ($invoke_name) {
            case 'run':
                // 任务处理
                ExecutorCenter::run($parameters, $req['requestId'], $server);
                // 调度结果
                $result = ['code' => Code::SUCCESS_CODE];
                break;
            case 'idleBeat':
                $result = ExecutorCenter::idleBeat($parameters, $this->table);
                break;
            case 'kill':
                $result = ExecutorCenter::kill($parameters[0], $this->table);
                break;
            case 'log':
                $result = ExecutorCenter::log($parameters[0], $parameters[1], $parameters[2]);
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
        $server->send($fd, self::packSendData(json_encode($message)));
    }

    /**
     * @param $server
     * @param $fd
     * @param $from_id
     */
    public function onClose( Server $server, $fd, $from_id )
    {

    }

    /**
     * @param Server $server
     * @param $taskId
     * @param $fromId
     * @param $data
     */
    public function onTask(Server $server, $task_id, $from_id, $data)
    {
        $params = self::getHandlerParams($data, $this->conf);
        // 设置到swoole_table里
        $process_name = implode(' ', $params);

        $exist = true;
        if (!$this->table->get($data['jobId'])) {
            $this->table->set($data['jobId'], ['task_id' => $task_id, 'process_name' => $process_name]);
            $exist = false;
        }

        // 丢弃下一个
        if ($exist && $data['executorBlockStrategy'] == JobStrategy::DISCARD_NEXT_SCHEDULING) {
            self::appendLog($data['logDateTim'], $data['logId'], '此task因策略需要被丢弃：' . json_encode($params));
            return JobStrategy::discard($data);
        }

        // 丢弃之前的使用新的
        if ($exist && $data['executorBlockStrategy'] == JobStrategy::USE_NEXT_SCHEDULING) {
            self::appendLog($data['logDateTim'], $data['logId'], '上一个task因策略需要被丢弃：' . json_encode($params));
            self::killScriptProcess($process_name);
        }

        self::appendLog($data['logDateTim'], $data['logId'], '执行task参数：' . json_encode($params));
        // 按照队列执行
        return JobStrategy::serial($data, $process_name, $params, $this->table);
    }

    /**
     * task进程完成后调用
     *
     * @param Server $server
     * @param $taskId
     * @param $data
     */
    public function onFinish(Server $server, $task_id, $data)
    {
        $data = json_decode($data, true);
        $this->table->del($data['job_id']);

        $biz_center = new BizCenter($this->conf['xxljob']['host'], $this->conf['xxljob']['port']);
        $time = self::convertSecondToMicroS();
        $log_id = $data['log_id'];
        $log_time = $data['log_date_time'];
        $request_id = $data['request_id'];

        $execute_result = [
            'code' => 200,
            'msg'  => '脚本执行完成，结束脚本运行'
        ];
        // 结果回调
        $result = $biz_center->callback($time, $log_id, $request_id, $log_time, $execute_result);

        $msg = Code::SUCCESS_CODE;
        if ($result) {
            $msg = Code::SUCCESS_CODE;
        }
        self::appendLog($data['log_date_time'], $data['log_id'], 'task回调:' . $msg);

        self::appendLog($data['log_date_time'], $data['log_id'], '任务执行完成');
    }
}
