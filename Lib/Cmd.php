<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/1
 * Time: 11:08 AM
 */
namespace Lib;

class Cmd
{
    use JobTool;

    /**
     * 需要支持以下几种命令
     * start    启动
     * stop     停止
     * reload   重载
     * restart  重启
     * shutdown 关闭
     * status   查看状况
     * list     列表
     * startAll 启动所有
     */

    public static function exec($argv)
    {
        // 具体执行逻辑

    }


    /**
     * 获取所有支持的命令
     *
     * @return array
     */
    public static function getSupportCmds()
    {
        return [
            'start', 'stop', 'reload', 'restart',
            'shutdown', 'status', 'list', 'startAll'
        ];
    }

    public static function printCmdHelp()
    {
        self::tips();
    }
}