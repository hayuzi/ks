Kafka基于docker 
==

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


