<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/5/23
 * Time: 15:26
 */

//websocket服务
$ws_server = new Swoole\WebSocket\Server("0.0.0.0", 9602);

//在https站点访问的时候需要设置SSL证书
$ws_server->set(
    [
        'worker_num' => 4,        //worker process num
        'max_request' => 3,       //每个worker进程任务数
        'ssl_cert_file' => '/etc/data/async_excel.cer', //证书
        'ssl_key_file' => '/etc/data/async_excel.key', //key
    ]
);

$ws_server->on('open', function (Swoole\WebSocket\Server $ws_server, $request) {
    echo "server: handshake success with fd{$request->fd}\n";
});

$ws_server->on('message', function (Swoole\WebSocket\Server $ws_server, $frame) {
    echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";

    $data = $frame->data; //接收websocket客户端发送的数据
    $fd   = $frame->fd; //websocket客户端ID

    /********************创建TCP异步客户端********************/
    $client = new Swoole\Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
    $client->on("connect", function(swoole_client $cli) use ($data){
        //给TCP服务端发送数据（也就是转发websocket客户端发送的数据）
        $cli->send($data."\n");
    });
    //接受TCP服务端的回调函数
    $client->on("receive", function(swoole_client $cli, $data) use ($ws_server,$fd){
        //调用websocket服务向websocket客户端返回数据
        $ws_server->push($fd, $data);
    });
    $client->on("error", function(swoole_client $cli){
        echo "error\n";
    });
    $client->on("close", function(swoole_client $cli){
        echo "Connection close\n";
    });
    $client->connect('127.0.0.1', 9601);
    /********************创建TCP异步客户端********************/
});

$ws_server->on('close', function ($ws_server, $fd) {
    echo "client {$fd} closed\n";
});

$ws_server->start();

