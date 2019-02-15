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
        //var_dump(self::packSendData(json_encode($message)));
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
        // 项目名_类名_方法名
        $executor_handler = $data['executorHandler'];
        $handler_info_arr = explode('_', $executor_handler);
        // /project/platform/ecs/public/index.php  teacherQuality/crontab/syncClassAndExtraDataMonthly -m=1 -c=2
        // 项目地址
        $project_index = self::getProjectIndex($handler_info_arr[0]);
        // 入口文件地址
        $index_path = $this->conf['project']['root_path'] . $project_index;
        // 拼成可以调用脚本的样子
        if (empty($handler_info_arr[3])) {
            // 测试用
            $class_path = '';
            $index_path = '/data/wwwroot/xxl-job-swoole/Tests/test_cli.php';
        } else {
            $class_path = $handler_info_arr[1] . '/' . $handler_info_arr[2] . '/' . $handler_info_arr[3];
        }
        $params = [$index_path, $class_path];

        // 带执行参数
        if ($data['executorParams']) {
            $params_key_values = explode('&',$data['executorParams']);

            foreach ($params_key_values as $params_key_value) {
                $params[] = '-' . $params_key_value;
            }
        }

        self::appendLog($data['logDateTim'], $data['logId'], '执行task参数：' . json_encode($params));

        // 然后执行脚本
        $process = new Process(function (Process $worker) use ($params, $data) {
            // 启动进程守护
            $result = $worker->exec($this->conf['server']['php'], $params);
            self::appendLog($data['logDateTim'], $data['logId'], '执行task后返回结果：' . $result);

        }, false);
        //$process->useQueue(1, 2);
        $process->start();
        $wait_res = Process::wait();
        if ($wait_res['code']) {
            echo  "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
            exit;
        }
        // 设置到swoole_table里
        $this->table->set($data['jobId'], ['task_id' => $task_id, 'process_name' => implode(' ', $params)]);

        return json_encode(['job_id' => $data['jobId'], 'request_id' => $data['requestId'], 'log_id' => $data['logId'], 'log_date_time' => $data['logDateTim']]);
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
            'msg'  => ''
        ];
        // 结果回调
        $result = $biz_center->callback($time, $log_id, $request_id, $log_time, $execute_result);

        self::appendLog($data['log_date_time'], $data['log_id'], 'task执行完成后回调：' . intval($result));

        self::appendLog($data['log_date_time'], $data['log_id'], '任务执行完成');
    }
}
