singleflight 包源码与应用分析
==

### 简介

singleflightd的设计思路就是将一组相同的请求合并成一个请求，使用map存储， 只会有一个请求到达后方，使用sync.waitgroup包进行同步，对所有的请求返回相同的结果。

而这个设计思路正是他的应用场景所在。

- 比如热点缓存失效导致失效的一瞬间后续所有请求直接打到数据库（缓存击穿）
- 其他相同并发请求的合并处理，削减后续服务请求压力


### 源码分析

#### Group 结构

该结构用于生成一个全局的 singleflight 工具，其中包含一个互斥锁和一个map，map用来保存合并调用的方法

```
type Group struct {
	mu sync.Mutex       // 互斥锁，保证并发安全
	m  map[string]*call // 存储相同的请求，key是相同的请求，value保存延迟调用的信息
}
```

后续执行请求的call的结构

```
type call struct {
    wg sync.WaitGroup
    
    // val 存储返回值，在wg done之前只会写入一次
    val interface{}
    
    // 存储返回的错误信息
    err error

    // 标识别是否调用了Forgot方法
    forgotten bool

    // 统计相同请求的次数，在wg done之前写入
    dups  int
    
    // 使用DoChan方法使用，用channel进行通知
    chans []chan<- Result
}

// 调用结果
type Result struct {
    Val    interface{}  // 存储返回值
    Err    error        // 存储返回的错误信息
    Shared bool         // 标示结果是否是共享结果
}
```

#### 核心调用方法 Do

此方法就是使用WaitGroup来做多协程合并调用的核心

```
// 入参：key：标识相同请求，fn：要执行的函数
func (g *Group) Do(key string, fn func() (interface{}, error)) (v interface{}, err error, shared bool) {
	// 开启互斥锁
	g.mu.Lock()
	
	// map进行懒加载
	// 如果m是空的,初始化m
	if g.m == nil {
		g.m = make(map[string]*call)
	}
	
	// 判断是否有相同请求
	if c, ok := g.m[key]; ok {
	    // 相同请求次数累计
		c.dups++
		
		// 解锁，等待执行结果，不会有写入操作了
		g.mu.Unlock()
		
		// 使用 c 中的 wg来阻塞等待执行完成
		c.wg.Wait()

        // 区分panic错误和runtime错误
		if e, ok := c.err.(*panicError); ok {
			panic(e)
		} else if c.err == errGoexit {
			runtime.Goexit()
		}
		
		// 返回数据
		return c.val, c.err, true
	}
	
	// 之前没有这个请求，则需要new一个call结构的指针类型
	c := new(call)
	
	// sync.waitgroup 只开启一个请求运行，其他请求等待，所以只需要add(1)
	c.wg.Add(1)
	
	// 将数据存到map中
	g.m[key] = c
	
	// 释放锁，以便可以承接其他请求
	g.mu.Unlock()

    // 唯一的请求去执行调用函数
    //  这个方法中将执行  sync.waitgroup.Done 方法，通知其他Wait的协程继续执行 ）
	g.doCall(c, key, fn)
	
	// 返回结果
	return c.val, c.err, c.dups > 0
}
```

#### 内部执行方法 doCall

```
// doCall 独立key的调用处理
func (g *Group) doCall(c *call, key string, fn func() (interface{}, error)) {
	// 是否正常返回
	normalReturn := false
	
	// 是否发生过panic
	recovered := false

	// use double-defer to distinguish panic from runtime.Goexit,
	// more details see https://golang.org/cl/134395
	defer func() {
		// 通过这个来判断是否是runtime导致直接退出了
		if !normalReturn && !recovered {
		    // 返回runtime错误信息
			c.err = errGoexit
		}
        
        // 结束wg
		c.wg.Done()
		
		// 开启互斥锁，然后删除key
		g.mu.Lock()
		defer g.mu.Unlock()
		if !c.forgotten {
			delete(g.m, key)
		}

        // 检测是否出现了panic错误
		if e, ok := c.err.(*panicError); ok {
			// In order to prevent the waiting channels from being blocked forever,
			// needs to ensure that this panic cannot be recovered.
			// 如果是调用了doChan方法，为了避免channel死锁，这个panic要直接抛出去，不能recover住，要不就隐藏错误了
			if len(c.chans) > 0 {
			    // 开一个协程 panic
				go panic(e)
				// Keep this goroutine around so that it will appear in the crash dump.
				// 保持住这个goroutine，这样可以将panic写入crash dump
				select {} 
			} else {
				panic(e)
			}
		} else if c.err == errGoexit {
			// Already in the process of goexit, no need to call again
		} else {
			// Normal return
			// 正常返回 直接向channel写入数据
			for _, ch := range c.chans {
				ch <- Result{c.val, c.err, c.dups > 0}
			}
		}
	}()

	func() {
		defer func() {
			if !normalReturn {
				// Ideally, we would wait to take a stack trace until we've determined
				// whether this is a panic or a runtime.Goexit.
				//
				// Unfortunately, the only way we can distinguish the two is to see
				// whether the recover stopped the goroutine from terminating, and by
				// the time we know that, the part of the stack trace relevant to the
				// panic has been discarded.
				
				// 发生了panic，我们recover住，然后把错误信息返回给上层
				if r := recover(); r != nil {
					c.err = newPanicError(r)
				}
			}
		}()

        // 执行函数
		c.val, c.err = fn()
		
		// 正常返回标识
		normalReturn = true
	}()

    // 判断执行函数是否发生panic
	if !normalReturn {
		recovered = true
	}
}

```


#### DoChan和Forget方法
```
// DoChan is like Do but returns a channel that will receive the
// results when they are ready.
// The returned channel will not be closed.
// 按照英文字面理解：调用时候可以使用Do或者DoChan, 使用 DoChan 返回的channel没有关闭
func (g *Group) DoChan(key string, fn func() (interface{}, error)) <-chan Result {
	ch := make(chan Result, 1)
	g.mu.Lock()
	if g.m == nil {
		g.m = make(map[string]*call)
	}
	if c, ok := g.m[key]; ok {
		c.dups++
		// 此处追加 chan
		c.chans = append(c.chans, ch)
		g.mu.Unlock()
		return ch
	}
	c := &call{chans: []chan<- Result{ch}}
	c.wg.Add(1)
	g.m[key] = c
	g.mu.Unlock()

    // 协程调用（此处不同于Do）
	go g.doCall(c, key, fn)

    // 返回chan，可以在chan上获取数据
	return ch
}



```

#### Forget方法
```
// Forget tells the singleflight to forget about a key.  Future calls
// to Do for this key will call the function rather than waiting for
// an earlier call to complete.

// 释放某个 key 下次调用就不会阻塞等待了
func (g *Group) Forget(key string) {
	g.mu.Lock()
	if c, ok := g.m[key]; ok {
		c.forgotten = true
	}
	delete(g.m, key)
	g.mu.Unlock()
}
```