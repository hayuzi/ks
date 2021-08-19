MySQL主从远原理与维护
==

## 7. 主从复制

### 7.1 主备复制流程
- 1. 在备库B上通过change master命令，设置主库A的IP、端⼝、⽤户名、密码，以及要从哪个 位置开始请求binlog，这个位置包含⽂件名和⽇志偏移量。
- 2. 在备库B上执⾏start slave命令，这时候备库会启动两个线程，就是图中的io_thread和 sql_thread。其中io_thread负责与主库建⽴连接。
- 3. 主库A校验完⽤户名、密码后，开始按照备库B传过来的位置，从本地读取binlog，发给B。
- 4. 备库B拿到binlog后，写到本地⽂件，称为中转⽇志（relay log）。
- 5. sql_thread读取中转⽇志，解析出⽇志⾥的命令，并执⾏。 （后来由于多线程复制⽅案的引⼊，sql_thread演化成为了多个线程）

### 7.2 主备双M循环复制

#### binglog中记录的server id
- 1.规定两个库的server id必须不同，如果相同，则它们之间不能设定为主备关系；
- 2.⼀个备库接到binlog并在重放的过程中，⽣成与原binlog的server id相同的新的binlog；
- 3.每个库在收到从⾃⼰的主库发过来的⽇志后，先判断server id，如果跟⾃⼰的相同，表示这 个⽇志是⾃⼰⽣成的，就直接丢弃这个⽇志

#### 其他循环复制场景

- 在⼀个主库更新事务后，⽤命令set global server_id=x修改了server_id
- 有三个节点的时候，trx1在节点 B执⾏的，因此binlog上的 server_id就是B，binlog传给节点 A，然后A和C搭建了双M结构，就会出现循环复制

#### 循环复制解决方案
(1)先执行如下命令
```
stop slave； 
CHANGE MASTER TO IGNORE_SERVER_IDS=(server_id_of_B); 
start slave; 
```
(2)一段时间后恢复serverid值
```
stop slave； 
CHANGE MASTER TO IGNORE_SERVER_IDS=(); 
start slave;
```

### 7.3 备库并行复制策略

#### MySQL版本5.6之后加入
- 支持粒度是按库并行，如果实例上是单库并不能应用到该策略

#### MariaDB的并行策略
- 1.在⼀组⾥⾯⼀起提交的事务，有⼀个相同的commit_id，下⼀组就是commit_id+1；（binglog同组落磁盘的组，不会修改同一行，不冲突）
- 2.commit_id直接写到binlog⾥⾯；
- 3.传到备库应⽤的时候，相同commit_id的事务分发到多个worker执⾏；
- 4.这⼀组全部执⾏完成后，coordinator再去取下⼀批。

#### MySQL 5.7版本策略

- slave-parallel-type 来控制并⾏复制策略
- - 配置为DATABASE，表示使⽤MySQL 5.6版本的按库并⾏策略
- - 配置为 LOGICAL_CLOCK，表示的就是类似MariaDB的策略。不过，MySQL 5.7这个策 略，针对并⾏度做了优化
- 策略思想
- - 1.同时处于prepare状态的事务，在备库执⾏时是可以并⾏的；
- - 2.处于prepare状态的事务，与处于commit状态的事务之间，在备库执⾏时也是可以并⾏的。

##### 相关参数配置
- 1.binlog_group_commit_sync_delay参数，表示延迟多少微秒后才调⽤fsync;
- 2.binlog_group_commit_sync_no_delay_count参数，表示累积多少次以后才调⽤fsync

这两个参数是⽤于故意拉⻓binlog从write到fsync的时间，以此减少binlog的写盘次数。
在 MySQL 5.7的并⾏复制策略⾥，它们可以⽤来制造更多的“同时处于prepare阶段的事务”。这样 就增加了备库复制的并⾏度

#### MySQL版本5.7.22的策略

- 基于 WRITESET的并⾏复制
- - 新增了⼀个参数binlog-transaction-dependency-tracking，⽤来控制是否启⽤这个新 策略。
- - 1.COMMIT_ORDER，表示 根据同时进⼊prepare和commit来判断是否可以并⾏的策略。
- - 2.WRITESET，表示的是对于事务涉及更新的每⼀⾏，计算出这⼀⾏的hash值，组成集合 writeset。如果两个事务没有操作相同的⾏，也就是说它们的writeset没有交集，就可以并 ⾏。
- - 3.WRITESET_SESSION，是在WRITESET的基础上多了⼀个约束，即在主库上同⼀个线程先 后执⾏的两个事务，在备库执⾏的时候，要保证相同的先后顺序。

### 7.4 主备切换

#### 主从复制双M情况下 可靠性优先策略
- 1.判断备库B现在的seconds_behind_master，如果⼩于某个值（⽐如5秒）继续下⼀步，否 则持续重试这⼀步；
- 2.把主库A改成只读状态，即把readonly设置为true；
- 3.判断备库B的seconds_behind_master的值，直到这个值变成0为⽌；
- 4.把备库B改成可读写状态，也就是把readonly 设置为false；
- 5.把业务请求切到备库B。
- 这个切换流程，⼀般是由专⻔的HA系统来完成的，我们暂时称之为可靠性优先流程。（系统有不可用时间）

#### 主从复制双M情况下 可用性优先策略
- 直接切换， 把步骤4、5调整到最开始执行（系统可能数据不一致）
- 使用row格式的binlog时，数据不一致，问题更容易发现
- 一般情况下不建议使用该方案

#### 主库异常的判定方法
- 查表判断
- - 不完全准确 受到并发查询线程上限的影响（配置innodb_thread_concurrency参数，进入锁等待后，并发线程的计数会减一）
- 更新判断
- - 同样受并发线程上限影响，双M配置主备需要安排更新不同行
- 内部统计
- - file_summary_by_event_name表⾥统计了每次IO请求的时间

### 7.5 主备延迟

#### 产生原因与处理方案
- 备库机器性能低于主库（多备同机/硬件配置/参数配置不同等）
- - 备库采用双非1配置，可以略微降低IO压力
- - 理想来说应该 主备库选⽤相同规格的机器，并且做对称部署
- 备库压力大 ( 运营以及分析业务在备库执行导致 )
- - 采用一主多从的方式多机器提供读服务
- - 通过binglog输出到外部系统，通过外部系统如hadoop等来提供统计查询能力
- 大事务（如一次性大批删除、大表DDL）
- - 小批量轮番删除
- - 计划内DDL、新建临时表同步数据过去，追上了再切换
- - 主库DML语句并发⼤,从库qps⾼ ；、从库上在进⾏备份操作 、设置的是延迟备库 、备库空间不⾜的情况下
- 表上⽆主键的情况(主库利⽤索引更改数据,备库回放只能⽤全表扫描,这种情况可以调整slave_rows_search_algorithms参数适当优化下)

#### 主从延时过长业务解决方案
- 强制读取主库
- - 业务可以忍受的情况下忽略
- - 一般会伴随一个负责管理后端的组建，比如 Zookeeper
- sleep方案
- - 延时一段时间后再查询写入的信息，也可以将该方案做在业务前端
- 选择性读取主库
- - 加中间件层：维护一个写请求的key，读取的时候存在则走此处，一定时间后删除
- - 加缓存层： 维护一个写请求的key，失效时长主从延迟时长，读取的时候存在则路由到主库
- 分库、打开从库的并行复制

## 8. 高可用架构

### 8.1 一主多从的主备切换

#### 基于位点的主备切换（主库故障T时刻的binlog位点），需要跳过错误

#### GTID  ( 5.6版本引入 )

- GTID的全称是Global Transaction Identififier，也就是全局事务ID，是⼀个事务在提交的时候⽣ 成的，是这个事务的唯⼀标识
- 官方定义 GTID=source_id:transaction_id,  可以理解为GTID=server_uuid:gno
- server_uuid是⼀个实例第⼀次启动时⾃动⽣成的，是⼀个全局唯⼀的值； gno是⼀个整数，初始值是1，每次提交事务的时候分配给这个事务，并加1。
- 加上参数 gtid_mode=on和enforce_gtid_consistency=on启动mysql实例就可以开启GTID模式

#### 基于GTID的主备切换
- 实例B指定主库A'，基于主备协议建立连接，并把 GTID集合set_b发给A'，
- 实例A'算出set_a与set_b的差集，在set_a而不在set_b的GTID集合，判断A'的本地是否包含了这个差集需要的所有binlog事务
- - 如果不包含，表示A'已经删除B需要的binlog，直接返回错误
- - 如果全包含，A'从自己binlog中，找出第一个不在 set_b的事务，发给B
- 之后就从这个事务开始，往后读文件，按顺序取binlog发给B去执行

#### GTID和在线DDL

- 双M模式的在线DDL
- - 实际从库 stop slave，而后从库执行DDL，查出GTID并在主库提交一条该GTID的空事务，跳过复制流程。执行主备切换后再来一遍

### 8.2 数据误删处理（重在预防）

#### 误删行数据
- Flashback工具解析binlog
- 线上数据不做删除，所有业务数据伪删除，除非归档

#### 误删库/表
- 定期全量备份，实时备份binlog，恢复时跳过误删的记录，备份恢复功能定期演练
- 延迟复制备库

#### 预防误删库/表
- 账号分离
- - 只读权限，收紧权限
- 制定操作规范
- - 先改名，后删除，只能删除固定格式名称的表等


## 9. 维护处理

### 9.1 kill相关知识

#### kill的方式
- 结束某个线程   kill query thread_id
- 断开线程连接   kill connection thread_id

#### kill延时
- 如果执行kill之后线程没有立即退出, 因为线程没有执行到判断线程状态的逻辑；或者终止逻辑耗时较长

### 9.2 内存相关

#### 全表扫描对server 层的影响

- 数据取发流程是 读取然后经过net_buffer批量轮流发送的

#### buffer pool
- 数据页维护在内存buffer pool中还可以加速查询， 查询加速效果的重要指标: 内存命中率
- InnoDB Buffer Pool的⼤⼩是由参数 innodb_buffer_pool_size确定的，⼀般建议设置成可⽤物 理内存的60%~80%。
- InnoDB内存管理⽤的是最近最少使⽤ (Least Recently Used, LRU)算法，这个算法的核⼼就是淘汰最久未使⽤的数据。
- 优化LRU淘汰算法，通过链表实现（ 5/3 青老区域 ），针对全表扫描做了优化