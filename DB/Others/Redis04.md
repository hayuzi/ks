Redis持久化与数据过期策略
==

> 参考:
> - Redis设计与实现 http://redisbook.com/
> - https://my.oschina.net/lscherish/blog/3145142


## 1. 基础设计

Redis的特点：
- 纯粹内存操作，可以持久化
- 支持key-value等多种数据结构的存储系统。
- 可以用于缓存，时间发布或订阅，高速队列称场景。支持网络，提供字符串、hash、列表、队列、集合等

单线程模型（文件事件处理器）：
- 基于非阻塞的 IO 多路复用机制
- 套接字 / IO多路复用程序 / 文件事件分派器 / 事件处理器


## 2. RDB/AOF持久化

### 2.1 RDB持久化

#### 基础功能介绍
- RDB(Redis Database)持久化是把当前内存数据生成快照保存到硬盘的过程，触发RDB持久化过程分为手动触发和自动触发
- RDB文件结构，全量周期性持久化

#### 手动触发
SAVE 命令:
- 手动触发对应save命令，会阻塞当前Redis服务器，直到RDB过程完成为止。
- 在主进程阻塞期间，服务器不能处理客户端的任何请求

BGSAVE 命令:
- 系统 fork出一个子进程，子进程负责调用 rdbsave 并在完成后向主进程发送信号，通知保存已经完成
- 阻塞只发生在fork阶段，一般时间很短。由于会fork一个进程，因此会更消耗内存（所以redis服务器需要预留出业务bgsave需要的内存）
- 但是fork进程时候并不一定是使用双倍的内存，Linux进程复制支持一个内存数据写时复制的功能，可以在数据变动的时候，才实际消耗更多内存存储变动的数据

#### 自动触发
redis.conf文件配置:
- 配置值 save <seconds> <changes>  表示xx秒内数据变动 xx次时即自动触发bgsave
- 如果想关闭自动触发，可以在 save命令后增加一个空字符串 即： save ""

其他触发场景:
- 如果从节点执行全量复制操作，主节点自动执行bgsave生成RDB文件并发送给从节点
- 默认情况下执行shutdown命令时，如果没有开启AOF持久化功能则 自动执行bgsave

#### BGSAVE 的工作机制
1. 执行bgsave命令，redis父进程判断当前是否存在正在执行的子进程， 如RDB/AOF，如果存在，bgsave命令返回
2. 父进程执行fork操作创建子进程，fork操作过程中父进程会阻塞，通过info stats 命令查看 latest_fork_usec 选项，可以获取最近一个 fork操作的耗时，单位为 微秒
3. 父进程fork完成后，bgsave命令返回 “Background saving started” 信息并不再阻塞父进程，可以继续响应其他命令
4. 子进程创建RDB文件，根据父进程内存生成临时快照文件，完成后对原有文件进行原子替换（rename）.（执行lastsave命令可以获取最后一次生成RDB的时间，对应info统计的rdb_last_save_time选项）
5. 进程发送信号给父进程表示完成，父进程更新统计信息，具体见 info Persistence下的rdb_*相关选项

#### save m n 的实现原理

基础实现流程
- 通过 serverCron函数、dirty计数器、和lastsave时间戳来实现的。
- save m n的原理如下：每隔100ms，执行serverCron函数；在serverCron函数中，遍历save m n配置的保存条件，只要有一个条件满足，就进行bgsave

serverCron
- serverCron是Redis服务器的周期性操作函数，默认每隔100ms执行一次
- （可在redis.conf 文件中配置 默认：hz 10 这个配置表示1s内执行10次，也就是每100ms触发一次定时任务）
- 该函数对服务器的状态进行维护，其中一项工作就是检查 save m n 配置的条件是否满足，如果满足就执行 bgsave。

dirty计数器
- dirty计数器是Redis服务器维持的一个状态，记录了上一次执行bgsave/save命令后，服务器状态进行了多少次修改(包括增删改)；
- 而当save/bgsave执行完成后，会将dirty重新置为0

lastsave时间戳
- lastsave时间戳也是Redis服务器维持的一个状态，记录的是上一次成功执行save/bgsave的时间


### 2.2 AOF持久化

#### 基础介绍

- 开启AOF功能需要配置 appendonly yes, 默认不开启
- AOF文件名 通过appendfilename配置设置，默认文件名是appendonly.aof。保存路径同 RDB持久化方式一致，通过dir配置指定。
- AOF的工作流程操作：命令写入 （append）、文件同步（sync）、文件重写（rewrite）、重启加载 （load）。

#### 执行流程
1. 所有命令会追加到aof_buf（缓冲区）中， AOF缓冲区根据对应的策略向硬盘做同步操作
2. 随着AOF文件越来越大，需要定期对AOF文件进行重写，达到压缩的目的
3. 当Redis服务器重启时候，可以加载AOF文件进行数据恢复

#### aof_buf（缓冲区）
缓冲区作用：
- redis使用单线程响应命令，如果每次写AOF文件命令都直接追加到硬盘，那么性能完全取决于当前硬盘负载
- 先写入缓冲区 aof_buf 中，还有另一个好处，redis可以提供多种缓冲区同步硬盘的策略，在性能和安全性方面做出平衡

#### AOF重写（rewrite）机制
oaf重写的控制
- 目的是：减小AOF文件占用空间；更小的AOF文件可以更快地被Redis加载恢复
- 可以手动触发（bgrewriteaof）
- 自动触发根据 auto-aof-rewrite-min-size和auto-aof-rewrite-percentage 参数确定自动触发时机
- - auto-aof-rewrite-min-size：表示运行AOF重写时文件最小体积，默认 为64MB
- - auto-aof-rewrite-percentage：代表当前AOF文件空间 （aof_current_size）和上一次重写后AOF文件空间（aof_base_size）的比值

为何重写后文件变小
- 旧有的AOF文件含有无效命令。如：del key1， hdel key2等。重写只保留最终数据的写入命令
- 多条命令可以合并，如lpush list a，lpush list b，lpush list c可以直接转化为lpush list a b c

#### AOF文件的载入与数据还原

数据恢复流程
- AOF持久化开启且存在AOF文件时，优先加载AOF文件
- AOF关闭或者AOF文件不存在时，加载RDB文件
- 加载AOF/RDB文件成功后，Redis启动成功
- AOF/RDB文件存在错误时，Redis启动失败并打印错误信息

#### 事件循环
事件循环中时间事件控制是否执行写入文件


### 2.3 两种持久化方式优缺点比较

#### RDB的优缺点
##### 优点
- RDB 是紧凑的二进制文件，比较适合备份，全量复制等场景
- RDB 恢复数据远快于 AOF
- 生成RDB文件的时候，redis主进程会fork()一个子进程来处理所有保存工作，主进程不需要进行任何磁盘IO操作

##### 缺点
- RDB方式数据没办法做到实时持久化/秒级持久化
- 新老版本无法兼容 RDB 格式
- 因为bgsave每次运行都要执行fork操作创建子进程，属于重量级操作，如果不采用压缩算法(内存中的数据被克隆了一份，大致2倍的膨胀性需要考虑)，频繁执行成本过高(影响性能)
- - 这一条目前有 操作系统写时复制功能 可以减少内存消耗

#### AOF优点

##### 优点
- 你可以使用不同的 fsync 策略：无 fsync、每秒 fsync 、每次写的时候 fsync
- 使用默认的每秒 fsync 策略, Redis 的性能依然很好( fsync 是由后台线程进行处理的,主线程会尽力处理客户端请求),一旦出现故障，你最多丢失1秒的数据
- AOF文件是一个只进行追加的日志文件,所以不需要写入seek,即使由于某些原因(磁盘空间已满，写的过程中宕机等等)未执行完整的写入命令,你也也可使用redis-check-aof工具修复这些问题。
- Redis 可以在 AOF 文件体积变得过大时，自动地在后台对 AOF 进行重写： 重写后的新 AOF 文件包含了恢复当前数据集所需的最小命令集合
- 整个重写操作是绝对安全的，因为 Redis 在创建新 AOF 文件的过程中，会继续将命令追加到现有的 AOF 文件里面，即使重写过程中发生停机，现有的 AOF 文件也不会丢失
- 而一旦新 AOF 文件创建完毕，Redis 就会从旧 AOF 文件切换到新 AOF 文件，并开始对新 AOF 文件进行追加操作
- AOF 文件有序地保存了对数据库执行的所有写入操作， 这些写入操作以 Redis 协议的格式保存， 因此 AOF 文件的内容非常容易被人读懂， 对文件进行分析（parse）也很轻松

##### 缺点
- 对于相同的数据集来说，AOF 文件的体积通常要大于 RDB 文件的体积
- AOF 开启后，会对写的 QPS 有所影响，相对于 RDB 来说 写 QPS 要下降；
- 数据恢复（load）时AOF比RDB慢，通常RDB 可以提供更有保证的最大延迟时间




## 3. 数据过期策略

### 3.1 定期删除 与 惰性删除
- **默认100ms随机抽取一部分设置过期时间的key进行处理**
- 获取某个key的时候会检查过期并处理

### 3.2 内存淘汰机制

具体策略（共8种）

####  noeviction（ no eviction 不驱逐 ）
- 默认策略，对于写请求直接返回错误，不进行淘汰

####  allkeys-lru
- lru(less recently used), 最近最少使用。从所有的key中使用近似LRU算法进行淘汰

#### volatile-lru
- 从设置了过期时间的key中使用近似LRU算法进行淘汰

#### allkeys-random
- 从所有的key中随机淘汰

#### volatile-random
- 从设置了过期时间的key中随机淘汰

#### volatile-ttl
- ttl(time to live)，在设置了过期时间的key中根据key的过期时间进行淘汰，越早过期的越优先被淘汰

#### allkeys-lfu
- lfu(Least Frequently Used)，最少使用频率。从所有的key中使用近似LFU算法进行淘汰。从Redis4.0开始支持

#### volatile-lfu
- 从设置了过期时间的key中使用近似LFU算法进行淘汰。从Redis4.0开始支持
- 注意：当使用volatile-lru、volatile-random、volatile-ttl 这三种策略时，如果没有设置过期的key可以被淘汰，则和noeviction一样返回错误


#### 配置文件配置
maxmemory
- maxmemory 1024mb   //设置Redis最大占用内存大小为1024M
- 该参数默认配置为0，在64位操作系统下redis最大内存位操作系统剩余内存，在32位操作系统下redis最大内存位3GB
- 可以通过动态命令配置： config set maxmemory 200mb // 设置Redis最大占用内存大小为200M


#### LRU算法介绍
核心思想: 如果一个数据在最近一段时间没有被用到，那么将来被使用到的可能性也很小，所以就可以被淘汰掉

#### LRU在Redis中的实现
- Redis使用的是近似LRU算法，它跟常规的LRU算法还不太一样。
- 近似LRU算法通过随机采样法淘汰数据，每次随机出5个（默认）key，从里面淘汰掉最近最少使用的key
- 可以通过 maxmemory-samples 参数修改采样数量， 如：maxmemory-samples 10
- Redis为了实现近似LRU算法，给每个key额外增加了一个24bit的字段，用来存储该key最后一次被访问的时间

#### Redis3.0对近似LRU的优化
- 新算法会维护一个候选池（大小为16），池中的数据根据访问时间进行排序，
- 第一次随机选取的key都会放入池中，随后每次随机选取的key只有在访问时间小于池中最小的时间才会放入池中，直到候选池被放满。
- 当放满后，如果有新的key需要放入，则将池中最后访问时间最大（最近被访问）的移除
- 当需要淘汰的时候，则直接从池中选取最近访问时间最小（最久没被访问）的key淘汰掉就行


#### LFU算法简介

核心思想： LFU(Least Frequently Used)，是Redis4.0新加的一种淘汰策略，它的核心思想是根据key的最近被访问的频率进行淘汰，很少被访问的优先被淘汰，被访问的多的则被留下来

##### 优缺点
- LFU算法能更好的表示一个key被访问的热度。
- 假如你使用的是LRU算法，一个key很久没有被访问到，只刚刚是偶尔被访问了一次，那么它就被认为是热点数据，不会被淘汰，而有些key将来是很有可能被访问到的则被淘汰了。
- 如果使用LFU算法则不会出现这种情况，因为使用一次并不会使一个key成为热点数据。




