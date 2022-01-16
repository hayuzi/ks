Kafka基本应用
==

### 1.基本概念讲解

#### 1.1 基本的概念

- 消息和批次
    - 数据可以单条发送，也可以分批次写入Kafka
- 主题和分区的概念
    - Kafka的消息通过主题来进行分类.
    - 主题可以分为若干个分区，一个分区就是一个提交日志，消息以追加的方式写入分区，然后以先入先出的顺序读取
    - 无法在整个主题范围内保证消息的顺序，但是可以保证消息在单个分区内的顺序
    - Kafka通过分区来实现数据冗余和伸缩性
- 生产者
    - 生产者在默认情况下把消息均衡地分布到主题的所有分区上，并不关心特定消息会被写到哪个分区
    - 但是在某些情况下，生产者会把消息直接写入到指定的分区
    - 可以通过消息键和分区器来实现，分区器为键生成一个散列值，并旧爱更年期映射到指定的分区上（保证同一个键的消息写到同一个分区上）
    - 生产者也可以使用自定义的分区器
- 消费者与偏移量
    - 消费者通过检查消息的偏移量来区分已经读过的消息
    - 偏移量是一个不断递增的整数值，在创建消息时候，Kafka会把他添加到消息里，在给定分区里，每个消息的偏移量都是唯一的
    - 消费者把每个分区最后读取的消息偏移量保存在Zookeeper或Kafka上，消费者的关闭重启，他的读取状态不会丢失
- 消费者群组
    - 一个或者多个消费者共同读取一个主题
    - 群组保证每个分区只能被一个消费者使用
    - 消费者和分区之间的映射通常称为消费者对分区的所有权关系
    - 如果一个消费者失效，群组里面的其他消费者可以接管失效消费者的工作
- broker
    - 一个独立的Kafka服务器称为一个broker
    - broker为消息设置偏移量并提交消息到磁盘保存
    - broker洧消费者提供服务，对读取分区的请求作出响应，返回已经提交到磁盘上的消息
    - 根据特定的硬件和其性能特征，单个broker可以轻松处理数千个分区以及每秒百万级别的消息量
- 集群
    - broker是集群的组成部分。每个集群都有一个broker同时充当了集群控制器的角色（自动从集群活跃成员中选取）
    - 控制器负责管理，包括将分区分配给broker和监控broker
    - 在集群中，一个分区从属于一个broker，该broker被称为分区的首领
    - 一个分区可以分配给多个broker，这个时候会发生分区复制（一个broker失效，其他broker可以接管领导权，但相关消费者和生产者都要重新连接到新首领）
- 保留消息
    - Kafka默认的消息保留策略是这样的：要么保留一段时间（比如7天），要么保留消息达到一定大小的字节数（比如1GB）
    - 当消息数量达到这些上限时，旧的消息就会过期并删除
    - 所以在任何时刻，可用消息的总量都不会超过配置参数所指定的大小
    - 另外：主题可以配置自己的保留策略，可以将消息保留到不再使用它们为止
- 多集群
    - 基于数据类型分离、安全需求隔离、多数据中心（灾难恢复）等原因，最好使用多个集群
    - 多数据中心需要在他们之间复制消息
    - Kafka的消息复制机制只能在单个集群进行
    - Kafka提供了一个叫做MirrorMaker的工具，可以用它来实现集群之间的消息复制
    - MirrorMaker的核心组件包含了一个消费者和一个生产者，消费者从一个集群读取消息，生产者把消息发送到另外一个集群上

#### 1.2 Kafka的优势

- 多个生产者
- 多个消费者
    - kafka支持多个消费者从一个单独的消息流上读取数据，而且消费者之间互不影响。
    - 其他队列队列系统：消息一旦被一个客户端读取，其他客户端就无法再读取它
- 基于磁盘的数据存储
- 伸缩性
- 高性能
    - 横向扩展后，在处理大量数据的同时，还能保证亚秒级别的消息延迟
- 数据生态系统

### 2. 配置与调优

#### 2.1 配置

- broker配置
    - broker.id 和集群有关的配置
    - port 监听端口 默认9092
    - zookeeper.connect 保存元数据的zk地址（格式 hostname:port/path;hostname2:port2/path2）
    - log.dirs （格式 dir1;dir2;dir3）
    - num.recovery.threads.per.data.dir (多个线程有助于服务器包含大量分区时候的恢复加载)
- topic配置
    - num.partitions 分区数量
        - 分区数量依据 吞吐消息量来确定
    - log.retention.ms 数据保留的时间（ms、bytes只要一个条件满足即触发数据删除）
    - log.retention.bytes 数据保留的大小上限
    - log.segment.bytes 日志片段的大小上限（ms、bytes只要一个条件满足即触发片段关闭）
    - log.segment.ms 日志片段持续的时间上限，达到上限会被关闭
    - message.max.bytes
        - broker通过该参数限制单个消息的大小
        - 默认值 1000000（1M，压缩后的消息大小）
        - 消费者fetch.message.max.bytes或者集群broker中replica.fetch.max.bytes需要大于该参数，负责会导致消费消息阻塞

#### 2.2 硬件与性能

- 磁盘吞吐量
- 磁盘容量
- 内存
- 网络
- CPU

#### 2.3 集群

####          

#### 2.2

#### 2.3 操作系统调优

### 简单的安装使用

使用docker-compose安装

先定义 docker-compose.yml

```yaml
version: '3'
services:
  zookeeper:
    image: wurstmeister/zookeeper
    ports:
      - "2181:2181"
  kafka:
    image: wurstmeister/kafka
    depends_on: [ zookeeper ]
    ports:
      - "9092:9092"
    environment:
      KAFKA_ADVERTISED_HOST_NAME: 172.18.0.3
      KAFKA_CREATE_TOPICS: "test:1:1"
      KAFKA_ZOOKEEPER_CONNECT: zookeeper:2181
    volumes:
      - /data/product/zj_bigdata/data/kafka/docker.sock:/var/run/docker.sock
```

在docker-compose.yml文件所在的目录进行服务运行

```shell
docker-compose up -d
```

docker启动之后进行测试，先进入容器内部

```shell
docker exec -it kafka_kafka_1 bash
```

在容器内执行测试

```shell
# 创建一个topic
$KAFKA_HOME/bin/kafka-topics.sh --create --topic test --partitions 4 --zookeeper kafka_zookeeper_1:2181 --replication-factor 1

# 查看topic信息
$KAFKA_HOME/bin/kafka-topics.sh --zookeeper kafka_zookeeper_1:2181 --describe --topic test

# 生产者
$KAFKA_HOME/bin/kafka-console-producer.sh --topic=test --broker-list kafka_kafka_1:9092

## 另开一个进程进入容器执行消费测试
$KAFKA_HOME/bin/kafka-console-consumer.sh --bootstrap-server kafka_kafka_1:9092 --from-beginning --topic test

```
