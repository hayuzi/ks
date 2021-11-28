protobuf
==

> 参考 《Go语言编程之旅》

### 简介

#### 对比其他IDL优缺点

优点：

- 性能好
    - 数据定义更简单、明了
    - 数据描述文件大小事另外两者的1/10甚至1/3
    - 解析速度是另外两者的20倍至100倍
    - 减少了二意性
    - 生成了更易使用的数据访问类
    - 序列化和反序列化速度更快
    - 在传输过程中开发者不需要过多的关注其内容
- 代码生成方便
- 流传输
    - gRPC通过 HTTP/2对流传输提供了大量的支持
        - 一元RPC
        - 服务端流式RPC
        - 客户端流式RPC
        - 双向流式RPC
- 超时和取消
    - 根据 Go的 Context 属性，层层调用来传播 截止和取消事件
      
缺点：
- 可读性差
- 不支持浏览器调用（gRPC-Web有限支持访问）
- 外部组件支持比较差

### Protobuf的安装

#### 安装protoc(protoc是Protobuf的编译器)

可以从这里 https://github.com/protocolbuffers/protobuf/releases 下载安装包

```shell

wget https://github.com/protocolbuffers/protobuf/releases/download/v3.19.1/protobuf-all-3.19.1.zip
unzip protobuf-all-3.19.1.zip && cd protobuf-3.19.1
./configure
sudo make && make install

protoc --version
```

#### 安装protoc插件

除了安装 protoc编译器，还需要安装不同语言的运行时的 protoc 插件

以Go语言为例子(20210123)

> https://grpc.io/docs/languages/go/quickstart/

```shell
go install google.golang.org/protobuf/cmd/protoc-gen-go@v1.26
go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@v1.1
```

写好proto文件，执行如下命令

```shell
protoc --go-out=. ./proto/*.proto
protoc --go-grpc_out=. ./proto/*.proto
```

另外使用Goland的Protocol Buffers插件的时候，如果import显示路径找不到可以在

配置中的 Languages & Frameworks -> Protocol Buffers 中配置路径
