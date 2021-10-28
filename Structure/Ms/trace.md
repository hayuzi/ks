分布式链路追踪
==

### 1. 简介

在我们项目服务日益增多或者业务代码日益庞大的场景下， 鉴于业务逻辑中各种调用的复杂性，我们有很大的必要对业务的整体链路以及每个步骤的相关瓶颈做分析。 由此而产生了调用链追踪的需求。

分布式链路追踪系统，一般来说，需要将分散的数据通过采集聚合，提升整体链路 路径、时序、时长 的可观察性

为了兼容不同供应商的分布式追踪系统API互不兼容的问题，出现了 OpenTracing规范。OpenTracing规范提供了一个标准的、与供应商无关的工具框架。

### 2. 通用规范 OpenTracing

- opentracing官方文档请自行搜索，目前 CNCF 整合 OpenCensus、OpenTracing 两个项目，并合并为 OpenTelemetry
- opentracing文档中文版 https://wu-sheng.gitbooks.io/opentracing-io/content/
- 开放分布式追踪（OpenTracing）入门与Jaeger实现  https://zhuanlan.zhihu.com/p/34318538

### 3. 常用软件

常用的分布式链路追踪系统：

- Dapper(Google) : 各 tracer 的基础
- StackDriver Trace (Google)
- Zipkin(twitter)
- AppDash(golang)
- Jaeger (Go开发 CNCF管理)
- sTrace(神马)
- X-ray(aws)

阿里系列的：

- 鹰眼(taobao)
- 谛听(盘古，阿里云云产品使用的Trace系统)
- 云图(蚂蚁Trace系统)

