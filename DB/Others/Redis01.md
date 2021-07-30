Redis数据类型以及其底层结构
==


## 1. 基本数据类型

Redis支持5种对象类型，分别是字符串(string)、列表(list)、哈希(hash)、集合(set)、有序集合(zset)，redisObject使用type字段记录自身属于哪种类型。

而每种对象类型至少使用了两种底层数据结构来实现，redisObject使用编码字段（encoding字段）记录了自己使用的是哪种底层数据结构实现。
而*ptr指针则会直接指向这个对应的底层数据结构。

每个对象会用到的编码以及对应的数据结构详见下表，即共8种底层数据结构：

```
对象                  编码        数据结构
string 字符串对象        int       可以存储long类型的整数
                        embstr    embstr编码的简单动态字符串
                        raw       简单动态字符串（SDS）

list 列表对象           ziplist     压缩列表
                    linkedlist    双端链表

hansh 哈希对象          ziplist     压缩列表
                    hashtable       字典

set 结合对象            intset      整数集合
                    hashtable       字典

zset 有序集合对象        ziplist      压缩列表
                    skiplist       字典加跳表                    

```

> Redis中的键，都是用字符串对象来存储的，即对于Redis数据库中的键值对来说，键总是一个字符串对象，而值可以是字符串对象、列表对象、哈希对象、集合对象或者有序集合对象中的其中一种


### 1.1 string类型
类型介绍：
- String类型是二进制安全的。redis的string可以包含任何数据，比如jpg图片或者序列化的对象。
- string类型是redis的最近本的数据类型，一个键最大能存储512MB

应用场景：
- 信息缓存、计数器、分布式锁等

底层实现：
- 对于整型  使用int实现
- embstr编码的简单动态字符串
- raw编码的简单动态字符串


### 1.2 list类型
类型介绍：
- 列表是简单的字符串列表，按照插入顺序排序。 可以添加一个元素到列表的头部（左边）或者尾部（右边）

应用场景：
- 可以作为队列来使用，业务中也是使用的该功能

底层实现：
- 双端链表（Linkedlist）
- 压缩表（Ziplist）

### 1.3 hash类型
类型介绍：
- Redis Hash 是一个键值对集合。支持一个string类型的field和value的映射表
- 适合存储对象

应用场景：
- 用户购物车、经常变动的对象结构

底层实现：
- 哈希表（Hashtable）
- 压缩表（Ziplist）


### 1.4 set类型
类型介绍：
- string类型的无序集合，数据不重复

应用场景：
- 收藏夹等

底层实现：
- 哈希表（Hashtable）
- 整型集合（intset）


### 1.5 zset类型（sorted set）
类型介绍：
- string类型元素的集合，且不允许重复的成员，且每个元素都会关联一个 double类型的分数

应用场景：
- 实时排行榜

底层实现：
- 压缩表（Ziplist）
- 跳表（skiplist）

### 1.6 Redis Stream
类型介绍：
- Redis 5.0 版本新增加的数据结构，用于消息队列功能
- Redis 发布订阅 (pub/sub) 来实现消息队列的功能，但它有个缺点就是消息无法持久化，如果出现网络断开、Redis 宕机等，消息就会被丢弃
- 而 Redis Stream 提供了消息的持久化和主备复制功能，可以让任何客户端访问任何时刻的数据

应用场景：

底层实现：