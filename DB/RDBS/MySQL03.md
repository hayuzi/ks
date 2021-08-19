MySQL关联查询以及导入导出
==

## 10. join查询

### 10.1 join查询类型

#### 交叉连接 CROSS JOIN
- 交叉连接(笛卡尔积)
- select r.*,s.* from r,s

#### 内连接 INNER JOIN
- 等值连接：ON A.id=B.id
- 不等值连接：ON A.id > B.id
- 自连接：SELECT * FROM A T1 INNER JOIN A T2 ON T1.id=T2.pid

#### 左外连接 LEFT JOIN 
- LEFT OUTER JOIN, 以左表为主，先查询出左表，按照ON后的关联条件匹配右表，没有匹配到的用NULL填充，可以简写成LEFT JOIN

#### 右外连接 RIGHT JOIN
- RIGHT OUTER JOIN, 以右表为主，先查询出右表，按照ON后的关联条件匹配左表，没有匹配到的用NULL填充，可以简写成RIGHT JOIN

#### 联合查询（UNION与UNION ALL）
- 就是把多个结果集集中在一起，UNION前的结果为基准，需要注意的是联合查询的列数要相等，相同的记录行会合并
- UNION 操作会对结果去重且排序，所以从速度来说， UNION ALL会更胜一筹
- 如果使用UNION ALL，不会合并重复的记录行
- 效率 UNION ALL  高于 UNION

#### 全连接（FULL JOIN）
- MySQL不支持全连接
- 可以使用LEFT JOIN 和UNION和RIGHT JOIN联合使用

### 10.2 join查询流程

#### Index Nested-Loop Join，简称NLJ
- 从驱动表查询一行数据R，提取关联字段值，再去被驱动表索引查询满足条件的数据，跟R组成一行，作为结果集合的一部分。如此重复步骤循环
- 这种情况可以利用到被驱动表的索引，适合小表做驱动表，时间复杂度会好点

#### Simple Nested-Loop Join，MySQL也不用
- 驱动表自索引查询一行数据R，主键取数据，再查询被驱动表（无索引，全表扫描），跟R组成行数据。循环其他行

#### Block Nested-Loop Join，简称BNL
- 1.把表t1的数据读⼊线程内存join_buffer中，由于我们这个语句中写的是select *，因此是把整 个表t1放⼊了内存；（如果jion_buffer放不下，可以分段放）
- 2.扫描表t2，把表t2中的每⼀⾏取出来，跟join_buffer中的数据做对⽐，满⾜join条件的，作 为结果集的⼀部分返回。
- 与简单jion复杂度一样，但是内存操作节省磁盘io操作，join_buffer_size 越大效果越好

#### 小表的确定
- 在决定哪个表做驱动表的时候，应该是两个表按照各⾃的条件过滤，过滤完 成之后，计算参与join的各个字段的总数据量，数据量⼩的那个表，就是“⼩表”，应该作为驱动 表

### 10.3 join语句优化

#### Multi-Range Read优化 (MRR)
- 这个优化的目的是尽量使用顺序读盘（回表是单行查询，一般情况主键递增的数据是顺序写入磁盘的，这样区间查询比较接近顺序读，能够提升读性能）
- 根据索引a定位满足条件的记录，将id值放入read_rnd_buffer中，并对id进行性递增排序，之后在一次到主键ID的索引汇总查记录并返回结果（read_rnd_buffer_size参数控制空间大小）

#### Batched Key Acess(BKA)算法
- MySQL5.6版本引入
- - 使用BKA依赖MRR，需要先设置 set optimizer_switch='mrr=on,mrr_cost_based=off,batched_key_access=on';
- NLJ使用该方法优化，积累获取的驱动表ID，再批量获取被驱动表数据
- BNL优化，可以尝试在被驱动表加索引转换为BKA算法，如果不适合，则可以提取满足条件的被驱动表数据组建临时表，给查询关联字段加上临时索引，触发BKA算法

#### 扩展-hash join，MySQL的优化器和执行器不支持
- 可以考虑业务层做hash结构的比对
- MySQL8.0做了相关支持

## 11. 临时表相关

### 11.1 临时表特性

- 1.建表语法是create temporary table …。
- 2.⼀个临时表只能被创建它的session访问，对其他线程不可⻅。所以，图中session A创建的 临时表t，对于session B就是不可⻅的。
- 3.临时表可以与普通表同名。
- 4.session A内有同名的临时表和普通表的时候，show create语句，以及增删改查语句访问的 是临时表

### 11.2 临时表与binlog
- 只在binlog_format=statment/mixed 的时候，binlog中才会记录临时表的操 作。
- MySQL在记录binlog的时候，会把主库执⾏这个语句的线程id写到binlog中。这样，在备库的应 ⽤线程就能够知道执⾏每个语句的主库线程id，并利⽤这个线程id来构造临时表的 table_def_key

### 11.3 临时表的应⽤
- 临时存储查询数据
- 分库分表时候用临时表来汇总排序

### 11.4 group by 优化
- 1.如果对group by语句的结果没有排序要求，要在语句后⾯加 order by null；
- 2.尽量让group by过程⽤上表的索引，确认⽅法是explain结果⾥没有Using temporary 和 Using filesort；
- 3.如果group by需要统计的数据量不⼤，尽量只使⽤内存临时表；也可以通过适当调⼤ tmp_table_size参数，来避免⽤到磁盘临时表；
- 4.如果数据量实在太⼤，使⽤SQL_BIG_RESULT这个提示，来告诉优化器直接使⽤排序算法 得到group by的结果。

## 12. 数据库引擎

### 12.1 innoDB
以上大部分内容都是基于InnoDB的，这里不多说

#### innoDB分区表
- 分区表跟⽤户分表⽐起来，有两个绕不开的问题：⼀个是第⼀次访问的时候需要访问所有分区，另⼀个是共⽤MDL锁。
- 如果要使⽤分区表，就不要创建太多的分区。
- 怎么给分区表t创建⾃增主键。由于MySQL要求主键包含所有的分区字段，所以 肯定是要创建联合主键的

### 12.2 Memory

#### 内存表索引
- 使用hash索引， 索引与数据分开存放
- 可以使用 alter table t1 add index a_btree_index using btree (id);的方式加 Btree索引

#### 内存表的锁
- 不支持行锁，只支持表锁

#### 特性
- 数据存放在内存中，如果机器重启，数据表结构与数据都会丢失
- 在数据库重启之后，MySQL会往binlog⾥⾯写⼊⼀⾏DELETE FROM t1，来删除内存表，这样的话，主备复制的备库内存表也会被清除
- 内存表在生产环境几乎不用

#### 与InnoDB的区别
- 1.InnoDB表的数据总是有序存放的，⽽内存表的数据就是按照写⼊顺序存放的；
- 2.当数据⽂件有空洞的时候，InnoDB表在插⼊新数据的时候，为了保证数据有序性，只能在固定的位置写⼊新值，⽽内存表找到空位就可以插⼊新值；
- 3.数据位置发⽣变化的时候，InnoDB表只需要修改主键索引，⽽内存表需要修改所有索引；
- 4.InnoDB表⽤主键索引查询时需要⾛⼀次索引查找，⽤普通索引查询的时候，需要⾛两次索引 查找。⽽内存表没有这个区别，所有索引的“地位”都是相同的。
- 5.InnoDB⽀持变⻓数据类型，不同记录的⻓度可能不同；内存表不⽀持Blob 和 Text字段，并且即使定义了varchar(N)，实际也当作char(N)，也就是固定⻓度字符串来存储，因此内存表 的每⾏数据⻓度相同。

## 13. 如何快速复制表

### 13.1 mysqldump⽅法导出到临时文件

#### 命令与参数解析
- mysqldump -h$host -P$port -u$user --add-locks --no-create-info --single-transaction --no-create-info --set-gtid-purged --result-file
- 如此得到的数据是组织好的 insert 语句。另外mysqldump提供了⼀个–tab参数，可以同时导出表结构定义⽂件和csv数据⽂件。
- 1.–single-transaction的作⽤是，在导出数据的时候不需要对表db1.t加表锁，⽽是使⽤ START TRANSACTION WITH CONSISTENT SNAPSHOT的⽅法；
- 2.–add-locks设置为0，表示在输出的⽂件结果⾥，不增加" LOCK TABLES t WRITE;" ；
- 3.–no-create-info的意思是，不需要导出表结构；
- 4.–set-gtid-purged=off表示的是，不输出跟GTID相关的信息；
- 5.–result-file指定了输出⽂件的路径，其中client表示⽣成的⽂件是在客户端机器上的。

### 13.2 导出CSV文件

#### 导出到服务端本地
##### 命令与规则
- select * from db1.t where a>900 into outfile '/server_tmp/t.csv';
- select …into outfifile⽅法不会⽣成表结构⽂件, 所以我们导数据时还需要单 独的命令得到表结构定义
- 1.这条语句会将结果保存在服务端
- 2.into outfile指定了⽂件的⽣成位置（/server_tmp/），这个位置必须受参数secure_file_priv 的限制
- 3.这条命令不会帮你覆盖⽂件, 如果存在同名你文件执行会报错
- 4.这条命令⽣成的⽂本⽂件中，原则上⼀个数据⾏对应⽂本⽂件的⼀⾏。数据中的换⾏符、制表符这类符号，前⾯都会跟上“\”这个转义符

#### 将服务端本地csv文件倒入库中
##### 命令与规则
- load data infile '/server_tmp/t.csv' into table db2.t;
- 主库binlog格式为statement时，除了记录该行命令，还会将文件写入binlog
- 执行命令的时候如果 是 local file，则加载客户端本地文件数据，如果不带 local关键字，则会读取服务端文件

### 13.3 物理拷贝

#### 基础解释

##### 直接拷贝数据文件无效
- ⼀个InnoDB表，除了包含这两个物理⽂件外，还需要在数据字典中注册。
- 直接拷⻉这两 个⽂件的话，因为数据字典中没有db2.t这个表，系统是不会识别和接受它们的。

#### 具体操作方法

##### 导出方法
- 在MySQL 5.6版本引⼊了可传输表空间(transportable tablespace)的⽅法，可以通过导出+导⼊表空间的⽅式，实现物理拷⻉表的功能

##### 导出流程
- 1.执⾏ create table r like t，创建⼀个相同表结构的空表；
- 2.执⾏alter table r discard tablespace，这时候r.ibd⽂件会被删除；
- 3. 执⾏flush table t for export，这时候db1⽬录下会⽣成⼀个t.cfg⽂件；
- 4. 在db1⽬录下执⾏cp t.cfg r.cfg; cp t.ibd r.ibd；这两个命令；
- 5. 执⾏unlock tables，这时候t.cfg⽂件会被删除；
- 6. 执⾏alter table r import tablespace，将这个r.ibd⽂件作为表r的新的表空间，由于这个⽂ 件的数据内容和t.ibd是相同的，所以表r中就有了和表t相同的数据。

### 13.4 xtranbackup

#### 介绍
- 100G 以上的库，可以考虑用 xtranbackup 来做，备份速度明显要比 mysqldump 要快
- 一般是选择一周一个全备，其余每天进行增量备份，备份时间为业务低峰期

#### 备份原理
- xtrabackup 属于物理备份，直接拷贝表空间文件，同时不断扫描产生的 redo 日志并保存下来


