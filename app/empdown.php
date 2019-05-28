<?php
/**
 * Created by PhpStorm.
 * User: tanlex
 * Date: 2019/5/28
 * Time: 15:26
 */
//引入MyPhpSpreadsheet类生成excel
require_once __DIR__ . '/../lib/MyPhpSpreadsheet.php';

//创建TCP服务
$serv = new swoole_server("0.0.0.0", 9601);

//设置异步任务的工作进程数量
$serv->set([
    'worker_num' => 4,        //worker process num
    'max_request' => 3,       //每个worker进程任务数
    'task_worker_num' => 4,   //task worker process num
    'task_enable_coroutine' => true, //异步任务支持协程
    'task_max_request'      => 3, //每个task进程任务数
]);

//创建共享内存
$table = new swoole_table(1024);
$table->column('fd', swoole_table::TYPE_INT);
$table->create();
//将table保存在serv对象上
$serv->table = $table;

$serv->on('receive', function($serv, $fd, $from_id, $data) {

    //接受TCP客户端数据并投递异步任务
    $task_id = $serv->task($data);

    //TCP客户端关联任务ID存储到swoole_table
    $serv->table->set('empdown_'.$task_id,['fd'=>$fd]);

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
        $where .= 'and c.name like "%'.$keyword.'%" ';
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
        'password' => 'root3306',
        'database' => 'tang',
    ]);
//    var_dump($swoole_mysql);

    $data_sql = "select c.name,c.status,c.create_time
                 from tang.user c
                 $where order by c.id desc";
    $oneResult = $swoole_mysql->query($data_sql);
//    var_dump($oneResult);

    //实例化excel生成类
    $MyPhpSpreadsheet = new MyPhpSpreadsheet();
    //xlsx表格第一行
    $title = ['姓名','状态','创建时间'];
    $files = $MyPhpSpreadsheet->arrayToXlsx($oneResult,$title);

    //返回任务执行的结果
    $task->finish(json_encode($files));
});

//处理异步任务的结果
$serv->on('finish', function ($serv, $task_id, $data) {
    //获取异步任务结果
    echo "AsyncTask[$task_id] Finish: $data".PHP_EOL;

    //获取TCP客户端
    $fd = $serv->table->get('empdown_'.$task_id,'fd');
    if(!empty($fd)){
        $serv->send($fd, $data);
        $serv->table->del('empdown_'.$task_id);
    }
});

//TCP客户端关闭回调
$serv->on('close',function(swoole_server $server, int $fd, int $reactorId){
    echo "TCP Client :".$fd." closed ".PHP_EOL;
});

$serv->start();