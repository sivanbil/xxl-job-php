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

use Lib\Common\Code;
use Lib\Common\JobTool;
use Lib\Core\Server;
use Lib\Core\Table;
use Lib\Executor\BizCenter;
use Lib\Executor\ExecutorCenter;
use Lib\Executor\JobStrategy;

class TcpServer
{
    use JobTool;
    // server 实例
    public $server;

    // 配置信息
    public $conf;

    // 内存table实例
    public $table;
    // manager pid 文件路径
    protected $managerPidFile;

    // master id 文件路径
    protected $masterPidFile;

    // tcp server process name
    protected $processName = 'php-executor-server';

    // 存储文件临时地址
    protected $runPath = '/tmp';

    // server setting 信息
    protected $setting = [
        'log_file' => APP_PATH . '/Log/runtime.log'
    ];

    /**
     * TcpServer constructor.
     * @param $conf
     */
    public function __construct($conf) {
        $this->conf = $conf;
    }


    /**
     * @param array $setting
     */
    public function run($cmd) {
        // 初始化运行时信息
        $this->initRunTime();

        // 根据命令执行不同的运行策略
        switch ($cmd) {
            case 'stop':
                $this->shutdown();
                break;
            case 'start':
                $this->initServer();
                $this->start();
                break;
            case 'reload':
                $this->reload();
                break;
            case 'restart':
                $this->shutdown();
                sleep(2);
                $this->initServer();
                $this->start();
                break;
            case 'status':
                $this->status();
                break;
            default:
                echo 'Usage:php index.php start | stop | reload | restart | status | help' . PHP_EOL;
                break;
        }
    }

    /**
     * @param Server $server
     */
    public function onManagerStart(Server $server)
    {
        // rename manager process
        self::setProcessNameProperty($this->processName . ': manager process');
    }

    /**
     * @param Server $server
     */
    public function onWorkerStart(Server $server)
    {
        $this->makeTick($server);
    }

    /**
     * 注册回调事件的启动
     *
     * @param $server
     */
    public function onStart(Server $server )
    {
        self::setProcessNameProperty($this->processName . ': master process');

        file_put_contents($this->masterPidFile, $server->master_pid);
        file_put_contents($this->managerPidFile, $server->manager_pid);
    }

    /**
     * @param Server $server
     * @param $fd
     * @param $fromId
     */
    public function onConnect(Server $server, $fd, $fromId )
    {
    }

    /**
     * @param Server $server
     * @param $fd
     * @param $fromId
     * @param $data
     */
    public function onReceive( Server $server, $fd, $fromId, $data )
    {

        // 解包通信数据
        $req = self::unpackData($data);

        $parameters = $req['parameters'];
        $invokeName = $req['methodName'];

        // 根据调用不同方法
        switch ($invokeName) {
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
     * @param Server $server
     * @param $fd
     * @param $fromId
     */
    public function onClose( Server $server, $fd, $fromId )
    {

    }

    /**
     * @param Server $server
     * @param $taskId
     * @param $fromId
     * @param $data
     */
    public function onTask(Server $server, $taskId, $fromId, $data)
    {
        $params = self::getHandlerParams($data, $this->conf);

        // 设置到swoole_table里
        $processName = 'task_' . $data['jobId'];

        $exist = true;
        if (!$this->table->get($data['jobId'])) {
            $this->table->set($data['jobId'], ['task_id' => $taskId, 'log_id' => $data['logId'], 'process_name' => $processName]);
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
            self::killScriptProcess($processName);
        }

        self::appendLog($data['logDateTim'], $data['logId'], '执行task参数：' . json_encode($params));
        // 按照队列执行
        return JobStrategy::serial($data, $this->conf['server'], $params, $this->table);
    }

    /**
     * task进程完成后调用
     *
     * @param Server $server
     * @param $taskId
     * @param $data
     */
    public function onFinish(Server $server, $taskId, $data)
    {
        $data = json_decode($data, true);

        $this->table->del($data['job_id']);

        $bizCenter = new BizCenter($this->conf['xxljob']['host'], $this->conf['xxljob']['port']);
        $time = self::convertSecondToMicroS();
        $logId = $data['log_id'];
        $logTime = $data['log_date_time'];
        $requestId = $data['request_id'];

        $executeResult = [
            'code' => Code::SUCCESS_CODE,
            'msg'  => '脚本执行完成，结束脚本运行',
        ];
        if (isset($data['exec_result_msg']) && $data['exec_result_msg']) {
            $searchResult = stripos(strtolower($data['exec_result_msg']), 'success');
            // 没找到则执行有问题
            if (is_bool($searchResult)) {
                $executeResult['code'] = Code::ERROR_CODE;
            }
            $executeResult['content'] =  $data['exec_result_msg'];
            // 追加执行结果
            self::appendLog($data['log_date_time'], $data['log_id'], '脚本执行结果:' . $data['exec_result_msg']);
        }
        // 结果回调
        $result = $bizCenter->callback($time, $logId, $requestId, $logTime, $executeResult);

        $msg = Code::SUCCESS_CODE;
        if ($result) {
            $msg = Code::SUCCESS_CODE;
        }
        self::appendLog($data['log_date_time'], $data['log_id'], 'task回调:' . $msg);

        self::appendLog($data['log_date_time'], $data['log_id'], '任务执行完成');
    }

    /**
     * @return bool
     */
    protected function reload()
    {
        $managerId = $this->getManagerPid();
        if (!$managerId) {
            return false;
        } elseif (!posix_kill($managerId, 10)) {
            return false;
        }
        return true;
    }

    /**
     * @param $processName
     */
    public function setProcessNameProperty($processName)
    {
        $this->processName = $processName;

        self::setProcessName($processName);
    }

    /**
     * @param $msg
     */
    public function log($msg)
    {
        if (isset($this->setting['log_file']) && file_exists($this->setting['log_file'])) {
            error_log($msg . PHP_EOL, 3, $this->setting['log_file']);
        }
        echo $msg . PHP_EOL;
    }

    /**
     * 初始化server运行时资源
     */
    protected function initRuntime()
    {
        $this->setting = array_merge($this->setting, $this->conf['setting']);

        $this->masterPidFile = $this->runPath . '/' . $this->processName . '.master.pid';
        $this->managerPidFile = $this->runPath . '/' . $this->processName . '.manager.pid';
        // table
        $this->table = new Table($this->conf['table']['size']);
        $this->table->column('task_id', Table::TYPE_INT);
        $this->table->column('process_name', Table::TYPE_STRING, 100);
        $this->table->create();
    }

    /**
     * 初始化server
     */
    protected function initServer()
    {
        $this->server = new Server($this->conf['server']['host'], $this->conf['server']['port']);
        if (!empty($this->conf['server']['process_name'])) {
            $this->processName = $this->conf['server']['process_name'];
        }
        $this->server->set($this->setting);
        // 注册回调事件
        $this->server->on('start',   [$this, 'onStart']);
        $this->server->on('connect', [$this, 'onConnect']);
        $this->server->on('receive', [$this, 'onReceive']);
        $this->server->on('close',   [$this, 'onClose']);
        $this->server->on('managerStart', array($this, 'onManagerStart'));

        if (isset($this->conf['setting']['worker_num'])) {
            $this->server->on('workerStart', array($this, 'onWorkerStart'));
        }

        if (isset($this->conf['setting']['task_worker_num'])) {
            $this->server->on('Task', array($this, 'onTask'));
            $this->server->on('Finish', array($this, 'onFinish'));
        }
    }
    /**
     * @return bool|string
     */
    protected function getManagerPid()
    {
        $pid = false;
        if (file_exists($this->managerPidFile)) {
            $pid = file_get_contents($this->managerPidFile);
        }
        return $pid;
    }

    /**
     * @return bool|string
     */
    protected function getMasterPid()
    {
        $pid = false;
        if (file_exists($this->masterPidFile)) {
            $pid = file_get_contents($this->masterPidFile);
        }
        return $pid;
    }

    /**
     * 启动
     */
    protected function start()
    {
        $this->server->start();
    }

    /**
     * 关闭
     */
    protected function shutdown()
    {
        $masterId = $this->getMasterPid();
        if (!$masterId) {
            $this->log("[warning] " . $this->processName . ": can not find master pid file");
            $this->log($this->processName . ": stop\033[31;40m [FAIL] \033[0m");
            return false;
        } elseif (!posix_kill($masterId, 15)) {
            $this->log("[warning] " . $this->processName . ": send signal to master failed");
            $this->log($this->processName . ": stop\033[31;40m [FAIL] \033[0m");
            return false;
        }
        unlink($this->masterPidFile);
        unlink($this->managerPidFile);
        return true;
    }

    /**
     * @return bool
     */
    protected function status()
    {
        $this->log("*****************************************************************");
        $this->log("Summary: ");
        $this->log("Swoole Version: " . SWOOLE_VERSION);
        if (!$this->checkServerIsRunning()) {
            $this->log($this->processName . ": is running \033[31;40m [FAIL] \033[0m");
            $this->log("*****************************************************************");
            return false;
        }
        $this->log($this->processName . ": is running \033[31;40m [OK] \033[0m");
        $this->log("master pid : is " . $this->getMasterPid());
        $this->log("manager pid : is " . $this->getManagerPid());
        $this->log("*****************************************************************");
    }

    /**
     * @return bool
     */
    protected function checkServerIsRunning()
    {
        $pid = $this->getMasterPid();
        return $pid && $this->checkPidIsRunning($pid);
    }

    /**
     * @param $pid
     * @return bool
     */
    protected function checkPidIsRunning($pid)
    {
        return posix_kill($pid, 0);
    }

    /**
     * 创建一个定时器
     *
     * @param $server
     */
    protected function makeTick($server)
    {
        // 定时器去注册
        $server->tick($this->conf['xxljob']['registry_interval_ms'], function() {
            $time = self::convertSecondToMicroS();
            if (!empty($this->conf['xxljob']['open_registry'])) {
                $bizCenter = new BizCenter($this->conf['xxljob']['host'], $this->conf['xxljob']['port']);
                $bizCenter->openRegistry = $this->conf['xxljob']['open_registry'];
                $bizCenter->registry($time, $this->conf['server']['app_name'], $this->conf['server']['host'] . ':' . $this->conf['server']['port']);
            }
        });
    }
}
