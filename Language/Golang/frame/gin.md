Gin框架原理分析
==

### 1. Gin框架的核心功能

Gin框架主要有三个核心功能：

- 路由管理与处理映射
- 中间件功能
- 请求数据预处理和上下文流控管理

请求数据预处理我们这里先跳过不讲，只分析框架的基础流程。这个涉及到两个核心结构体。 一个是全局的 gin.Engine 还有一个 gin.Context

在业务使用中，我们大概有这么一个流程

- 使用的时候，我们会生成一个全局唯一的 gin.Engine 结构，并保存指针
- 在 *gin.Engine上面注册中间件与路由以及业务处理方法
- 所有的处理方法都接收一个 *gin.Context 参数（ 包括中间件与业务函数 ）
- 上下文信息会在连接进来正式处理的时候注入请求与返回IO的相关内容
- 我们后续只需要在业务处理函数中管理相应的逻辑即可

下面简单分析这两个结构体

#### 1.1 基础引擎gin.Engine

```
type Engine struct {
     // 路由组
	RouterGroup

	// Enables automatic redirection if the current route can't be matched but a
	// handler for the path with (without) the trailing slash exists.
	// For example if /foo/ is requested but a route only exists for /foo, the
	// client is redirected to /foo with http status code 301 for GET requests
	// and 307 for all other request methods.
	// 路由重定向 (以/为结尾的路由，如果未匹配到，是否重定向到截取掉/的路由)
	RedirectTrailingSlash bool

	// If enabled, the router tries to fix the current request path, if no
	// handle is registered for it.
	// First superfluous path elements like ../ or // are removed.
	// Afterwards the router does a case-insensitive lookup of the cleaned path.
	// If a handle can be found for this route, the router makes a redirection
	// to the corrected path with status code 301 for GET requests and 307 for
	// all other request methods.
	// For example /FOO and /..//Foo could be redirected to /foo.
	// RedirectTrailingSlash is independent of this option.
	// 另一个路由修复标记，具体功能查看源码的标准注释
	RedirectFixedPath bool

	// If enabled, the router checks if another method is allowed for the
	// current route, if the current request can not be routed.
	// If this is the case, the request is answered with 'Method Not Allowed'
	// and HTTP status code 405.
	// If no other Method is allowed, the request is delegated to the NotFound
	// handler.
	// 路由请求方法状态码返回选择标记（ 404 还是 405(不允许的HTTP方法true)）
	HandleMethodNotAllowed bool

	// If enabled, client IP will be parsed from the request's headers that
	// match those stored at `(*gin.Engine).RemoteIPHeaders`. If no IP was
	// fetched, it falls back to the IP obtained from
	// `(*gin.Context).Request.RemoteAddr`.
	// 是否从请求头解析客户端IP
	ForwardedByClientIP bool

	// List of headers used to obtain the client IP when
	// `(*gin.Engine).ForwardedByClientIP` is `true` and
	// `(*gin.Context).Request.RemoteAddr` is matched by at least one of the
	// network origins of `(*gin.Engine).TrustedProxies`.
	// 用于在以下（...）情况下获取客户端IP的标头列表：
	RemoteIPHeaders []string

	// List of network origins (IPv4 addresses, IPv4 CIDRs, IPv6 addresses or
	// IPv6 CIDRs) from which to trust request's headers that contain
	// alternative client IP when `(*gin.Engine).ForwardedByClientIP` is
	// `true`.
	// 网络源列表
	TrustedProxies []string

	// #726 #755 If enabled, it will trust some headers starting with
	// 'X-AppEngine...' for better integration with that PaaS.
	AppEngine bool

	// If enabled, the url.RawPath will be used to find parameters.
	UseRawPath bool

	// If true, the path value will be unescaped.
	// If UseRawPath is false (by default), the UnescapePathValues effectively is true,
	// as url.Path gonna be used, which is already unescaped.
	// 请求路径是否需要 unescape （解码）
	UnescapePathValues bool

	// Value of 'maxMemory' param that is given to http.Request's ParseMultipartForm
	// method call.
	MaxMultipartMemory int64

	// RemoveExtraSlash a parameter can be parsed from the URL even with extra slashes.
	// See the PR #1817 and issue #1644
	RemoveExtraSlash bool

	delims           render.Delims
	secureJSONPrefix string
	HTMLRender       render.HTMLRender
	FuncMap          template.FuncMap
	allNoRoute       HandlersChain
	allNoMethod      HandlersChain
	noRoute          HandlersChain
	noMethod         HandlersChain
	
	// sync.Pool是一个对象缓存池，可以放一组对象，通过Put置入、Get取出. 
    // 初始化sync.Pool的时候可以给一个New函数
    // 在Get获取时候，如果对象池为空，回调用New函数创建一个对象并返回，如果没有New函数，则返回nil
    // 并发安全的
    // Gin框架此处用来存放一组 *gin.Conetext
	pool             sync.Pool
	trees            methodTrees
	maxParams        uint16
	trustedCIDRs     []*net.IPNet
}
```

#### 1.2 上下文信息gin.Context

```
type Context struct {
	writermem responseWriter
	Request   *http.Request     // HTTP请求信息
	Writer    ResponseWriter    // HTTP返回输入io

	Params   Params             // 请求参数（路由中提取）
	handlers HandlersChain      // 处理函数（中间件与执行函数链）
	index    int8               // handlers的执行计数，在中间件调用中起关键作用
	fullPath string

	engine *Engine
	params *Params

	// This mutex protect Keys map
	// 互斥锁，字面意思，用于下面KV的保护
	mu sync.RWMutex

	// Keys is a key/value pair exclusively for the context of each request.
	// KV存储的map
	Keys map[string]interface{}

	// Errors is a list of errors attached to all the handlers/middlewares who used this context.
	// Errors 附加到使用此上下文的所有处理程序/中间件的错误列
	Errors errorMsgs

	// Accepted defines a list of manually accepted formats for content negotiation.
	// Accepted 定义用于内容协商的手动接受格式列表
	Accepted []string

	// queryCache use url.ParseQuery cached the param query result from c.Request.URL.Query()
	// c.Request.URL.Query()的缓存
	queryCache url.Values

	// formCache use url.ParseQuery cached PostForm contains the parsed form data from POST, PATCH,
	// or PUT body parameters.
	formCache url.Values

	// SameSite allows a server to define a cookie attribute making it impossible for
	// the browser to send this cookie along with cross-site requests.
	// 同源策略 跨域相关()
	sameSite http.SameSite
}
```

### 2. Gin框架使用时候的执行流程

我们通常会先通过封装router方法，来返回*gin.Engine, 并绑定到http.Server中

```
// 初始化gin引擎， 并获取到返回的*gin.Engines
routers := gin.Default() 

// 紧接着我们那通过路由注册方法，将路由信息注册到路由树上（ 路由树形结构待分析 ）
routers.GET("/foo", func (c *gin.Context) {
    // test
})

// 启动服务，并将 *gin.Engine 作为服务的处理handler
// 当然也可以通过 routers.Run
s := &http.Server{
    Addr:           ":80",
    Handler:        routers,
    ReadTimeout:    time.Secone * 10,
    WriteTimeout:   time.Secone * 10,
    MaxHeaderBytes: 1 << 20,
}
err := s.ListenAndServe()
if err != nil {
    panic(err)
}
```

这样一来，在http请求进来的时候，server会自动触发调用 *gin.Engine.ServeHTTP 方法，执行处理函数。 我们继续跟随 这个方法去解析执行原理。

```
// ServeHTTP conforms to the http.Handler interface.
func (engine *Engine) ServeHTTP(w http.ResponseWriter, req *http.Request) {
    // 从全局的engine.pool对象池中获取一个*Context
	c := engine.pool.Get().(*Context)
	// 将http返回结果的io具柄写入到Context中
	c.writermem.reset(w)
	// 将请求数据注入到 Contex的请求中
	c.Request = req
	// 重置Context的信息
	c.reset()

    // 处理业务流程, 该方法主要是根据请求方法以及请求Path，从已经注册的路由树上获取已经注册的处理方法链
    // 获取到方法链之后，赋值给 c.handlers , 后面通过 c.Next方法执行主所有注册的方法（ 包括中间件与业务方法 ）
	engine.handleHTTPRequest(c)
    
    // 用完的*Context放回到pool中
	engine.pool.Put(c)
}
```

c.Next 方法我们在后面再分析，此处先看一下路由与中间件注入的逻辑

### 3. Gin框架的路由实现

我们通过Use方法注册中间件。 通过源码追踪分析，我们可以知道，Gin框架通过 Use方法，将中间件执行方法挂载到路由分组上。

```
func (engine *Engine) Use(middleware ...HandlerFunc) IRoutes {
	engine.RouterGroup.Use(middleware...)
	engine.rebuild404Handlers()
	engine.rebuild405Handlers()
	return engine
}

// Use adds middleware to the group, see example code in GitHub.
func (group *RouterGroup) Use(middleware ...HandlerFunc) IRoutes {
	group.Handlers = append(group.Handlers, middleware...)
	return group.returnObj()
}
```

框架 Engine 通过GET、POST等方法注册路由方法、Group注册分组路由，我们追踪一下 GET 路由注册方法

```
// 注册一个GET路由
func (group *RouterGroup) GET(relativePath string, handlers ...HandlerFunc) IRoutes {
	return group.handle(http.MethodGet, relativePath, handlers)
}

// 此处将在路由分组上添加方法
func (group *RouterGroup) handle(httpMethod, relativePath string, handlers HandlersChain) IRoutes {
	// 获取绝对路径
	absolutePath := group.calculateAbsolutePath(relativePath)
	// 获取处理方法链，该方法会获取分组上的所有前置方法链，并再增加该条注册的函数，放在最后
	// 注意这个方法中是设置了方法上限的  const abortIndex int8 = math.MaxInt8 / 2. 并不能无限挂载合并
	handlers = group.combineHandlers(handlers)
	// 这个方法将数据加入到路由树中
	group.engine.addRoute(httpMethod, absolutePath, handlers)
	// 返回该路由分组
	return group.returnObj()
}


// 该方法在Engine上注册路由以及处理方法链路
func (engine *Engine) addRoute(method, path string, handlers HandlersChain) {
	assert1(path[0] == '/', "path must begin with '/'")
	assert1(method != "", "HTTP method can not be empty")
	assert1(len(handlers) > 0, "there must be at least one handler")

	debugPrintRoute(method, path, handlers)

    // engine.trees 按照不同的HTTP请求方法存储了多个树形路由结构
    // root 是树形结构的根节点
	root := engine.trees.get(method)
	
	// 如果根节点不存在，就新创建一个
	if root == nil {
		root = new(node)
		root.fullPath = "/"
		engine.trees = append(engine.trees, methodTree{method: method, root: root})
	}
	
	// 正式在树中挂载路由数据与处理方法
	root.addRoute(path, handlers)

	// Update maxParams
	if paramsCount := countParams(path); paramsCount > engine.maxParams {
		engine.maxParams = paramsCount
	}
}
```

Tree整体结构是一个前缀树, 字典树的一种

Tree中的 node的具体结构如下:
> 参考 https://segmentfault.com/a/1190000016655709
> 这其中包括整体的路由树形结构的实例展示

```
type node struct {
    path      string           // 当前节点相对路径（与祖先节点的 path 拼接可得到完整路径）
    indices   string           // 所有孩子节点的path[0]组成的字符串，并与children的索引位置一一对应，根据首个字符索引可以跳转相应的子节点
    children  []*node          // 孩子节点
    handlers  HandlersChain    // 当前节点的处理函数（包括中间件）
    priority  uint32           // 当前节点及子孙节点的实际路由数量
    nType     nodeType         // 节点类型
    maxParams uint8            // 子孙节点的最大参数数量
    wildChild bool             // 孩子节点是否有通配符（wildcard）// 如果孩子节点是通配符（*或者:），则该字段为 true。
}
```

其中 path 和 indices，使用了前缀树的逻辑。

举个例子： 如果我们有两个路由，分别是 /index，/inter， 则根节点为 {path: "/in", indices: "dt"...}，这里面的indices对应的值 dt 是"dex", "ter"的首个字符组合的
两个children子节点为 {path: "dex", indices: ""}，{path: "ter",indices: ""} ，正好对应 d 以及 t 的索引位置

```
const (
    static nodeType = iota // 普通节点，默认
    root       // 根节点
    param      // 参数路由，比如 /user/:id
    catchAll   // 匹配所有内容的路由，比如 /article/*key
)
```

param 与 catchAll 使用的区别就是 : 与 * 的区别。* 会把路由后面的所有内容赋值给参数 key；但 : 可以多次使用。 比如：/user/:id/:no 是合法的，但 /user/*id/:no 是非法的，因为 *
后面所有内容会赋值给参数 id。

```
// 看一下 root.addRoute方法
// addRoute将具有给定句柄的节点添加到路径。 该方法不是并发安全的！ 这个需要注意
// addRoute adds a node with the given handle to the path.
// Not concurrency-safe!
func (n *node) addRoute(path string, handlers HandlersChain) {
	fullPath := path
	n.priority++

	// Empty tree
	if len(n.path) == 0 && len(n.children) == 0 {
		n.insertChild(path, fullPath, handlers)
		n.nType = root
		return
	}

	parentFullPathIndex := 0

walk:
	for {
		// Find the longest common prefix.
		// This also implies that the common prefix contains no ':' or '*'
		// since the existing key can't contain those chars.
		// 查找最长的公共前缀
        // 这也意味着公共前缀不包含“：”或“*”
        // 因为现有key不能包含这些字符。
        
		i := longestCommonPrefix(path, n.path)

		// Split edge
		// 原来路由上的树形结构，如果长度比共同有长度要长的话，需要将后半段截取出来成为子节点
		// 并更新自身的数据
		if i < len(n.path) {
			child := node{
				path:      n.path[i:],
				wildChild: n.wildChild,
				indices:   n.indices,
				children:  n.children,
				handlers:  n.handlers,
				priority:  n.priority - 1,
				fullPath:  n.fullPath,
			}

			n.children = []*node{&child}
			// []byte for proper unicode char conversion, see #65
			n.indices = bytesconv.BytesToString([]byte{n.path[i]})
			n.path = path[:i]
			n.handlers = nil
			n.wildChild = false
			n.fullPath = fullPath[:parentFullPathIndex+i]
		}

		// Make new node a child of this node
		// 如果公有长度比新增路由短，则看情况新增一个节点
		if i < len(path) {
			path = path[i:]
			c := path[0]

			// '/' after param
			if n.nType == param && c == '/' && len(n.children) == 1 {
				parentFullPathIndex += len(n.path)
				n = n.children[0]
				n.priority++
				continue walk
			}

			// Check if a child with the next path byte exists
			// 如果后续还有其他路径，根据首个字符indices的索引，跳转到相应的子节点继续处理
			for i, max := 0, len(n.indices); i < max; i++ {
				if c == n.indices[i] {
					parentFullPathIndex += len(n.path)
					i = n.incrementChildPrio(i)
					n = n.children[i]
					// 跳转walk位置
					continue walk
				}
			}

			// Otherwise insert it
			// 这里都是处理":"以及"*"的相关逻辑
			if c != ':' && c != '*' && n.nType != catchAll {
				// []byte for proper unicode char conversion, see #65
				n.indices += bytesconv.BytesToString([]byte{c})
				child := &node{
					fullPath: fullPath,
				}
				n.addChild(child)
				n.incrementChildPrio(len(n.indices) - 1)
				n = child
			} else if n.wildChild {
				// inserting a wildcard node, need to check if it conflicts with the existing wildcard
				n = n.children[len(n.children)-1]
				n.priority++

				// Check if the wildcard matches
				if len(path) >= len(n.path) && n.path == path[:len(n.path)] &&
					// Adding a child to a catchAll is not possible
					n.nType != catchAll &&
					// Check for longer wildcard, e.g. :name and :names
					(len(n.path) >= len(path) || path[len(n.path)] == '/') {
					continue walk
				}

				// Wildcard conflict
				pathSeg := path
				if n.nType != catchAll {
					pathSeg = strings.SplitN(pathSeg, "/", 2)[0]
				}
				prefix := fullPath[:strings.Index(fullPath, pathSeg)] + n.path
				panic("'" + pathSeg +
					"' in new path '" + fullPath +
					"' conflicts with existing wildcard '" + n.path +
					"' in existing prefix '" + prefix +
					"'")
			}

			n.insertChild(path, fullPath, handlers)
			return
		}

		// Otherwise add handle to current node
		// 如果在当前节点上重复追加处理方法，则有问题 pinc掉
		if n.handlers != nil {
			panic("handlers are already registered for path '" + fullPath + "'")
		}
		n.handlers = handlers
		n.fullPath = fullPath
		return
	}
}

```

### 4. Gin框架中间件原理实现

我们前面看到的 路由树节点上的处理函数（handlers HandlersChain），一般来说，除了最后一个函数，前面的函数被称为中间件。

这个与之前的中间件注册的功能匹配上了。

#### 4.1 中间件的执行

业务处理函数的执行，框架是通过 Next方法来执行的（整体的洋葱模型主要是因为触发 next会调用下一个处理函数. 调用按照顺序，但是在Next之后程序段的执行是个反顺序的过程 ）

```
// Next should be used only inside middleware.
// It executes the pending handlers in the chain inside the calling handler.
// See example in GitHub.
func (c *Context) Next() {
    // 增加一个索引，然后执行方法，(初始化的时候，index会设置成 -1 )
    // 所以首次执行处理是正常的，这样的话会一直执行到最后一个处理函数
	c.index++
	for c.index < int8(len(c.handlers)) {
	    // 执行方法
		c.handlers[c.index](c)
		// 执行之后增加所以调用下一个处理方法
		// 该方法添加之后，即便中间件中不加next方法，也会在执行完之后，继续执行下一个中间件（相当于是省略的c.Next被加在最后了）
		c.index++
	}
}

// 中间件的abort
// for this request are not called.
func (c *Context) Abort() {
    // 直接把index执行函数索引放到最大值，这样跳过所有后面的处理函数
	c.index = abortIndex
}

```

### 5. 其他关联

context 的 生成使用，其中涉及到 sync.Pool, 对象池，如果有兴趣的话，可以自行去了解