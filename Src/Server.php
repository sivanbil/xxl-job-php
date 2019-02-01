<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/1
 * Time: 7:10 PM
 */
namespace Src;

use Lib\TcpServer;

class Server extends TcpServer
{

    public $server;

    public function __construct($conf) {
        $this->server = new swoole_server($conf['server']['ip'], $conf['server']['port']);
        $this->server->set($conf['setting']);

        $this->server->on('Start',   [$this, 'onStart']);
        $this->server->on('Connect', [$this, 'onConnect']);
        $this->server->on('Receive', [$this, 'onReceive']);
        $this->server->on('Close',   [$this, 'onClose']);

        $this->server->start();
    }

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