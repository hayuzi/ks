RocketMQ
==

> 这个基础的学习可以参考阿里云的文档等内容
> [文档]https://help.aliyun.com/document_detail/29532.html

### 1. 基本协议与概念

#### 核心概念

- Topic: 消息主题，一级消息类型，生产者向其发送消息
    - Partition: 分区, 物理概念，每一个topic包含一个或者多个分区
    - 消费位点: 每个Topic会有多个分区
        - 每个分区会统计当前消息的总条数，这个称为最大位点MaxOffset；
        - 分区的起始位置对应的位置叫做起始位点MinOffset。
- 生产者: 消息发布者，负责生产并发送消息至Topic
- 消费者：也称为消息订阅者，负责从Topic接收并消费消息
    - RocketMQ消息消费模式在阿里云提供的服务上主要是推模式
- 消息：生产者向Topic发送并最终传送给消费者的数据和（可选）属性的组合
- 消息属性：生产者可以为消息定义的属性，包含MessageKey和Tag
    - Message Key：消息的业务标识，唯一标识某个业务逻辑。可根据设置的Message Key对消息进行查询。
    - Tag：消息标签，二级消息类型，用来进一步区分某个Topic下的消息分类。消费者可通过Tag对消息进行过滤。消息过滤的更多信息，请参见消息过滤。
- Group: 一类生产者或者消费者，这类生产者或者消费者通常生产或消费同一类消息，且消息发布或者订阅的逻辑一致

### 2. 实现原理

[系统部署架构](https://help.aliyun.com/document_detail/112008.html)

部署架构中的概念如下所述：

- Name Server：
    - 是一个几乎无状态节点，可集群部署，在消息队列RocketMQ版中提供命名服务，更新和发现Broker服务。
- Broker：
    - 消息中转角色，负责存储消息，转发消息。分为Master Broker和Slave Broker。
    - 一个Master Broker可以对应多个Slave Broker，但是一个Slave Broker只能对应一个Master Broker。
    - Broker启动后需要完成一次将自己注册至Name Server的操作；随后每隔30s定期向Name Server上报Topic路由信息。
    - Name Server收到心跳包时候会更新 brokerLiveTable 缓存中的BrokerLiveInfo的lastUpdateTimestamp
    - 然后Name Server每隔10s扫描brokerLiveTable，如果连续120s没有收到心跳包，Name Server将移除该Broker的路由信息同时关闭Socket连接
- 生产者：
    - 与Name Server集群中的其中一个节点（随机）建立长连接（Keep-alive），定期从Name Server读取Topic路由信息。
    - 并向提供Topic服务的Master Broker建立长连接，且定时向Master Broker发送心跳。
- 消费者：
    - 与Name Server集群中的其中一个节点（随机）建立长连接，定期从Name Server拉取Topic路由信息。
    - 并向提供Topic服务的Master Broker、Slave Broker建立长连接，且定时向Master Broker、Slave Broker发送心跳。
    - Consumer既可以从Master Broker订阅消息，也可以从Slave Broker订阅消息，订阅规则由Broker配置决定。

### 3. RocketMQ 消息发送

RocketMQ发送普通消息有三种实现方式：

- 可靠同步发送
    - 发送者向MQ执行发送消息API时，同步等待，直到消息服务器返回发送结果
- 可靠异步发送
    - 发送者向MQ执行发送消息API时，指定消息发送成功后的回调函数，然后调用消息发送API后，立即返回
    - 消息发送者线程不阻塞，直到运行结束，消息发送成功或者失败的回调任务在一个新的线程中执行。
- 单向发送
    - 发送者向MQ执行发送消息API时，直接返回，不等待消息服务器的结果，也不注册回调函数。只管发，不在乎发送结果

普通消息发送基本流程：

- 消息长度验证
    - 确保生产者处于运行状态
    - 验证topic名称、消息体不能洧空，消息长度不能等于0且默认不能超过允许发送消息的最大长度4M(maxMessageSize=1024*1024*4)
- 查找主题路由信息
    - 如果生产者中缓存了topic的路由信息，并且路由信息中包含了消息队列，则直接返回该路由信息
    - 否则向NameServer查询该topic的路由信息。如果最终未找到，则抛出异常
    - 由于NameServer检测Broker是否有用是有延迟的
- 选择消息队列
    - 根据路由信息选择消息队列，返回的消息队列按照broker、序号排序。
    - 消息发送端采用重试机制，循环选择消息队列，发送消息，发送成功则返回，收到异常则重试
    - broker故障延迟机制: 开启该机制可以在选择队列的时候临时摘除故障队列，待其恢复之后再可选
- 消息发送
    - 根据前面得到的消息队列获取对应broker的网络地址.(如果该broker信息未缓存则自从NameServer主动更新一下，更新还没有则抛出异常，提示不存在)
    - 为消息分配全局唯一ID，如果消息体超过4K（compressMsgBodyOverHowmuch）会对消息体采用zip压缩，并设置消息被压缩的系统标记
    - 如果是事物Prepared消息，同样设置消息的特殊系统标记
    - 执行发送前的钩子函数（如果设置的话）
    - 构建消息发送请求包
    - 根据消息发送方式，同步、异步、单向方式进行网络传输
    - 执行发送后的钩子函数（如果有设置）
- 批量消息发送
    - 将同一个主题的多条消息一起打包发送到消息服务端，减少网络调用次数，提高网络传输效率
    - 多条消息批次发送的总长度不能超过DefaultMQProducer#maxMessageSize.

### 4.RockerMQ消息存储

#### 4.1 存储设计概要

RocketMQ主要存储文件包括 CommitLog 文件、ConsumeQueue文件、IndexFile文件。 RocketMQ将所有主题的消息存储在同一个文件中，确保消息发送时候顺序写文件，尽最大的能力确保消息发送的高性能和高吞吐。
消息中间件一般是基于消息主题的订阅机制，这样就给按照主题检索消息带来了极大的不便。 为了提高消息消费的效率，RocketMQ引入了ConsumeQueue消息队列文件，每个消息主题包含多个消息消费队列，每一个消息队列有一个消息文件。
IndexFile文件，主要为了加速消息的检索性能，根据消息的属性，快速从 CommitLog 文件中检索消息。

具体的文件功能如下：

- CommitLog： 消息存储文件，所有消息主题的消息都存储在CommitLog文件中
- ConsumeQueue： 消息消费队列，消息到达CommitLog文件后，将异步转发到消息消费队列，供消息消费者消费
- IndexFile： 消息索引文件，主要存储消息Key与Offset的对应关系
- 事务状态服务：存储每条消息的事务状态
- 定时消息服务：每一个延迟级别对应一个消息消费队列，存储延迟队列的消息拉取进度

RocketMQ通过开启一个线程 ReputMessageService 来准实时转发CommitLog文件更新事件，相应的任务处理器根据转发的消息及时更新ConsumeQueue、IndexFile文件

#### 4.2 消息发送存储流程如下

- 如果当前Broker停止工作或Broker为slave角色或当前Rocket不支持写入则拒绝消息写入；如果消息主题长度超过256字符，消息属性长度超过65535个字符将拒绝该消息写入
- 如果消息的延迟级别大于0，将消息的原主题名称与原消息队列ID存入消息属性中，用延迟消息主题 SCHEDULE_TOPIC、消息队列ID更新原先消息的主题与队列。
- 获取当前可写入的CommitLog文件
    - 存储目录为 ${ROCKET_HOME}/store/commitlog目录，每一个文件默认1G，
    - 一个文件写满后再创建另一个，以文件中第一个偏移量为文件名，偏移量小于20位用0补齐
- 在写入CommitLog之前，先申请putMessageLock，也就是将消息存储到CommitLog文件中是串行的
- 设置消息的存储时间，如果commitlog目录下没有文件，则用偏移量0创建文件
- 将消息追加到 MappedFile中（当前CommitLog文件，使用了```内存映射文件```的方式）
- 创建全局唯一消息ID，消息ID有16个字节
    - 4字节IP+4字节端口号+8字节消息偏移量（转为字符串）
- 获取该消息在消息队列的偏移量。CommitLog中保存了当前所有消息队列的当前待写入偏移量
- 根据消息体的长度，主题的长度、属性的长度结合消息存储格式计算消息的总长度
- 如果消息长度+END_FILE_MIN_BLANK_LENGTH 大于 CommitLog 文件的空闲空间，则会创建一个新的CommitLog文件来储存该消息
- 将消息内容存储在MappedFile对应的内存映射Buffer（并没有刷写到磁盘）
- 消息追加操作仅仅是将消息追加在内存中，需要根据是同步还是异步刷盘的方式，将内存中的数据持久化到磁盘。然后执行HA主从同步复制

### 5. RocketMQ 消息消费

#### 5.1 概述

消息消费以组的模式开展，一个消费组内可以包含多个消费者，每一个消费组可以订阅多个主题，消费组之间有集群模式与广播模式两种消费模式。

- 集群模式，主题下的同一条消息只允许被其中一个消费者消费。
- 广播模式，主题下的同一条消息将被集群内的所有消费者消费一次。

消息服务器与消费者之间的消息传送也有两种方式：推模式与拉模式。

- 拉模式：消费端主动发起拉消息请求
- 推模式：消息到达消息服务器后，推送给消费者

RocketMQ消息推模式的实现基于拉模式，在拉模式上包装一层，一个拉取任务完成后，开始下一个拉取任务。

集群模式下，消息队列负载机制遵循一个通用的思想：一个消息队列同一时间只允许被一个消费者消费，一个消费者可以消费多个消息队列。

RocketMQ支持局部顺序消息消费，也就是保证同一个消息队列上的消息顺序消费。不支持消息全局顺序消费，如果要实现某一个主题的全局顺序消息消费，可以将该主题的队列数量设置为1，牺牲高可用性

RocketMQ支持两种消息过滤模式：表达式（TAG、SQL92）与类过滤模式

#### 5.2 消息拉取

#### 5.3 消息消费

ACK：

RocketMQ自带消息消费重试机制，消息消费失败的，在无成功ACK的情况下，会将相应的消息在broker中重新创建一条，保持原来的属性，并将重试次数加1，存入到延迟消费topic中，等待一段时间进行下一次消费。
如果超过了设置的消息重试次数，则消息会进入到 DLQ主题（只读）中，以后就不再消费，如果想要额外处理，得人工干预。

消费进度管理：

- 广播模式消息消费进度存储在消费者本地
- 集群模式消费进度存储在消息服务端Broker
    - 集群模式下以主题与消费组为键保存该主题所有队列的消费进度
    - 根据同一个消费组下所有消费任务的ProcessQueue中包含的最小消息偏移量来更新消费进度，消息消费进度推进取决于ProcessQueue中消费量最小的消息消费速度
    - RocketMQ引入了消息拉取流控措施，消息处理队列ProcessQueue中最大消息偏移量与最小偏移量不能超过设置的控制该值，如果超过，将出发流控，将延迟该消息队列的消息拉取
    - 另外消息进行负载时，如果消息消费队列被分配给其他消费者，也会触发消费进度更新。

#### 5.4 定时消息机制

RocketMQ不支持任意的时间精度(出于性能考量)。只支持特定级别的延迟消息。消息延迟级别在broker端通过messageDelayLevel配置。 默认为 "1s 5s 10s 30s 1m 2m 3m 4m 5m 6m 7m 8m
9m 10m 20m 30m 1h 2h"。

定时消息的设计关键点在于，定时消息使用单独的一个主题 SCHEDULE_TOPIC_XXX 。 其他的消息属性，将被转换存储并统一存入定时消息主题等待调度。
调度程序在满足条件之后，会被定时任务提取处理，并恢复原先的属性，推送到对应的TOPIC当中去

定时消息，借助于定时任务来实现：

- 每一个延迟级别对应一个消息消费队列，并对应一个定时任务，每次启动时候，默认延迟1s先执行一次，之后第二次调度开始才使用相应的延迟时间
- 另有一个定时任务，每隔10s持久化一次延迟队列的消息消费进度。持久化频率可设置

#### 5.5 顺序消息

RocketMQ支持局部消息顺序消费，可以确保同一个消息消费队列中的消息被顺序消费，如果需要做到全局顺序消费，则可以将主题配置成一个队列。

消息队列负载：

- 集群模式下同一个消费组内的消费者共同承担其订阅主题下消息队列的消费，同一消费队列在同一时刻只会被消费组内一个消费者消费，一个消费者同一时刻可以分配多个消费队列
- 重新负载后，分配到新的消息队列时，首先要尝试向Broker发起锁定该消息队列的请求，如果返回加锁成功则创建该消费队列的拉取任务，否则跳过，等下一次重新负载时在尝试加锁
- 消息队列负载由 RebalanceService 线程默认每隔20s进行一次消息队列负载

### 6. 主从同步（HA）机制

#### 主从复制原理

RocketMQ引入了Broker主备机制，即消息到达主服务器后需要将消息同步到消息从服务器，如果主服务器Broker宕机，消息消费者可以从从服务器拉取消息

实现原理：

- 主服务器启动，并在特定端口上监听从服务器的连接
- 从服务器主动连接主服务器，主服务器接收客户端的连接，并建立相关TCP连接
- 从服务器保存消息并继续发送新的消息同步请求

### 6. 事务消息

RocketMQ事务消息的实现远离基于两阶段提交和定时事务状态回查来决定消息最终是提交还是回滚

具体的分布式事务相关原理可以转移到 [分布式事务](/Structure/Distributed/Trainsaction.md)




