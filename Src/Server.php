<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/1
 * Time: 7:10 PM
 */
namespace Src;

class Server
{
    public function onStart( $server )
    {

    }

    public function onConnect( $server, $fd, $from_id )
    {

    }

    public function onReceive( swoole_server $server, $fd, $from_id, $data )
    {

    }

    public function onClose( $server, $fd, $from_id )
    {
    }
}