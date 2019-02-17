<?php
/**
 * 任务执行策略
 *
 * @author sivan
 * @description job strategy
 */
namespace Lib;

class JobStrategy
{
    // 串行
    const SERIAL = 'SERIAL_EXECUTION';
    // 丢弃后续调度请求
    const DISCARD_NEXT_SCHEDULING = 'DISCARD_LATER';
    // 关闭当前任务调度进程，启用新的调度
    const USE_NEXT_SCHEDULING = 'COVER_EARLY';

    /**
     * @param $data
     * @param $process_name
     * @param $exist
     * @return false|string
     */
    public static function serial($data, $process_name, $params, $exist)
    {
        // 然后执行脚本
        $process = new Process(function (Process $worker) use ($data, $process_name, $params, $exist) {
            while ($msg = $worker->pop()) {
                if ($msg === false) {
                    break;
                }
                if ($exist) {
                    $worker->push($msg);
                    break;
                }
                // 丢弃之后的
                $params = json_decode($msg, true);
                $worker->exec($this->conf['server']['php'], $params);
            }
        }, false);
        $process->useQueue(1, 2);
        $process->start();
        $process->push(json_encode($params));
        $wait_res = Process::wait();
        if ($wait_res['code']) {
            echo  "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
            exit;
        }
        return json_encode([
                'job_id' => $data['jobId'],
                'request_id' => $data['requestId'],
                'log_id' => $data['logId'],
                'log_date_time' => $data['logDateTim']]
        );
    }

    public static function discard($data)
    {

        return json_encode([
                'job_id' => $data['jobId'],
                'request_id' => $data['requestId'],
                'log_id' => $data['logId'],
                'log_date_time' => $data['logDateTim']]
        );
    }

    public static function next()
    {

    }
}

