PHP知识提纲
==

## 语言基础（手册相关）
- 安装/模块
- - 该部分内容可以通过访问PHP官方手册来学习 [官方手册](https://www.php.net/manual/zh/index.php)
- PHP运行模型（多进程/cli/fpm/协程/swoole）
- - cli
- - php-fpm 下面讲
- - swoole 下面讲

#### PHP的四层体系

从下到上依次为
- Application（上层应用）
- SAPI（服务器应用程序编程接口）
- Extensions（扩展）
- Zend引擎（核心）

#### PHP 常见的运行模式
SAPI 即服务器应用程序编程接口，是 PHP 与其他应用交互的接口，PHP 脚本要执行有很多方式，比如通过 Web 服务器、命令行下或者嵌入在其他程序中。
SAPI 提供了一个和外部通信的接口，常见的 SAPI 有：cgi、fast-cgi、cli、apache 模块的 DLL、isapi 等。

##### CGI
CGI 即通用网关接口（Common Gateway Interface），它是一段程序，通俗的讲 CGI 就象是一座桥，把网页和 WEB 服务器中的执行程序连接起来，
它把 HTML 接收的指令传递给服务器的执行程序，再把服务器执行程序的结果返还给 HTML。

CGI 的跨平台性能极佳，几乎可以在任何操作系统上实现。

CGI 在遇到连接请求后，会先要创建 CGI 的子进程，激活一个 CGI 进程，然后处理请求，处理完后结束这个子进程，这就是 fork-and-execute 模式。

使用 CGI 方式的服务器有多少连接请求就会有多少 CGI 子进程，子进程反复加载 会导致 CGI 性能低下。当用户请求数量非常多时，会大量挤占系统的资源，如内存、CPU 时间等，造成性能低下。

##### FastCGI
fast-cgi 是 CGI 的升级版本，FastCGI 像是一个常驻（long-live）型的 CGI，它激活后可以一直执行着。

FastCGI 的工作原理：
Web Server 启动时载入 FastCGI 进程管理器（IIS ISAPI 或 Apache Module）；
FastCGI 进程管理器自身初始化，启动多个 CGI 解释器进程（可见多个 php-cgi）并等待来自 Web Server 的连接；
当客户端请求到达 Web Server 时，FastCGI 进程管理器选择并连接到一个 CGI 解释器。
Web server 将 CGI 环境变量和标准输入发送到 FastCGI子进程 php-cgi；
FastCGI 子进程完成处理后将标准输出和错误信息从同一连接返回 Web Server。
当 FastCGI 子进程关闭连接时，请求便处理完成了。
FastCGI 子进程接着等待并处理来自 FastCGI 进程管理器（运行在 Web Server 中）的下一个连接。 
在 CGI 模式中，php-cgi 在此便退出了。

##### php-fpm
是 PHP（Web Application）对 Web Server 提供的 FastCGI 协议的接口程序，额外还提供了相对智能一些任务管理。

PHP-CGI只是个CGI程序，他自己本身只能解析请求，返回结果，不会进程管理。
PHP-FPM是用于调度管理PHP解析器php-cgi的管理程序

作为一种多进程的模型，Fpm由一个master进程加多个worker进程组成。

当master进程启动时，会创建一个socket，但是他本身并不接收/处理请求。
他会fork出子进程来完成请求的接收和处理。所以，master的主要职责是管理worker进程，比如fork出worker进程，或者kill掉worker进程。

Fpm中的worker进程的主要工作是处理请求。
每个worker进程会竞争Accept请求，接收成功的那一个来处理本次请求。
请求处理完毕后又重新进入等待状态。此处需要注意的是：一个worker进程在同一时刻只能处理一个请求。
这与Nginx的事件驱动模型有很大的区别。
Nginx的子进程使用epoll来管理socket，当一个请求的数据还未发送完成的话他就会处理下一个请求，即同一时刻，一个子进程会连接多个请求。
而且Nginx的worker进程书通常设置为核心数。
而Fpm的这种子进程处理方式则大大简化了PHP的资源管理，使得在Fpm模式下我们不需要考虑并发导致的资源冲突。


##### APACHE2HANLER
PHP 作为 Apache 的模块，Apache 服务器在系统启动后，预先生成多个进程副本驻留在内存中，一旦有请求出现，就立即使用这些空余的子进程进行处理，这样就不存在生成子进程造成的延迟了。
这些服务器副本在处理完一次 HTTP 请求之后并不立即退出，而是停留在计算机中等待下次请求。对于客户浏览器的请求反应更快，性能较高。


## 一些简单的源码功能介绍

#### 如何实现的 弱类型
通过底层 Zval结构实现。 Zval 主要由以下 3 部分组成。
- Type：指定了变量所述的类型（整数、字符串、数组等）；
- refcount&is_ref：用来实现引用计数；
- value：是核心部分，存储了变量的实际数据。

Zval 用来保存一个变量的实际数据。因为要存储多种类型，所以 Zval的value 是一个 union，也由此实现了弱类型。

#### GC
通过引用计数实现
对于循环引用，我们采用...(等待完善)


## 相关框架
- Laravel
- Yaf
- Symfony
- Yii

## 代码规范（PSR）

## 设计模式（语言通用）

## Swoole

###  基础知识
- TCP / UDP / HTTP / WebSocket / MQTT服务端
- 服务端实现
- 进程线程结构
- [swoole进程模型及其两种运行模式](/Language/PHP/Swoole.md)
  
###  网络编程知识

### 基于swoole的微服务框架
Hyperf
swoft

## PHP源码，原理与优化
- Zend引擎架构
- PHP扩展开发（C/C++）