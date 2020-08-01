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
use Lib\Core\Process;

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

    //
    protected $currentServer = null;

    protected $availableServers = [];

    protected $unavailableServers = [];

    protected $xxlJobServers = [];

    /* @see  Table*/
    protected $cacheTable;

    /* @see  Table*/
    protected $panicTable;
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
            case 'countTable':
                $this->table->count();
                break;
            default:
                echo 'Usage:php Bin/index.php start | stop | reload | restart | status | help' . PHP_EOL;
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
        $workerId = $server->worker_id;
        self::setProcessNameProperty($this->processName . ': worker process ' . $workerId);
        if (version_compare(SWOOLE_VERSION, '1.10.5', '<')) {
            $this->makeTick($server);
        }
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
        // 增加定时器 有些版本不支持需要放在work启动的时候
        if (version_compare(SWOOLE_VERSION, '1.10.5', '>=')) {
            $this->makeTick($server);
        }
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
                ExecutorCenter::run($parameters, $req['requestId'], $server, $this->conf, $this->table, $this->cacheTable);
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
    public function onTask(Server $server, $taskId, $fromId, $taskData)
    {
        $data = $taskData['job_data'];
        self::appendLog($data['logId'], 'task_worker开始处理..');
        try {
            $params = $taskData['params'];
            // 按照队列执行
            return JobStrategy::serial($data, $this->conf['server'], $params, $this->table);
        } catch (\Exception $exception) {
            return json_encode([
                'job_id' => $data['jobId'],
                'request_id' => $data['requestId'],
                'log_id' => $data['logId'],
                'log_date_time' => $data['logDateTim'],
                'exec_result_msg' => 'failed.' . $exception->getMessage()
            ]);
        }
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
            self::appendLog($data['log_id'], '脚本执行结果:' . $data['exec_result_msg']);
        }
        // 结果回调
        $retryTimes = self::$retryTimes;
        while ($retryTimes > 0) {
            $currentServer = self::getCurrentServer($this->cacheTable);
            $time = self::convertSecondToMicroS();
            self::appendLog($data['log_id'], $currentServer);
            $bizCenter = self::getBizCenterByHostInfo($currentServer);
            $result = $bizCenter->callback($time, $logId, $requestId, $logTime, $executeResult);
            if ($result) {
                self::appendLog($data['log_id'], $currentServer . '执行完成,task回调:' . json_encode([$result, $bizCenter->getHost()]));
                $retryTimes = 0;
            } else {
                $retryTimes--;
                self::appendLog($data['log_id'], $currentServer . '执行完成,task回调失败:' . json_encode([$result, $bizCenter->getHost()]));
                sleep(20);
            }
        }
        self::appendLog($data['log_id'], '任务执行完成end');
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
        $this->table->column('log_id', Table::TYPE_INT, 8);
        $this->table->column('request_id', Table::TYPE_STRING, 100);
        $this->table->column('log_date_time', Table::TYPE_STRING, 30);
        $this->table->create();
        $this->currentServer = $this->getMainServer();
        $this->xxlJobServers = $this->getXXLJobServers();

        //
        $this->cacheTable = new Table($this->conf['table']['size']);
        $this->cacheTable->column('server_info', Table::TYPE_STRING, 255);
        $this->cacheTable->create();
        $this->cacheTable->set('current_server', ['server_info' => $this->getMainServer()]);
        $this->xxlJobServers = $this->getXXLJobServers();

        // 报警机制
        $this->panicTable = new Table(10);
        // server 信息
        $this->panicTable->column('server', Table::TYPE_STRING, 255);
        // 告警次数
        $this->panicTable->column('panic_times', Table::TYPE_INT, 2);
        $this->panicTable->create();
    }

    /**
     * 初始化server
     */
    protected function initServer()
    {
        try {
            $this->server = new Server($this->conf['server']['host'], $this->conf['server']['port']);
            if (!empty($this->conf['server']['process_name'])) {
                $this->processName = $this->conf['server']['process_name'];
            }
            $this->server->set($this->setting);
            // 注册回调事件
            $this->server->on('start', [$this, 'onStart']);
            $this->server->on('connect', [$this, 'onConnect']);
            $this->server->on('receive', [$this, 'onReceive']);
            $this->server->on('close', [$this, 'onClose']);
            $this->server->on('managerStart', array($this, 'onManagerStart'));

            if (isset($this->conf['setting']['worker_num'])) {
                $this->server->on('workerStart', array($this, 'onWorkerStart'));
            }

            if (isset($this->conf['setting']['task_worker_num'])) {
                $this->server->on('Task', array($this, 'onTask'));
                $this->server->on('Finish', array($this, 'onFinish'));
            }
        } catch (\Exception $exception) {
            echo $exception->getMessage();
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
        // 摘除注册
        $masterId = $this->getMasterPid();
        $this->registryRemove();
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
        $server->tick($this->conf['xxljob']['registry_interval_ms'], function() use($server)  {
            $permitPanicTimes = self::$retryTimes;
            foreach ($this->xxlJobServers as $key => $hostInfo) {
                $res = $this->sendToProcessor($hostInfo);
                $formatDatetime = $this->formatDatetime(time());
                $appName = $this->conf['server']['app_name'];
                $address = $this->conf['server']['host'] . ':' . $this->conf['server']['port'];
                $hKey = md5($hostInfo);
                if ($res) {
                    $this->availableServers[] = $hostInfo;
                    $this->log($hostInfo);
                    $this->panicTable->set($hKey, ['server' => $hostInfo, 'panic_times' => 0]);
                    $this->log('[' . $formatDatetime . ']' . $appName . ':' . $address  . ' -> registry 成功' . PHP_EOL);
                } else {
                    $info = $this->panicTable->get($hKey);
                    $panicTimes = empty($info['panic_times']) ? 0: $info['panic_times'];
                    if ($panicTimes < $permitPanicTimes) {
                        $panicTimes += 1;
                        $this->panicTable->set($hKey, ['server' => $hostInfo, 'panic_times' => $panicTimes]);
                        $timesec=10000*$panicTimes;
                        $server->after($timesec, function () use ($appName, $hostInfo, $panicTimes, $timesec) {
                            self::panic($appName, 'registry failed. Target host:' . $hostInfo . ' panic times:' . $panicTimes . ' timeafter:' . $timesec);
                        });
                    }
                }
            }
            // 优先用主的
            if (in_array($this->getMainServer(), $this->availableServers)) {
                $this->setCurrentServer($this->getMainServer());
            } else{
                !empty($this->availableServers[0]) && $this->setCurrentServer($this->availableServers[0]);
            }
        });
    }

    /**
     * 设置当前主server
     *
     * @param $hostInfo
     */
    protected function setCurrentServer($hostInfo)
    {
        $this->log('current log:' . $hostInfo);
        /* @see Table::set()*/
        $this->cacheTable->set('current_server', ['server_info' => $hostInfo]);
    }

    /**
     * 获取主要的server
     *
     * @return string
     */
    protected function getMainServer()
    {
        return $this->conf['xxljob']['host'] . ':' . $this->conf['xxljob']['port'];
    }


    /**
     * 获取所有的server
     *
     * @return array
     */
    protected function getXXLJobServers()
    {
        $servers = [];
        if (!empty($this->conf['xxljob']['open_registry'])) {
            $servers[] = $this->conf['xxljob']['host'] . ':' . $this->conf['xxljob']['port'];
        }
        // 备份的server
        if (!empty($this->conf['xxljob_backup']['open_registry']) && !empty($this->conf['xxljob_backup']['host_urls'])) {
            $hostList = explode(',', $this->conf['xxljob_backup']['host_urls']);
            $servers = array_merge($hostList, $servers);
        }
        return $servers;
    }


    /**
     * 注册移除
     */
    protected function registryRemove()
    {
        foreach ($this->xxlJobServers as $hostInfo) {
            $res = $this->sendToProcessor($hostInfo, 'registryRemove');
            $formatDatetime = $this->formatDatetime(time());
            $appName = $this->conf['server']['app_name'];
            $address = $this->conf['server']['host'] . ':' . $this->conf['server']['port'];
            if ($res) {
                $this->log($hostInfo);
                $this->log('[' . $formatDatetime . ']' . $appName . ':' . $address  . ' -> registryRemove 成功' . PHP_EOL);
            }
        }
    }

    /**
     * @param $hostInfo
     * @param string $method
     * @return mixed
     */
    protected function sendToProcessor($hostInfo, $method = 'registry')
    {
        $time = self::convertSecondToMicroS();
        $bizCenter = $this->getBizCenterByHostInfo($hostInfo);
        $bizCenter->openRegistry = 1;
        /* @see BizCenter::registry() */
        /* @see BizCenter::registryRemove()*/
        $res = $bizCenter->$method($time, $this->conf['server']['app_name'], $this->conf['server']['host'] . ':' . $this->conf['server']['port']);
        return $res;
    }
}
