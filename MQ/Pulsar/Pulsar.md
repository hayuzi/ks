Pulsar
==

## 简介

### 基本架构

Pulsar主要由三个组件组成：

- Broker
    - Broker 是无状态服务，客户端需要连接到 broker 进行核心消息传递
- Apache BookKeeper
    - BookKeeper 节点（bookie）存储消息与游标
    - BookKeeper 使用 RocksDB 作为内嵌数据库，用于存储内部索引，但 RocksDB 的管理不独立于 BookKeeper
    - 有状态服务
- Apache ZooKeeper
    - ZooKeeper 只用于为 broker 和 bookie 存储元数据
  

Pulsar将计算与存储分离。Broker进行计算，而在BookKeeper层进行数据存储
