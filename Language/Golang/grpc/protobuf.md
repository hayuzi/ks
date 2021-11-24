protobuf
==

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
