sync.atomic包要点分析
==

### 基本介绍

> atomic包中的原子操作则由底层硬件直接提供支持。在 CPU 实现的指令集里，有一些指令被封装进了atomic包，这些指令在执行的过程中是不允许中断（interrupt）的，因此原子操作可以在lock-free的情况下保证并发安全，并且它的性能也能做到随 CPU 个数的增多而线性扩展

### atomic.value

> atomic.value分析 可以参考  https://zhuanlan.zhihu.com/p/79584247

整体来说，这个包内容不需要过多分析，主要需要了解硬件底层架构设计.

大多数高级语言的原子操作，是基于硬件提供的实现. 在不同的处理器架构下，具体的实现方式不同。

多核心处理器情况下，我们要了解MESI缓存协议。

- [MESI缓存协议](https://en.wikipedia.org/wiki/MESI_protocol)
- [缓存一致性](https://en.wikipedia.org/wiki/Cache_coherence)


### MESI

MESI是4个缓存状态的缩写：
- M  Modified (已变更: cache line 仅仅在当前缓存, 脏数据, 未回写到主存 )
- E  Exclusive (独占: cache line 仅仅在当前缓存, 干净数据，和主存相同 )
- S  Shared (共享：cache line 可能存在于其他缓存中，干净数据，和主存同 )
- I  Invalid (无效：cache line 是无效的)

以上四个状态按照一定的规则相互转换






