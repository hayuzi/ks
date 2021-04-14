Swoole
==

## Process
pcntl不足
- 没有提供进程间通信
- 不支持重定向标准输入和输出
- 只提供了fork这样原始的的接口，容易使用错误

Process提供了比pcntl更强大的功能
- 进程间通信
- 重定向标准输入和输出（子进程echo不会打印屏幕而是写入管道）
- exec接口，创建的进程可以执行其他程序，与原来PHP父进程可以方便的通信
- 协程环境中无法使用 Process模块，可以runtime hook + proc_open实现



## SERVER 运行的两种模式

#### SWOOLE_RPOCESS（ 进程模式 ）
所有的客户端TCP连接都是和主进程建立的，内部复杂，用了大量的进程间通信、进程管理机制

优点：
- 连接与数据请求发送分离
- Worker进程发送知名错误，连接不会被切断
- 可以实现单连接并发，仅保持少量TCP连接，请求可以并发地在多个Worker进程中处理

缺点：
- 存在2次IPC的开销，Master进程与worker进程需要使用unixSocket进行通信

##### 该模式下进程关系



#### SWOOLE_BASE （ 基础模式 ）
SWOOLE_BASE 这种模式就是传统的异步非阻塞 Server。
与 Nginx 和 Node.js 等程序是完全一致的

当有 TCP 连接请求进来的时候，所有的 Worker 进程去争抢这一个连接，并最终会有一个 worker 进程成功直接和客户端建立 TCP 连接，
之后这个连接的所有数据收发直接和这个 worker 通讯，不经过主进程的 Reactor 线程转发。
- BASE 模式下没有 Master 进程的角色，只有 Manager 进程的角色。
- 每个 Worker 进程同时承担了 SWOOLE_PROCESS 模式下 Reactor 线程和 Worker 进程两部分职责。
- BASE 模式下 Manager 进程是可选的，当设置了 worker_num=1，并且没有使用 Task 和 MaxRequest 特性时，底层将直接创建一个单独的 Worker 进程，不创建 Manager 进程

优点:
- BASE 模式没有 IPC 开销，性能更好
- BASE 模式代码更简单，不容易出错

缺点:
- TCP 连接是在 Worker 进程中维持的，所以当某个 Worker 进程挂掉时，此 Worker 内的所有连接都将被关闭
- 少量 TCP 长连接无法利用到所有 Worker 进程
- TCP 连接与 Worker 是绑定的，长连接应用中某些连接的数据量大，这些连接所在的 Worker 进程负载会非常高。但某些连接数据量小，所以在 Worker 进程的负载会非常低，不同的 Worker 进程无法实现均衡。
- 如果回调函数中有阻塞操作会导致 Server 退化为同步模式，此时容易导致 TCP 的 backlog 队列塞满问题

在 BASE 模式下，Server 方法除了 send 和 close 以外，其他的方法都不支持跨进程执行。





## swoole与golang的一些对比 ( 来自老版本swoole文档 )
#### 开发效率
- Go语言是本质上是静态语言，开发效率稍差，但性能更强，更适合底层软件的开发
- Swoole使用PHP语言，动态脚本语言，开发效率最佳，更适合应用软件的开发

#### IO模型
- go语言使用单线程eventloop处理IO事件，多线程实现协程调度，执行用户层代码
- swoole使用多线程eventloop处理IO事件，多进程执行用户层php代码

> Go对与IO事件的处理是单线程的，无法利用多核，吞吐量稍弱于swoole
在实际的TCP/UDP密集IO压测中，swoole表现要稍优于go

Go协程(goroutine)是运行在多线程上的，线程可以共享堆栈和文件描述符，功能更强大，在实现连接池、并发库方面更有优势。额外的带来的一个问题是，存在数据同步问题，需要用户自行考虑加锁。

Swoole的用户代码运行在多进程环境，无需考虑加锁问题。但无法直接访问内存和资源。需要借助Task进程实现中转。


#### 语言性能
- go语言是静态编译的，语言本身的性能大大超过php，密集计算更有优势
- php是动态解释执行的，语言性能较差，不适合密集计算程序

