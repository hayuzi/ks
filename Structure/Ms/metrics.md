指标采集与监控
==

目前微服务最常用的指标监控使用的 Prometheus

### 1. Prometheus 四个核心指标类型

- 官方文档： https://prometheus.io/docs/concepts/metric_types/
- 具体的一些使用介绍可以参考一下这个文档： https://zhuanlan.zhihu.com/p/422395448

#### Counter 计数器(单调递增)

计数器是一个累积度量,它代表一个单调递增计数器,其值只能增加或重启被重置为零。 可以使用一个计数器来表示请求的数量,任务完成,或错误。

当服务进程重新启动的时候，counter 指标值会被重置为 0，不过不用担心数据错乱，我们一般会使用的 rate() 函数会自动处理。

#### Gauges 测量仪(可增可减)

衡量指标,代表一个单一的数值,可以任意上下。

该指标通常用于测量值如温度或当前内存使用量,而且“计数”,可以向上和向下,像并发请求的数量。

#### Histogram 直方图（难以理解的请搜索 直方图 📊）

直方图对观察值（通常是请求持续时间或响应大小）进行采样，并在可配置的存储桶中对其进行计数。这是一个二维的指标。 它还提供了所有观察值的总和。

Prometheus 提供的直方图是累积的，每一个后续的Bucket都包含前一个Bucket的观察计数, 所有Bucket下限都是从0开始，但是上限需要指定...

直方图类型的 Observe方法会自动将采集数据归类到相关的桶

#### Summary 摘要

与直方图类似，摘要对观察结果进行采样（通常是请求持续时间和响应大小）。虽然它还提供了观察的总数和所有观察值的总和，但它计算了滑动时间窗口上的可配置分位数。

该指标主要统计分位数

### 2. 指标标签

标签旨在在基本核心指标类型的基础上，构建带有标签的更加定制化的指标采集。

>  ⚠️ 注意：当使用带有标签维度的指标时，任何标签组合的时间序列只有在该标签组合被访问过至少一次后才会出现在 /metrics 输出中，这对我们在 PromQL 查询的时候会产生一些问题，因为它希望某些时间序列一直存在，我们可以在程序第一次启动时，将所有重要的标签组合预先初始化为默认值。 


### 3. 以下以在Go语言的 Gin框架http项目中注入指标采集为例，做简单的介绍

在项目中的metrics包中增加如下文件，使用自定义的注册表来采集相关信息


```go
package metrics

import (
	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/collectors"
)

var (
	// 创建一个自定义的注册表
	registry = prometheus.NewRegistry()
)

func init() {
	// 注册基础的Go运行时等通用指标
	InitRegisterCommonCollector()
	// 注册Gin的http信息指标
	InitRegisterGinHttpCollector()
	// TODO 可以注册其他的指标需要自己去编制 (然后通过中间件、切面或装饰器等方式将指标采集起来)
	// ......
	// ......
	// ......
}

func InitRegisterCommonCollector() {
	// 可选: 添加 process 和 Go 运行时指标到我们自定义的注册表中
	registry.MustRegister(collectors.NewProcessCollector(collectors.ProcessCollectorOpts{}))
	registry.MustRegister(collectors.NewGoCollector())
}

```

可以另外再开一个文件，编写专门针对gin框架的相关代码
```go
package metrics

import (
	"fmt"
	"github.com/gin-gonic/gin"
	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	"net/http"
	"strconv"
	"time"
)

const (
	DefaultMetricsPath = "/metrics"
	NameSpaceGin       = "gin"
)

var (
	reqCnt = prometheus.NewCounterVec(prometheus.CounterOpts{
		Namespace:   NameSpaceGin,
		Name:        "request_total",
		Help:        "gin http request total",
		ConstLabels: prometheus.Labels{},
	}, []string{"method", "host", "url", "handler", "status"})
	reqDur = prometheus.NewSummaryVec(prometheus.SummaryOpts{
		Namespace:   NameSpaceGin,
		Name:        "request_duration_seconds",
		Help:        "gin http request latencies in seconds",
		ConstLabels: prometheus.Labels{},
		Objectives: map[float64]float64{
			0.95: 0.005, // 第90个百分位数，最大绝对误差为0.005。
			0.99: 0.001, // 第90个百分位数，最大绝对误差为0.001。
		},
	}, []string{"method", "host", "url", "handler", "status"})
	reqSz = prometheus.NewSummaryVec(prometheus.SummaryOpts{
		Namespace:   NameSpaceGin,
		Name:        "request_size_bytes",
		Help:        "gin http request size in bytes",
		ConstLabels: prometheus.Labels{},
		Objectives: map[float64]float64{
			0.95: 0.005,
			0.99: 0.001,
		},
	}, []string{"method", "host", "url", "handler", "status"})
	resSz = prometheus.NewSummaryVec(prometheus.SummaryOpts{
		Namespace:   NameSpaceGin,
		Name:        "response_size_bytes",
		Help:        "gin http response size in bytes",
		ConstLabels: prometheus.Labels{},
		Objectives: map[float64]float64{
			0.95: 0.005, // 第90个百分位数，最大绝对误差为0.005。
			0.99: 0.001, // 第90个百分位数，最大绝对误差为0.001。
		},
	}, []string{"method", "host", "url", "handler", "status"})
)

func InitRegisterGinHttpCollector() {
	// 在默认的注册表中注册以上的指标
	registry.MustRegister(reqCnt)
	registry.MustRegister(reqDur)
	registry.MustRegister(reqSz)
	registry.MustRegister(resSz)
}

// GetGinMetricsServerHandler metrics 服务指标暴露采集接口
func GetGinMetricsServerHandler() gin.HandlerFunc {
	return gin.WrapH(promhttp.HandlerFor(registry, promhttp.HandlerOpts{Registry: registry}))
}

// GinMetricsMiddleware 本身的 Metrics 中间件，统计Gin请求的相关信息，全局注入
func GinMetricsMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		// 如果请求Path的本身是Metrics，跳过
		if c.Request.URL.Path == DefaultMetricsPath {
			c.Next()
			return
		}
		// 业务执行之前的数据
		start := time.Now()
		requestSz := float64(computeApproximateRequestSize(c.Request))

		// 执行方法
		c.Next()

		// 业务执行之后的数据
		status := strconv.Itoa(c.Writer.Status())                    // http 状态码
		elapsed := float64(time.Since(start)) / float64(time.Second) // 执行耗时
		responseSz := float64(c.Writer.Size())                       // 返回值大小

		url := fmt.Sprintf("[%s]%s", c.Request.Method, c.FullPath())
		reqCnt.WithLabelValues(c.Request.Method, c.Request.Host, url, c.HandlerName(), status).Inc()
		reqDur.WithLabelValues(c.Request.Method, c.Request.Host, url, c.HandlerName(), status).Observe(elapsed)
		reqSz.WithLabelValues(c.Request.Method, c.Request.Host, url, c.HandlerName(), status).Observe(requestSz)
		resSz.WithLabelValues(c.Request.Method, c.Request.Host, url, c.HandlerName(), status).Observe(responseSz)
	}
}

func computeApproximateRequestSize(r *http.Request) int {
	s := 0
	if r.URL != nil {
		s = len(r.URL.Path)
	}
	s += len(r.Method)
	s += len(r.Proto)
	for name, values := range r.Header {
		s += len(name)
		for _, value := range values {
			s += len(value)
		}
	}
	s += len(r.Host)
	if r.ContentLength != -1 {
		s += int(r.ContentLength)
	}
	return s
}

```

最后需要在项目路由中注入全局中间件，并在服务路由中添加暴露指标信息的接口
```go
package router

import (
	"github.com/gin-gonic/gin"
	"github.com/yournamespace/projectname/pkg/metrics"
)

func NewRouter() *gin.Engine {
	r := gin.New()
	r.Use(gin.Logger())
	r.Use(gin.Recovery())
	r.Use(metrics.GinMetricsMiddleware())
	
	// 暴露自定义指标
	r.GET("/metrics", metrics.GetGinMetricsServerHandler())
	
	return r
}

```


采集到的结果大概
```
# HELP gin_request_duration_seconds gin http request latencies in seconds
# TYPE gin_request_duration_seconds summary
gin_request_duration_seconds{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles",quantile="0.95"} NaN
gin_request_duration_seconds{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles",quantile="0.99"} NaN
gin_request_duration_seconds_sum{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles"} 0.036726997
gin_request_duration_seconds_count{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles"} 5
gin_request_duration_seconds{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags",quantile="0.95"} 0.005141493
gin_request_duration_seconds{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags",quantile="0.99"} 0.005141493
gin_request_duration_seconds_sum{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags"} 0.059446474000000006
gin_request_duration_seconds_count{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags"} 15
gin_request_duration_seconds{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]",quantile="0.95"} NaN
gin_request_duration_seconds{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]",quantile="0.99"} NaN
gin_request_duration_seconds_sum{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]"} 1.7326e-05
gin_request_duration_seconds_count{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]"} 2
# HELP gin_request_size_bytes gin http request size in bytes
# TYPE gin_request_size_bytes summary
gin_request_size_bytes{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles",quantile="0.95"} NaN
gin_request_size_bytes{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles",quantile="0.99"} NaN
gin_request_size_bytes_sum{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles"} 910
gin_request_size_bytes_count{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles"} 5
gin_request_size_bytes{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags",quantile="0.95"} 178
gin_request_size_bytes{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags",quantile="0.99"} 178
gin_request_size_bytes_sum{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags"} 2670
gin_request_size_bytes_count{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags"} 15
gin_request_size_bytes{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]",quantile="0.95"} NaN
gin_request_size_bytes{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]",quantile="0.99"} NaN
gin_request_size_bytes_sum{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]"} 334
gin_request_size_bytes_count{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]"} 2
# HELP gin_request_total gin http request total
# TYPE gin_request_total counter
gin_request_total{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles"} 5
gin_request_total{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags"} 15
gin_request_total{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]"} 2
# HELP gin_response_size_bytes gin http response size in bytes
# TYPE gin_response_size_bytes summary
gin_response_size_bytes{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles",quantile="0.95"} NaN
gin_response_size_bytes{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles",quantile="0.99"} NaN
gin_response_size_bytes_sum{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles"} 5150
gin_response_size_bytes_count{handler="github.com/yournamespace/project/internal/controller/api/v1.Article.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/articles"} 5
gin_response_size_bytes{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags",quantile="0.95"} 444
gin_response_size_bytes{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags",quantile="0.99"} 444
gin_response_size_bytes_sum{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags"} 6660
gin_response_size_bytes_count{handler="github.com/yournamespace/project/internal/controller/api/v1.Tag.List-fm",host="127.0.0.1:8081",method="GET",status="200",url="[GET]/api/v1/tags"} 15
gin_response_size_bytes{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]",quantile="0.95"} NaN
gin_response_size_bytes{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]",quantile="0.99"} NaN
gin_response_size_bytes_sum{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]"} -2
gin_response_size_bytes_count{handler="github.com/yournamespace/project/internal/midddleware.JWTInjectClaims.func1",host="127.0.0.1:8081",method="GET",status="404",url="[GET]"} 2
# HELP go_gc_duration_seconds A summary of the pause duration of garbage collection cycles.
# TYPE go_gc_duration_seconds summary
go_gc_duration_seconds{quantile="0"} 6.7608e-05
go_gc_duration_seconds{quantile="0.25"} 8.9621e-05
go_gc_duration_seconds{quantile="0.5"} 0.000130448
go_gc_duration_seconds{quantile="0.75"} 0.000163406
go_gc_duration_seconds{quantile="1"} 0.000212941
go_gc_duration_seconds_sum 0.0018175
go_gc_duration_seconds_count 14
# HELP go_goroutines Number of goroutines that currently exist.
# TYPE go_goroutines gauge
go_goroutines 15
# HELP go_info Information about the Go environment.
# TYPE go_info gauge
go_info{version="go1.16.4"} 1
# HELP go_memstats_alloc_bytes Number of bytes allocated and still in use.
# TYPE go_memstats_alloc_bytes gauge
go_memstats_alloc_bytes 4.59216e+06
# HELP go_memstats_alloc_bytes_total Total number of bytes allocated, even if freed.
# TYPE go_memstats_alloc_bytes_total counter
go_memstats_alloc_bytes_total 1.270612e+07
# HELP go_memstats_buck_hash_sys_bytes Number of bytes used by the profiling bucket hash table.
# TYPE go_memstats_buck_hash_sys_bytes gauge
go_memstats_buck_hash_sys_bytes 1.450166e+06
# HELP go_memstats_frees_total Total number of frees.
# TYPE go_memstats_frees_total counter
go_memstats_frees_total 42358
# HELP go_memstats_gc_cpu_fraction The fraction of this program's available CPU time used by the GC since the program started.
# TYPE go_memstats_gc_cpu_fraction gauge
go_memstats_gc_cpu_fraction 3.8424192575004e-06
# HELP go_memstats_gc_sys_bytes Number of bytes used for garbage collection system metadata.
# TYPE go_memstats_gc_sys_bytes gauge
go_memstats_gc_sys_bytes 5.2422e+06
# HELP go_memstats_heap_alloc_bytes Number of heap bytes allocated and still in use.
# TYPE go_memstats_heap_alloc_bytes gauge
go_memstats_heap_alloc_bytes 4.59216e+06
# HELP go_memstats_heap_idle_bytes Number of heap bytes waiting to be used.
# TYPE go_memstats_heap_idle_bytes gauge
go_memstats_heap_idle_bytes 5.951488e+07
# HELP go_memstats_heap_inuse_bytes Number of heap bytes that are in use.
# TYPE go_memstats_heap_inuse_bytes gauge
go_memstats_heap_inuse_bytes 6.938624e+06
# HELP go_memstats_heap_objects Number of allocated objects.
# TYPE go_memstats_heap_objects gauge
go_memstats_heap_objects 13133
# HELP go_memstats_heap_released_bytes Number of heap bytes released to OS.
# TYPE go_memstats_heap_released_bytes gauge
go_memstats_heap_released_bytes 5.9097088e+07
# HELP go_memstats_heap_sys_bytes Number of heap bytes obtained from system.
# TYPE go_memstats_heap_sys_bytes gauge
go_memstats_heap_sys_bytes 6.6453504e+07
# HELP go_memstats_last_gc_time_seconds Number of seconds since 1970 of last garbage collection.
# TYPE go_memstats_last_gc_time_seconds gauge
go_memstats_last_gc_time_seconds 1.660192845414908e+09
# HELP go_memstats_lookups_total Total number of pointer lookups.
# TYPE go_memstats_lookups_total counter
go_memstats_lookups_total 0
# HELP go_memstats_mallocs_total Total number of mallocs.
# TYPE go_memstats_mallocs_total counter
go_memstats_mallocs_total 55491
# HELP go_memstats_mcache_inuse_bytes Number of bytes in use by mcache structures.
# TYPE go_memstats_mcache_inuse_bytes gauge
go_memstats_mcache_inuse_bytes 9600
# HELP go_memstats_mcache_sys_bytes Number of bytes used for mcache structures obtained from system.
# TYPE go_memstats_mcache_sys_bytes gauge
go_memstats_mcache_sys_bytes 16384
# HELP go_memstats_mspan_inuse_bytes Number of bytes in use by mspan structures.
# TYPE go_memstats_mspan_inuse_bytes gauge
go_memstats_mspan_inuse_bytes 123488
# HELP go_memstats_mspan_sys_bytes Number of bytes used for mspan structures obtained from system.
# TYPE go_memstats_mspan_sys_bytes gauge
go_memstats_mspan_sys_bytes 131072
# HELP go_memstats_next_gc_bytes Number of heap bytes when next garbage collection will take place.
# TYPE go_memstats_next_gc_bytes gauge
go_memstats_next_gc_bytes 7.142352e+06
# HELP go_memstats_other_sys_bytes Number of bytes used for other system allocations.
# TYPE go_memstats_other_sys_bytes gauge
go_memstats_other_sys_bytes 1.893882e+06
# HELP go_memstats_stack_inuse_bytes Number of bytes in use by the stack allocator.
# TYPE go_memstats_stack_inuse_bytes gauge
go_memstats_stack_inuse_bytes 655360
# HELP go_memstats_stack_sys_bytes Number of bytes obtained from system for stack allocator.
# TYPE go_memstats_stack_sys_bytes gauge
go_memstats_stack_sys_bytes 655360
# HELP go_memstats_sys_bytes Number of bytes obtained from system.
# TYPE go_memstats_sys_bytes gauge
go_memstats_sys_bytes 7.5842568e+07
# HELP go_threads Number of OS threads created.
# TYPE go_threads gauge
go_threads 13
# HELP promhttp_metric_handler_errors_total Total number of internal errors encountered by the promhttp metric handler.
# TYPE promhttp_metric_handler_errors_total counter
promhttp_metric_handler_errors_total{cause="encoding"} 0
promhttp_metric_handler_errors_total{cause="gathering"} 0

```