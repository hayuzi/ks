sync.Cond
== 

> 具体的源码分析我们可以看一下这一篇文章 https://zhuanlan.zhihu.com/p/365814673

### sync.Cond 结构体

该结构可以用来实现协程同步(不过官方一般不推荐使用，因为会使程序耦合严重，最好使用channel)

sync.Cond 结构如下：

```
type Cond struct {
    // 这是一个不允许复制的标记
    // noCopy可以嵌入到结构中，在第一次使用后不可复制,使用go vet作为检测使用。
	noCopy noCopy
	
	// L is held while observing or changing the condition
	// L是接口类型，用来继承实现了Locker里面方法的类型，也可以重写里面的方法。
	// 在sync.Cond中可以传入一个读写锁或互斥锁，当修改条件或者调用wait 方法时需要加锁。
	L Locker
	
	// 通知启动的协程队列
	notify  notifyList
	
	// copyChecker保留指向自身的指针以检测对象的复制。
	checker copyChecker
}
```

我们在使用的时候需要基本了该结构的几个方法的基本功能与实现

- cond.Wait()
    - Wait方法用来将一个协程加入条件队列中
- cond.Signal()
    - 唤起一个等待的goroutine
- cond.Broadcast()
    - 唤醒等待通知队列中的goroutine
    

### 应用场景

该结构应用场景比较少，一般用于条件广播唤醒所有队列来执行后续任务。

但是我们应该注意：wait方法将协程加入队列的时候是有顺序的， 但唤醒之后多个携程的执行不一定是完全按照顺序执行的