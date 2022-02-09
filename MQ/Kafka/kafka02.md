Kafka（二）
==

### 5. Kafka 构建数据管道

#### 构建数据管道时需要考虑的问题

- 及时性
- 可靠性
- 高吞吐量和动态吞吐量
- 数据格式
- 转换
    - ETL 提取-转换-加载（Extract-Transform-Load）
    - ELT 提取-加载-转换（高保真数据管道、数据湖架构）
- 安全性
- 故障处理能力
- 耦合性和灵活性

#### Kafka Connect

预留

### 6. 跨集群数据镜像

#### 6.1 多集群架构

##### 跨数据中心通信的限制

- 高延时
- 有限的带宽
- 一些架构原则
    - 每个数据中心至少需要一个集群
    - 每两个数据中心之间的数据复制要做到每个事件仅复制一次（除非出现错误需要重试）
    - 如果有可能，尽量从远程数据中心读取数据，而不是向远程数据中心写入数据

##### 架构模式

- Hub和Spoke（星形辐射架构）
    - 一个中央数据中心,多个本地数据中心,本地数据中心向中央数据中心同步数据
    - 优点: 数据只会在本地数据中心生成，而且每个数据中心的数据只会被镜像到中央数据中心一次
    - 缺点：一个数据中心的数据不能被另一个数据中心访问
- 双活架构
    - 有两个或者多个数据中心需要共享数据并且每个数据中心都可以生产和读取数据时候，可以使用双活架构
    - 优点：可以就近为用户提供服务，弹性和冗余
    - 缺点：循环复制不好处理、数据冲突难以解决
    - 方案：topic带命名空间前缀，单向镜像；通配符方式订阅主题
    - 挑战：每个数据中心都要进行镜像，而且是双向的。所一镜像进程数量是乘积
- 主备架构
    - 备用集群只做数据冗余，可以在主集群异常的时候选择使用
- 延展集群
    - 延展集群不是多个集群而是单个集群，不需要对延展集群进行镜像，多个数据中心，位于一个集群中
    - Kafka内置功能，需要通过配置来实现。
    - 优势：同步复制

##### 失效备援的的内容

- 数据丢失和不一致性需要处理
- 失效备援之后的起始偏移量
    - 偏移量自动重置（消费者的配置选项）
    - 复制偏移量主题（不能保证完全一致，还是有丢失或重复的可能）
    - 基于时间的失效备援
    - 偏移量外部映射
- 失效备援之后
    - 防止数据不一致，建议清空之前的集群数据再重新把数据镜像回来
- 关于集群发现
    - 建议不要硬编码集群主机地址

##### Kafka 的 MirrorMaker

用于在数据中心之间镜像数据 （待完善）

##### 其他跨集群的镜像方案

- 优步的 uReplicator
- Confluent 的 Replicator

### 7. 管理Kafka

#### 7.1 主题操作

```shell
# 创建主题
kafka-topics.sh --zookeeper <zk-connect> --create --topic <string> \
-- replication-factor <integer> --partitions <integer>  [--if-not-exists]

# 增加分区（只可以增加不可以减少,如果一定要减少，建议删除整个topic然后再创建）
# 另外给予键的主题，如果增加分区，会导导致键到分区的映射发生变化，所以建议一开始就分好分区，不再变化
kafka-topics.sh --zookeeper <zk-connect> --alter --topic <string> --partitions <integer>  [--if-exists]

# 删除主题
kafka-topics.sh --zookeeper <zk-connect> --delete --topic <string>

# 列出集群里的所有主题
kafka-topics.sh --zookeeper <zk-connect> --list

# 列出主题详细信息
# # 可以支持额外参数
# # --topics-with-overrides  可以找出所有包含覆盖配置的主题
# # --under-replicated-partitions  列出所有包含不同步副本的分区
# # --unavailable-partitions  列出所有没有首领的分区（这些分区已经处于离线状态）
kafka-topics.sh --zookeeper <zk-connect> --describe [--topic <string>]

```

#### 7.2 消费者群组

```shell

# 列出并描述消费者群组（旧版）
kafka-consumer-groups.sh --zookeeper <zk-connect> --list
# 列出并描述消费者群组（新版）
kafka-consumer-groups.sh --bootstrap-server <broker-connection> --list

# 列出旧版本消费者群组的详细信息
kafka-consumer-groups.sh --zookeeper <zk-connect> --describe --group <string>

# 删除群组，只有旧版本客户端才支持删除群组【注意消费者要先关闭，否则坑会出现不可预测行为】
kafka-consumer-groups.sh --zookeeper <zk-connect> --delete --group <string>
# 从消费者群组删除单个主题的偏移量
kafka-consumer-groups.sh --zookeeper <zk-connect> --delete --group <string> --topic <string>

```

#### 7.2 偏移量管理

```shell

# 导出zookeeper中保存的偏移量（kafka中保存的无法导出）
kafka-run-class.sh kafka.tools.ExportZKOffsets --zkconnect <zk-connect> --group <string> --output-file offsets
# 将文件保存的偏移量导入到群组
kafka-run-class.sh kafka.tools.ImportZKOffsets --zkconnect <zk-connect> --input-file offsets

```

#### 7.3 动态配置变更

```shell

# 覆盖主题的默认配置
kafka-configs.sh --zkconnect <zk-connect> --alter --entity-type topics -- entity-name <topic name> --add-config <key>=<value>[,<key>=<value>...]

# 覆盖客户端配置
kafka-configs.sh --zkconnect <zk-connect> --alter --entity-type clients -- entity-name <client ID> --add-config <key>=<value>[,<key>=<value>...]

# 列出被覆盖的配置（只能显示主题的覆盖配置，使用这个工具来获得主题和客户端的配置信息，那必须为它提供集群的默认配置信息）
kafka-configs.sh --zkconnect <zk-connect> --describe --entity-type topics -- entity-name <topic name>

# 移除被覆盖的配置
kafka-configs.sh --zkconnect <zk-connect> --alter --entity-type topics -- entity-name <topic name> --delete-config <key>
```

#### 7.4 分区管理

##### 首选首领的选举

```shell
# 自动首领再均衡功能不建议使用，会带来严重的性能问题

# 首领选举 
kafka-perferred-replica-election.sh --zkconnect <zk-connect> 

# 选举时候集群的元数据必须被写到Zookeeper的节点熵，如果元数据超过了节点允许的大小（默认1MB），那么就会选举失败
# 这时候需要把分区清淡的信息写到一个JSON文件里面，并将请求分为多个步骤 json文件格式此处不记录

# 通过 文件制定分区清单来启动副本选举
kafka-perferred-replica-election.sh --zkconnect <zk-connect> --path-to-json-file <file>

```

##### 修改分区副本

需要修改分区副本的场景

- 主题分区在整个集群里的不均衡分布造成了集群负载的不均衡
- broker离线造成分区不同步
- 新加入的broker需要从集群里获得负载

具体流程待完善（流程比较复杂）

使用 kafka-reassign-partition.sh工具来修改分区。

- 步骤一： 根据broker清单和主题清单生成一组迁移步骤；
- 步骤二：执行这些迁移步骤
- 步骤三：【可选】使用生成的迁移步骤验证分区冲分配到进度和完成情况

##### 修改复制系数

分区重分配工具可以修改复制系数。同样要借助文件来处理

##### 转储日志片段

```shell
# 查看日志片段文件清单下 每个消息的概要信息和数据内容
## 支持额外参数
## --print-data-log 参数会额外显示消息的数据内容
## --index-sanity-check 会检查无用的索引
## --verify-index-only 会检查索引的匹配度
kafka-run-class.sh kafka.tools.DumpLogSegments --files <file-name> 

## 索引匹配度检查示例
kafka-run-class.sh kafka.tools.DumpLogSegments --files <index-file,log-file> --index-sanity-check
```

##### 副本验证

可以用副本验证工具来验证集群分区副本的一致性

副本验证工具会对集群造成影响，因为它需要读取所有的消息。另外，它的读取过程是并行进行的，所以使用的时候要小心。

```shell
#  对 broker1,broker2 上以  my- 开头的主题副本进行验证
## 主题白名单会正则匹配
kafka-replica-verification.sh --broker-list <broker1,broker2,...> --topic-white-list "my-.*"

```

#### 7.5 消费和生产

##### 控制台消费者

kafka-console-consumer.sh 工具提供了一种从多个主题上读取消息的方式

另外：要使用与kafka相同版本的消费者客户端，旧版本客户端可能有不恰当交互影响到集群

```shell
# 三种方式启动
kafka-console-consumer.sh --zookeeper <zk-connect> --topic <string>
kafka-console-consumer.sh --zookeeper <zk-connect> --white-list <string(regex)> --consumer.config <config-file>
kafka-console-consumer.sh --zookeeper <zk-connect> --black-list <string(regex)> --consumer-property <key>=<value>,[<key>=<value>,...]

# 新版本不再使用 --zookeeper参数，而是 --bootstrap-server <brokers>

# 另外的参数
# --formatter CLASSNAME 指定消息格式化器的类名
#     kafka.tools.LoggingMessageFormatter
#     kafka.tools.ChecksumMessageFormatter
#     kafka.tools.NoOpMessageFormatter
#     kafka.tools.DefaultMessageFormatter有一些非常有用的配置选项，可以通过 --property 命令行参数传递给它
#         print.timestamp    如果设置为true，就会打印每个消息的时间戳   
#         print.key          如果被设置为true，除了打印消息的指之外，还会打印消息的键
#         key.separator      指定打印消息的键和消息的值使用的分隔符 
#         line.timestamp     指定消息之间的分隔符  
#         key.deserializer   指定打印消息的键所使用的反序列化器类名    
#         value.deserializer 指定打印消息的值所使用的反序列化器类名          
# --from-beginning 指定从最旧的偏移量开始读取数据
# --max-messages NUM 指定在退出之前最多读取NUM个消息
# --formatter CLASSNAME 指定消息格式化器的类名
# --partition NUM 指定只读取ID为NUM的分区（需要新版本消费者）
```

##### 读取偏移量主题

```shell
## 此处有点问题待完善
# 老版本 0.11 之前
kafka-console-consumer.sh --zookeeper <zk-connect> --topic __consumer_offsets --formatter 'kafka.coordinator.GroupMetadataManager$OffsetsMessageFormatter' --max-messages 1
# 新版本....0.11 之后（ 待确定 ）
kafka-console-consumer.sh --bootstrap-server <brokers> --topic __consumer_offsets --formatter "kafka.coordinator.group.GroupMetadataManager\$OffsetsMessageFormatter" --max-messages 1

```

##### 控制台生产者

```shell
# 生产
kafka-console-producer.sh --topic <string> --broker-list <broker1,broker2,...>
kafka-console-producer.sh --topic <string> --broker-list <broker1,broker2,...> --produer.config <config-file>
kafka-console-producer.sh --topic <string> --broker-list <broker1,broker2,...> --produer-property <key>=<value>,[<key>=<value>,...]

# 另外的参数
# --key-serializer CLASSNAME 指定消息键的编码器类名 默认是 kafka.serializer.DefaultEncoder
# --value-serializer CLASSNAME 指定消息值的编码器类名 默认是 kafka.serializer.DefaultEncoder
# --compression-codec STRING  指定生成消息所使用的压缩类型，可以是 none、gzip、snappy或者 lz4，默认是gizp
# --sync 指定以同步方式生成消息. 也就是说,在发送下一个消息之前会等待当前消息得到确认

# 文本行读取器的配置参数
# kafka.tools.LineMessageReader 类负责读取标准输入，并创建消息记录。塔也有一些非常有用的配置参数。
# 可以通过 --property 命令行参数吧浙西饿配置参数传给控制台生产者 
#     ignore.err    如果被设置为false，那么在parse.key 被设置为true或者标准输入里没有包含键的分隔符时就会抛出异常，默认为true
#     parse.key     如果被设置为false，那么生成消息的键总是null，默认为 true
#     key.separator 指定消息键和消息值之间的分隔符，默认是Tab字符

```

##### 客户端ACL

使用 kafka-acls.sh 命令行工具，参考文档

##### 不安全的操作

- 移动集群控制器
    - 在zookeeper上删除控制器注册的临时节点，会释放当前控制器，集群会进行新的控制器选举
- 取消分区重分配
    - 从zookeeper熵删除 /admin/reassign_partitions节点
    - 重新选举控制器
- 移除待删除的主题
    - 如果进群没有哦启动主题删除的功能，那么命令行工具发起的删除请求会被挂起，不过这种挂起请求是可以被移除的
    - 主题的删除是通过在 /admin/delete_topic节点下创建一个以待删除主题名字命名的子节点来实现的，删除主题名字的节点可以移除挂起的请求
- 手动删除主题
    - 先关闭集群里的所有broker
    - 删除zookeeper路径/brokers/topics/TOPICNAME，注意要先删除节点下面的子节点
    - 删除每个broker的分区目录，这些目录名字可能是 TOPICNAME-NUM，其中NUM是指分区的ID
    - 重启所有broker

### 8. 监控kafka

### 9. 流量式处理

#### 简介

数据流

- 数据流（事件流、流数据）是无边界数据集的抽象表示, 无边界意味着无限和持续增长
- 事件流是有序的
- 不可变的数据记录
- 事件流是可重播的

流式处理是指实时地处理一个或多个事件流

三种处理范式

- 请求与响应
- 批处理
- 流式处理

#### 流式处理的一些概念

- 时间（注意统一时区）
    - 事件时间
    - 日志追加时间
    - 处理时间
- 状态
    - 本地状态或内部状态
    - 外部状态
- 流和表的二元性
    - 表变动的流记录
    - 流到表的转换
- 时间窗口
    - 窗口大小
    - 窗口移动频率
        - 滚动窗口
        - 滑动窗口
    - 窗口的可更新时间多长

#### 流式处理的设计模式

- 单个事件处理（map）
    - map 或 filter 模式
    - map的概念来自 Map-Reduce 模式（map阶段转换事件，reduce阶段集合转换过的事件）
- 使用本地状态
    - 问题：内存使用、持久化、再均衡
- 多阶段处理和重分区
- 使用外部查找----流和表的连接
    - 外部查找的延迟需要解决（一般在5-15ms）之间
    - 流处理系统每秒钟可以处理10-40万个事件
    - 可以考虑缓存数据库信息到流式处理应用程序里（ CDC 变更数据捕捉（Change Data Capture）将数据库事件流通知到流处理系统）
- 流与流的连接
    - 基于时间窗口的连接（具有相同键和在相同时间窗口内的事件匹配）
- 乱序的事件
    - 识别乱序的事件
    - 规定一个，时间段用于重排乱序的事件
    - 具有一定时间段内重排乱序事件的能力
    - 具备更新结果的能力
- 重新处理
    - 2个变种：
        - 使用新版本应用处理同一个事件流，生成新结果，比较两个版本的结果，在适当的时间将客户端切换到新的结果流上
        - 现有流失处理有缺陷，修复缺陷后，重新处理事件流并重新计算结果
    - 建议使用第一个方案
  

