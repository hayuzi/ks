Gin框架原理分析
==

## 1. 简介

### 1.1 Gin框架的核心功能

Gin中有两个核心结构体，我们可以先分析一下

#### 1.1.1 基础引擎gin.Engine

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

#### 上下文信息gin.Context

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

### Gin框架使用时候的执行流程

我们通常会先通过封装router方法，来返回*gin.Engine, 并将绑定到给http.Server中的

```
// 初始化gin引擎， 并获取到返回的*gin.Engines
routers := gin.Default() 

// 紧接着我们那通过路由注册方法，将路由信息注册到路由树上（ 路由树形结构待分析 ）
routers.GET("/foo", func (c *gin.Context) {
    // test
})

// 启动服务，并将 *gin.Engine 作为服务的处理handler
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
    // 从全局的engine.pool中获取一个*Context
	c := engine.pool.Get().(*Context)
	// 将http返回结果的io具柄写入到Context中
	c.writermem.reset(w)
	// 将请求数据注入到 Contex的请求中
	c.Request = req
	// 重置Context的信息
	c.reset()

    // 处理业务流程.该方法内部有很多数据
	engine.handleHTTPRequest(c)
    
    // 用完的*Context放回到pool中
	engine.pool.Put(c)
}
```

流程梳理图:

### Gin框架的路由实现

### Gin框架中间件原理实现

### ...