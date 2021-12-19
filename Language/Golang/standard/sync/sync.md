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

sync.Map的性能高体现在读操作远多于写操作的时候。 极端情况下，只有读操作时，是普通map的性能的44.3倍。 反过来，如果是全写，没有读，那么sync.Map还不如加普通map+mutex锁呢。只有普通map性能的一半。
建议使用sync.Map时一定要考虑读定比例。当写操作只占总操作的<=1/10的时候，使用sync.Map性能会明显高很多。

#### sync.Map的源码分析

### sync.WaitGroup

### sync.Mutex 互斥锁

### sync.RWMutex 读写锁
