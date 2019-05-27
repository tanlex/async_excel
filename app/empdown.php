<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/5/23
 * Time: 15:26
 */
//引入MyPhpSpreadsheet类生成excel
require_once __DIR__ . '/../lib/MyPhpSpreadsheet.php';

$serv = new swoole_server("0.0.0.0", 9601);

//设置异步任务的工作进程数量
$serv->set([
    'worker_num' => 4,        //worker process num
    'max_request' => 3,       //每个worker进程任务数
    'task_worker_num' => 4,   //task worker process num
    'task_enable_coroutine' => true, //异步任务支持协程
    'task_max_request'      => 3, //每个task进程任务数
]);

$serv->on('receive', function($serv, $fd, $from_id, $data) {

    //接受TCP客户端数据并投递异步任务
    $task_id = $serv->task($data);

    //创建协程redis
    go(function () use ($task_id,$fd) {
        $redis = new Swoole\Coroutine\Redis();
        $redis->connect('127.0.0.1', 6379); //连接redis
        $redis->auth('123456'); //redis认证
        //任务ID关联TCP客服端，存储到hash表
        $res = $redis->hSet('empdown_tcp_key',$task_id,$fd);
    });

});

//处理异步任务
$serv->on('Task', function ($serv, Swoole\Server\Task $task) {
/*
    //来自哪个`Worker`进程
    $task->worker_id;
    //任务的编号
    $task->id;
    //任务的类型，taskwait, task, taskCo, taskWaitMulti 可能使用不同的 flags
    $task->flags;
    //任务的数据
    $task->data;
    //协程 API
    co::sleep(0.2);
    //完成任务，结束并返回数据
    $task->finish([123, 'hello']);
*/
    //异步任务处理数据
    $data = json_decode($task->data);
    $keyword = $data->keyword;
    $status  = $data->status;
    $start   = $data->start;
    $end     = $data->end;

    $where = 'where 1 ';
    if(!empty($keyword)){
        $where .= 'and concat(c.name,c.phone) like "%'.$keyword.'%" ';
    }
    if(!empty($status)){
        $where .= 'and c.status='.$status.' ';
    }
    if(!empty($start)){
        $where .= 'and c.create_time>="'.$start.'" ';
    }
    if(!empty($end)){
        $where .= 'and c.create_time<="'.$end.'" ';
    }

    //协程mysql
    $swoole_mysql = new Swoole\Coroutine\MySQL();
    $swoole_mysql->connect([
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'password' => 'root',
        'database' => 'shop',
    ]);
//    var_dump($swoole_mysql);

    $data_sql = "select c.uname,c.phone,c.status,c.create_time 
                 from shop.user c 
                 $where order by c.id desc";
    $oneResult = $swoole_mysql->query($data_sql);
//    var_dump($oneResult);

    //实例化excel生成类
    $MyPhpSpreadsheet = new MyPhpSpreadsheet();
    //xlsx表格第一行
    $title = ['姓名','手机号','状态','创建时间'];
    $files = $MyPhpSpreadsheet->arrayToXlsx($oneResult,$title);

    //返回任务执行的结果
    $task->finish(json_encode($files));
});

//处理异步任务的结果
$serv->on('finish', function ($serv, $task_id, $data) {
    //获取异步任务结果
    echo "AsyncTask[$task_id] Finish: $data".PHP_EOL;

    //创建协程redis
    go(function () use ($serv,$task_id,$data) {
        //获取100个连接的TCP客户端列表（后台操作人员不多）
        $conn_list = $serv->getClientList(0, 100);
        $redis = new Swoole\Coroutine\Redis();
        $redis->connect('127.0.0.1', 6379); //连接redis
        $redis->auth('123456'); //redis认证
        //获取任务ID对应的TCP客户端
        $fd = $redis->hGet('empdown_tcp_key',$task_id);
        //查询正在连接的TCP客户端列表是否存在任务对应的TCP客户端
        foreach($conn_list as $v){
            if($v == $fd){
                //给TCP客户端返回任务处理结果信息
                $serv->send($fd, $data);
                //从任务hash表剔除TCP客户端
                $redis->hDel('empdown_tcp_key',$task_id);
            }
        }
    });

});

$serv->start();

