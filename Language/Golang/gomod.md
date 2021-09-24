go mod 的一些使用问题
==

### 1 go mod  使用私有gitlab群组的解决办法

#### 1.1 设置git使用 ssh协议

设置git使用 ssh协议 以替换默认的https的拉取方式。 某些情况下私有仓库可能未设置https, 那么相应的 https改为http
```shell
git config --global url."git@your.domain.com:".insteadOf "https://your.domain.com/"
```

执行完以上命令之后，在 ～/.gitconfig 文件中会有如下信息
```

[url "git@your.domain.com:"]
        insteadOf = https://your.domain.com/

```

#### 1.2 设置private域名 与 设置noproxy域名

```shell
# 设置private域名 (1.17版本mac上面设置的时候两个变量会同时被更新)
go env -w GOPRIVATE=your.domain.com\*\*

# 设置noproxy域名
go env -w GONOPROXY=your.domain.com\*\*
```

#### 1.3 实际项目的 go.mod 文件修改

添加如下内容, 对项目的拉取路径做正确替换
```
replace your.domain.com/YourGroup/SubGroup/Project => your.domain.com/YourGroup/SubGroup/Project.git master

```

之后在项目根目录运行如下命令
```shell
go get pkg@version

# 或者 已经引入的时候
go mod dity
```

 


