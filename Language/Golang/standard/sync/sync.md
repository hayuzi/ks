sync包主要功能源码分析
==

### sync.Pool

sync.Pool 主要用来缓解频繁创建对象带来大量GC的问题

sync.Pool 可以将暂时不用的对象缓存起来，下次需要的时候直接使用，不用再次经过内存分配（ 减轻GC压力提升系统性能 ）

sync.Pool 是协程安全的，在多个goroutine都需要创建同一个对象的时候，使用sync.Pool来处理，这样只会创建一个对象，其他goroutine会从这个对象池中取出一个对象(如果池中已经存在)

sync.Pool底层实现远离可以通过这篇文章来了解 [https://zhuanlan.zhihu.com/p/399150710](https://zhuanlan.zhihu.com/p/399150710)

### sync.Map

sync.Map 提供协程安全的Map功能。 sync.Map 提供 Load、Store、Delete几个方法来操作其中的数据。该功能是自 Go 1.9版本加入的。 在这之前，我们想要安全是使用Map，需要配合
sync.Mutex来实现。

这两种方式实现Map的数据并发读写，各有优势:

sync.Map的性能高体现在读操作远多于写操作的时候。 极端情况下，只有读操作时，是普通map的性能的44.3倍（待验证）。 反过来，如果是全写，没有读，那么sync.Map还不如加普通map+mutex锁呢。只有普通map性能的一半。
建议使用sync.Map时一定要考虑读定比例。当写操作只占总操作的<=1/10的时候，使用sync.Map性能会明显高很多。

#### sync.Map的源码分析

主要原理就是在 互斥锁控制的一个map之外再加一个缓存层（可以原子操作更新或读取），实现读取情况下的无锁优化

源码分析参考:

- [https://zhuanlan.zhihu.com/p/355417981](https://zhuanlan.zhihu.com/p/355417981)
- [https://zhuanlan.zhihu.com/p/413467399](https://zhuanlan.zhihu.com/p/413467399)
- [https://zhuanlan.zhihu.com/p/270598980](  https://zhuanlan.zhihu.com/p/270598980)

### sync.WaitGroup

- 数据结构包含：计数器，等待者个数，信号量
- add以及Done的语法，基于原子操作（sync/atomic包）实现并发的控制
- wait通过阻塞当前goroutine等待其他goroutine的执行，完成之后，会重新唤醒当前的goroutine

### sync.Mutex 互斥锁

### sync.RWMutex 读写锁
