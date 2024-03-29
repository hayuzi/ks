分布式事务处理
==

## 1. 引言

### 1.1 事务需要满足ACID特性

- 原子性（Atomicity）
    - 可以理解为一个事务内的所有操作要么都执行，要么都不执行
- 一致性（Consistency）
    - 可以理解为数据是满足完整性约束的，也就是不会存在中间状态的数据
- 隔离性（Isolation）
    - 指的是多个事务并发执行的时候不会互相干扰，即一个事务内部的数据对于其他事务来说是隔离的
- 持久性（Durability）
    - 指的是一个事务完成了之后数据就被永远保存下来，之后的其他操作或故障都不会对事务的结果产生影响

### 1.2 分布式事务

分布式事务就是指事务的参与者、支持事务的服务器、资源服务器以及事务管理器分别位于不同的分布式系统的不同节点之上。
简单的说，就是一次大的操作由不同的小操作组成，这些小的操作分布在不同的服务器上，且属于不同的应用，分布式事务需要保证这些小操作要么全部成功，要么全部失败。 本质上来说，分布式事务就是为了保证不同数据库的数据一致性。

### 1.3 分布式系统的 CAP理论

CAP定理，又被叫作布鲁尔定理

- C (一致性):
    - 对某个指定的客户端来说，读操作能返回最新的写操作。对于数据分布在不同节点上的数据上来说，如果在某个节点更新了数据，那么在其他节点如果都能读取到这个最新的数据，那么就称为强一致，如果有某个节点没有读取到，那就是分布式不一致。
- A (可用性)：
    - 非故障的节点在合理的时间内返回合理的响应(不是错误和超时的响应)
      。可用性的两个关键一个是合理的时间，一个是合理的响应。合理的时间指的是请求不能无限被阻塞，应该在合理的时间给出返回。合理的响应指的是系统应该明确返回结果并且结果是正确的，这里的正确指的是比如应该返回50，而不是返回40。
- P (分区容错性):
    - 当出现网络分区后，系统能够继续工作。打个比方，这里个集群有多台机器，有台机器网络出现了问题，但是这个集群仍然可以正常工作。

CAP三者不能共有，在分布式系统中，网络无法100%可靠，分区其实是一个必然现象，如果我们选择了CA而放弃了P，那么当发生分区现象时，为了保证一致性，这个时候必须拒绝请求，但是A又不允许，所以分布式系统理论上不可能选择CA架构，只能选择CP或者AP架构。

对于CP来说，放弃可用性，追求一致性和分区容错性，我们的zookeeper其实就是追求的强一致。

对于AP来说，放弃一致性(这里说的一致性是强一致性)，追求分区容错性和可用性，这是很多分布式系统设计时的选择，后面的BASE也是根据AP来扩展。

没有绝对的CA存在，但是我们仍然要尽可能的维护CA。

### 1.4 BASE理论

BASE 是 Basically Available(基本可用)、Soft state(软状态)和 Eventually consistent (最终一致性)三个短语的缩写。是对CAP中AP的一个扩展

- 基本可用
    - 分布式系统在出现故障时，允许损失部分可用功能，保证核心功能可用。
- 软状态
    - 允许系统中存在中间状态，这个状态不影响系统可用性，这里指的是CAP中的不一致。
- 最终一致
    - 最终一致是指经过一段时间后，所有节点数据都将会达到一致。

BASE解决了CAP中理论没有网络延迟，在BASE中用软状态和最终一致，保证了延迟后的一致性。 BASE和 ACID
是相反的，它完全不同于ACID的强一致性模型，而是通过牺牲强一致性来获得可用性，并允许数据在一段时间内是不一致的，但最终达到一致状态。

## 2. 分布式事务的处理方案

### 2.1 两阶段提交（2PC）

2PC，Two-phase commit protocol，即两阶段提交协议，二阶段提交是一种强一致性设计， 2PC 引入一个事务协调者的角色来协调管理各参与者（也可称之为各本地资源）的提交和回滚，
二阶段分别指的是准备（投票）和提交两个阶段。

两阶段提交有两种角色：

- 事务协调者
    - 启动事务的执行
    - 将事务分成若干子事务，并将这些子事务分布到合适的站点去执行
    - 协调事务的结束，它可能导致事务被提交到所有的站点，或在所有站点终止
- 事务参与者（参与站点）
    - 在协调节点的调度下处理子事务

2PC 具体的执行流程如下：

- 第一阶段：事务协调器通知参与者准备提交事务，参与者准备成功之后向协调者返回成功，若有一个参与者返回的是准备不成功，那么事务执行失败。
- 第二阶段：事务协调器根据各个参与者的第一阶段的返回结果，发起最终提交事务的请求，若有一个参与者提交失败，则所有参与者都执行回滚，事务执行失败。

#### 更详细的流程：

投票准备阶段：

- 协调者向所有参与者发送prepare请求与事务内容，询问是否可以准备事务提交，并等待参与者的响应。
- 参与者执行事务中包含的操作，并记录undo日志（用于回滚）和redo日志（用于重放），但不真正提交。
- 参与者向协调者返回事务操作的执行结果，执行成功返回yes，否则返回no。

提交执行阶段： 若所有参与者都返回yes，说明事务可以提交：

- 协调者向所有参与者发送commit请求。
- 参与者收到commit请求后，将事务真正地提交上去，并释放占用的事务资源，并向协调者返回ack。
- 协调者收到所有参与者的ack消息，事务成功完成。

若有参与者返回no或者超时未返回，说明事务中断，需要回滚：

- 协调者向所有参与者发送rollback请求。
- 参与者收到rollback请求后，根据undo日志回滚到事务执行前的状态，释放占用的事务资源，并向协调者返回ack。
- 协调者收到所有参与者的ack消息，事务回滚完成。

![image](/Structure/image/2pc-success.png)
![image](/Structure/image/2pc-fail.png)

2PC 存在的问题： -「单点故障」：一旦事务管理器出现故障，整个系统不可用 -「数据不一致」：在阶段二，如果事务管理器只发送了部分 commit 消息，此时网络发生异常，那么只有部分参与者接收到 commit
消息，也就是说只有部分参与者提交了事务，使得系统数据不一致。 -「响应时间较长」：整个消息链路是串行的，要等待响应结果，不适合高并发的场景 -「不确定性」：当协事务管理器发送 commit 之后，并且此时只有一个参与者收到了
commit，那么当该参与者与事务管理器同时宕机之后，重新选举的事务管理器无法确定该条消息是否提交成功。

总结：

2PC 是一种尽量保证强一致性的分布式事务，因此它是同步阻塞的，而同步阻塞就导致长久的资源锁定问题，总体而言效率低，并且存在单点故障问题，在极端条件下存在数据不一致的风险。

关联扩展：数据库的 XA 事务

### 2.2 三阶段提交（3PC）

三阶段提交又称3PC，相对于2PC来说增加了CanCommit阶段和超时机制。 如果段时间内没有收到协调者的commit请求，那么就会自动进行commit，解决了2PC单点故障的问题。

3PC 包含了三个阶段，分别是准备阶段、预提交阶段和提交阶段，对应的英文就是：CanCommit、PreCommit 和 DoCommit。

- 第一阶段：「CanCommit阶段」这个阶段所做的事很简单，就是协调者询问事务参与者，你是否有能力完成此次事务。
    - 如果都返回yes，则进入第二阶段
    - 有一个返回no或等待响应超时，则中断事务，并向所有参与者发送abort请求
- 第二阶段：「PreCommit阶段」此时协调者会向所有的参与者发送PreCommit请求，参与者收到后开始执行事务操作，并将Undo和Redo信息记录到事务日志中。参与者执行完事务操作后（此时属于未提交事务的状态），就会向协调者反馈“Ack”表示我已经准备好提交了，并等待协调者的下一步指令。
- 第三阶段：「DoCommit阶段」在阶段二中如果所有的参与者节点都可以进行PreCommit提交，那么协调者就会从“预提交状态”转变为“提交状态”。然后向所有的参与者节点发送"doCommit"
  请求，参与者节点在收到提交请求后就会各自执行事务提交操作，并向协调者节点反馈“Ack”消息，协调者收到所有参与者的Ack消息后完成事务。
  相反，如果有一个参与者节点未完成PreCommit的反馈或者反馈超时，那么协调者都会向所有的参与者节点发送abort请求，从而中断事务。

3PC 的引入是为了解决提交阶段 2PC 协调者和某参与者都挂了之后新选举的协调者不知道当前应该提交还是回滚的问题。 新协调者来的时候发现有一个参与者处于预提交或者提交阶段，那么表明已经经过了所有参与者的确认了，所以此时执行的就是提交命令。
所以说 3PC 就是通过引入预提交阶段来使得参与者之间的状态得到统一，也就是留了一个阶段让大家同步一下。

但是这也只能让协调者知道该如何做，但不能保证这样做一定对，这其实和上面 2PC 分析一致，因为挂了的参与者到底有没有执行事务无法断定。

3PC 相对于 2PC 做了一定的改进：引入了参与者超时机制，并且增加了预提交阶段使得故障恢复之后协调者的决策复杂度降低，但整体的交互过程更长了，性能有所下降，并且还是会存在数据不一致问题

### 2.3 TCC (Try - Confirm - Cancel)

TCC其实就是采用的补偿机制，其核心思想是：「针对每个操作，都要注册一个与其对应的确认和补偿（撤销）操作」。它分为三个阶段： 「Try,Confirm,Cancel」

- Try阶段主要是对「业务系统做检测及资源预留」，其主要分为两个阶段
- Confirm 阶段主要是对「业务系统做确认提交」，Try阶段执行成功并开始执行 Confirm阶段时，默认 Confirm阶段是不会出错的。即：只要Try成功，Confirm一定成功。
- Cancel 阶段主要是在业务执行错误，需要回滚的状态下执行的业务取消，「预留资源释放」。

TCC 事务机制相比于2PC，解决了其几个缺点：

- 1.「解决了协调者单点」，由主业务方发起并完成这个业务活动。业务活动管理器也变成多点，引入集群。
- 2.「同步阻塞」：引入超时，超时后进行补偿，并且不会锁定整个资源，将资源转换为业务逻辑形式，粒度变小。
- 3.「数据一致性」，有了补偿机制之后，由业务活动管理器控制一致性

总之，TCC 就是通过代码人为实现了两阶段提交，不同的业务场景所写的代码都不一样，并且很大程度的「增加」了业务代码的「复杂度」，因此，这种模式并不能很好地被复用。
不过目前很多企业的业务上都做了TCC分布式事务，甚至最常用的分布式事务中间件比如 seata（扩展）；

### 2.4 Saga方案（补偿事务，失败补偿回滚之前数据）

Saga事务模型又叫做长时间运行的事务

其核心思想是「将长事务拆分为多个本地短事务」，由Saga事务协调器协调，如果正常结束那就正常完成，如果「某个步骤失败，则根据相反顺序一次调用补偿操作」。

Seata框架中一个分布式事务包含3中角色：

「Transaction Coordinator (TC)」： 事务协调器，维护全局事务的运行状态，负责协调并驱动全局事务的提交或回滚。「Transaction Manager (TM)」：
控制全局事务的边界，负责开启一个全局事务，并最终发起全局提交或全局回滚的决议。「Resource Manager (RM)」： 控制分支事务，负责分支注册、状态汇报，并接收事务协调器的指令，驱动分支（本地）事务的提交和回滚。
seata框架「为每一个RM维护了一张UNDO_LOG表」，其中保存了每一次本地事务的回滚数据。

具体流程：

- 1.首先TM 向 TC 申请「开启一个全局事务」，全局事务「创建」成功并生成一个「全局唯一的 XID」。
- 2.XID 在微服务调用链路的上下文中传播。
- 3.RM 开始执行这个分支事务，RM首先解析这条SQL语句，「生成对应的UNDO_LOG记录」。下面是一条UNDO_LOG中的记录，UNDO_LOG表中记录了分支ID，全局事务ID，以及事务执行的redo和undo数据以供二阶段恢复。
- 5.RM在同一个本地事务中「执行业务SQL和UNDO_LOG数据的插入」。在提交这个本地事务前，RM会向TC「申请关于这条记录的全局锁」。
    - •如果申请不到，则说明有其他事务也在对这条记录进行操作，因此它会在一段时间内重试，重试失败则回滚本地事务，并向TC汇报本地事务执行失败。
- 6.RM在事务提交前，「申请到了相关记录的全局锁」，然后直接提交本地事务，并向TC「汇报本地事务执行成功」。此时全局锁并没有释放，全局锁的释放取决于二阶段是提交命令还是回滚命令。
- 7.TC根据所有的分支事务执行结果，向RM「下发提交或回滚」命令。
    - • RM如果「收到TC的提交命令」，首先「立即释放」相关记录的全局「锁」，然后把提交请求放入一个异步任务的队列中，马上返回提交成功的结果给 TC。异步队列中的提交请求真正执行时，只是删除相应 UNDO LOG 记录而已。
    - • RM如果「收到TC的回滚命令」，则会开启一个本地事务，通过 XID 和 Branch ID 查找到相应的 UNDO LOG 记录。将 UNDO LOG 中的后镜与当前数据进行比较，
    - • 如果不同，说明数据被当前全局事务之外的动作做了修改。这种情况，需要根据配置策略来做处理。
    - • 如果相同，根据 UNDO LOG 中的前镜像和业务 SQL 的相关信息生成并执行回滚的语句并执行，然后提交本地事务达到回滚的目的，最后释放相关记录的全局锁。

### 2.5 本地消息表

本地消息表这个方案最初是ebay提出的。 ebay的完整方案https://queue.acm.org/detail.cfm?id=1394128。
此方案的核心是将需要分布式处理的任务通过消息日志的方式来异步执行。消息日志可以存储到本地文本、数据库或消息队列，再通过业务规则自动或人工发起重试。人工重试更多的是应用于支付场景，通过对账系统对事后问题的处理。

本地消息表的思想主要是依靠各个服务之间的本地事务来保证的。 就是在服务的本地建立一张消息表，一般是在数据库中。

当执行分布式事务的时候执行完本地操作后，在本地的消息表中插入一条数据。 然后将消息发送到MQ中，下一个服务接收到消息后执行本地操作，操作成功后更新消息表中的状态。
如果下一个服务执行失败了，那么消息表中的状态是不会变的，这样就靠定时任务去刷消息表来进行重试，但是这样需要保证被重试的服务是幂等的，这样就保证最终数据一致。

该方案实现的是最终一致性，并非强一致性保证。

### 2.6 基于可靠消息最终一致性方案

该方案借助了阿里的 RocketMQ。

第一步先给 Broker 发送事务消息即半消息，半消息不是说一半消息，而是这个消息对消费者来说不可见，然后发送成功后发送方再执行本地事务。 再根据本地事务的结果向 Broker 发送 Commit 或者 RollBack 命令。

并且 RocketMQ 的发送方会提供一个反查事务状态接口，如果一段时间内半消息没有收到任何操作请求，那么 Broker 会通过反查接口得知发送方事务是否执行成功，然后执行 Commit 或者 RollBack 命令。

如果是 Commit 那么订阅方就能收到这条消息，然后再做对应的操作，做完了之后再消费这条消息即可。

如果是 RollBack 那么订阅方收不到这条消息，等于事务就没执行过。

可以看到通过 RocketMQ 还是比较容易实现的，RocketMQ 提供了事务消息的功能，我们只需要定义好事务反查接口即可。

这种方案也是实现了「最终一致性」，对比本地消息表实现方案，不需要再建消息表，「不再依赖本地数据库事务」了，所以这种方案更适用于高并发的场景。目前市面上实现该方案的「只有阿里的 RocketMQ」。

### 2.7 最大努力通知方案

最大努力通知，其实也算是一种最终一致性的方案。
主要是当A系统执行完本地事务后，发送消息给MQ，然后去让B系统执行事务操作，如果B系统执行完成了，就消费消息，若B系统执行失败了，则执行重试，重试多次直到成功。若达到一定次数后还没成功就只能人工干预了。
所以最大努力通知其实只是表明了一种柔性事务的思想：我已经尽力我最大的努力想达成事务的最终一致了。

参考文章：
https://zhuanlan.zhihu.com/p/353781389
https://zhuanlan.zhihu.com/p/256374135

## 3. 相关的中间件

分布式事务中间件，目前主要集中在以下两个（ 分别由Go、Java实现 ）。 

建议将其文档初步过一遍，结合上面的内容对比着看，会对分布式事物的解决方案优缺点有更深入的理解。

**强烈推荐DTM的教程**

- [DTM](https://dtm.pub/other/opensource.html)
- [Seata](http://seata.io/zh-cn/docs/overview/what-is-seata.html)

