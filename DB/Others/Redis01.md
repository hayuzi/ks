Redis数据类型以及其底层结构
==



Redis支持5种对象类型，分别是字符串(string)、列表(list)、哈希(hash)、集合(set)、有序集合(zset)，redisObject使用type字段记录自身属于哪种类型。

而每种对象类型至少使用了两种底层数据结构来实现，redisObject使用编码字段（encoding字段）记录了自己使用的是哪种底层数据结构实现。
而*ptr指针则会直接指向这个对应的底层数据结构。

每个对象会用到的编码以及对应的数据结构详见下表，即共8种底层数据结构：

```
对象                  编码        数据结构
string 字符串对象        int       可以存储long类型的整数
                        embstr    embstr编码的简单动态字符串
                        raw       简单动态字符串（SDS）

list 列表对象           ziplist     压缩列表
                    linkedlist    双端链表

hansh 哈希对象          ziplist     压缩列表
                    hashtable       字典

set 结合对象            intset      整数集合
                    hashtable       字典

zset 有序集合对象        ziplist      压缩列表
                    skiplist       字典加跳表                    

```

> Redis中的键，都是用字符串对象来存储的，即对于Redis数据库中的键值对来说，键总是一个字符串对象，而值可以是字符串对象、列表对象、哈希对象、集合对象或者有序集合对象中的其中一种


## 1. string类型