Django项目注意事项
==

直接pip拉取代码库依赖会报错，此处记录前置处理

```shell
brew install mysql
```

修改环境变量： vim ~/.bash_profile

```
# 根据对应版本设置
export PATH="/usr/local/Cellar/mysql-client/8.0.23/bin:$PATH"
export LDFLAGS="-L/usr/local/Cellar/mysql-client/8.0.23/lib"
export CPPFLAGS="-I/usr/local/Cellar/mysql-client/8.0.23/include"
export LIBRARY_PATH="/usr/local/Cellar/openssl@1.1/1.1.1k/lib"
export CFLAGS="-D__x86_64__"
```

依然没能成功，更新了 Command Line Tools 到 11.5 版本, 之后执行成功（该步骤或许不是必须的）

```shell
生成requirements.txt文件
pip freeze > requirements.txt
安装requirements.txt依赖
pip install -r requirements.txt
```

python3提供了虚拟环境功能，使用brew命令安装 python3.6 并加载，之后在IDE配置venv为对应的版本，然后运行

```shell
brew install python@3.6 
```

运行django项目

```shell
python manage.py runserver
```