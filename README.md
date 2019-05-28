# async_excel
   项目简介：
   PHP项目后台常有下载导出数据的功能，当数据量比较小的时候，查询也比较快，
这时候使用PHP同步代码下载都可以实现，不会出现超时的情况（502/504错误），
当数据量大了之后，查询会很慢，生成的excel也很慢，比如可能需要3分钟，
这个时候同步代码就有问题了。解决方法就是使用异步代码，不超时下载。采用swoole扩展，
集成websocket客户端、websocket服务端、TCP客户端、TCP服务端、swoole_table缓存、
协程mysql、异步task任务。下载生成.xlsx文件采用PhpSpreadsheet类库。
   
   流程说明：
   php /app/empdown.php 为TCP服务端
   php /app/empdown_ws.php 为WebSocket服务端，并在接收到WebSocket客户端的数据时创建
   TCP客户端，并转发数据到TCP服务端
   
   WebSocket客户端->WebSocket服务端->onMessage函数中创建TCP客户端->转发数据至TCP服务端
   ->异步Task任务处理数据生成excel->Task进程异步处理完成后推送数据至TCP客户端->TCP客户端
   接收数据推送至WebSocket客户端。
   
   目录结构：
   app --- 服务目录
   lib --- PhpSpreadsheet目录
   upload --- 生成的xlsx文件目录
   demo --- websocket客户端调用代码目录
   vendor --- composer目录
