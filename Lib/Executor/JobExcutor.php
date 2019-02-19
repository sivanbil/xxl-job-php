<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/13
 * Time: 3:57 PM
 */

namespace Lib\Executor;

use Lib\Core\Table;

class JobExecutor
{
    /**
     * @param $job_id
     * @param Table $table
     * @return array|bool|string
     */
    public static function loadJob($job_id, Table $table)
    {

        return $table->get($job_id);
    }


    /**
     * @param $job_id
     * @param Table $table
     * @return array|bool|string
     */
    public static function removeJob($job_id, Table $table)
    {
        return $table->get($job_id);
    }
}