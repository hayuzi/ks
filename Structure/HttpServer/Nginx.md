Nginx
==

> Nginx的一些介绍的参考 https://zhuanlan.zhihu.com/p/108031600
> epoll原理解释  https://zhuanlan.zhihu.com/p/63179839

## Nginx的进程模型
- 多进程：一个 Master 进程、多个 Worker 进程
- Master 进程：管理 Worker 进程
- - 对外接口：接收外部的操作（信号）
- - 对内转发：根据外部的操作的不同，通过信号管理 Worker
- - 监控：监控 worker 进程的运行状态，worker 进程异常终止后，自动重启 worker 进程
- Worker 进程：所有 Worker 进程都是平等的
- - 实际处理：网络请求，由 Worker 进程处理；
- - Worker 进程数量：在 nginx.conf 中配置，一般设置为核心数，充分利用 CPU 资源，同时，避免进程数量过多，避免进程竞争 CPU 资源，增加上下文切换的损耗。

## 工作原理

#### HTTP连接建立和请求处理过程
- Nginx 启动时，Master 进程，加载配置文件
- Master 进程，初始化监听的 socket
- Master 进程，fork 出多个 Worker 进程
- Worker 进程，竞争新的连接，获胜方通过三次握手，建立 Socket 连接，并处理请求

#### Nginx的惊群效应与解决
老版本的 Worker 竞争socket连接的方式，存在惊群效应的问题

- 待完善
Master进程与Worker进程之间的关系以及socket连接分布的问题



## 基于epoll的I/O多路复用机制
Nginx使用了基于epoll的I/O多路复用机制

## select/poll/epoll 三种I/O多路复用

### select
- select 维护一个 fd_set
- 每次调用 select，都需要把 fd 集合从用户态拷贝到内核态，这个开销在 fd 很多时会很大
- 同时每次调用 select 都需要在内核遍历传递进来的所有 fd，这个开销在 fd 很多时也很大
- select 支持的文件描述符数量只有 1024，非常小


### poll
poll 和 select 原理一样，不过相比较 select 而言，poll 可以支持大于 1024 个文件描述符。

### epoll
相比较 select 和 poll，epoll 的最大特点是：
- epoll 现在是线程安全的，而 select 和 poll 不是。
- epoll 内部使用了 mmap 共享了用户和内核的部分空间，避免了数据的来回拷贝。
- epoll 基于事件驱动，epoll_ctl 注册事件并注册 callback 回调函数，epoll_wait 只返回发生的事件避免了像 select 和 poll 对事件的整个轮寻操作。

