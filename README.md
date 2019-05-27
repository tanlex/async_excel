# async_excel

   PHP项目后台常有下载导出数据的功能，当数据量比较小的时候，
查询也比较快，这时候使用PHP同步代码下载都可以实现，不会
出现超时的情况（502/504错误），当数据量大了之后，查询会
很慢，生成的excel也很慢，比如可能需要3分钟，这个时候同步
代码就有问题了。解决方法就是使用异步代码。
    采用swoole扩展，集成websocket客户端、websocket服务端、
TCP客户端、TCP服务端、协程redis、协程mysql、异步任务。
    下载生成.xlsx文件采用PhpSpreadsheet库。
