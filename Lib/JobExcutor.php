<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/13
 * Time: 3:57 PM
 */

namespace Lib;


class JobExcutor
{
    public static function loadJob($job_id, Table $table)
    {

        return $table->get($job_id);
    }


    public static function removeJob($job_id, Table $table)
    {
        return $table->get($job_id);
    }
}