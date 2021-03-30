Redis 内部数据结构
==

## 1. 整数

如果保存的字符串是整数值，并且这个整数值可以用long类型来表示，那么ptr指针的void*则转化为C语言源生的long类型。


## 2. 简单动态字符串（SDS）

### SDS的实现、优势、以及与C语言的区别

##### SDS的结构如下：
```
// 分别有      sdshdr8、sdshdr16、sdshdr32、sdshdr64
// 此处只展示   sdshdr32

struct sdshdr64 {
    // 记录 buf 数组中已使用字节的数量
    // 等于 SDS 所保存字符串的长度
    // len分别以uint8, uint16, uint32, uint64表示用户数据的长度(不包括末尾的\0)
    uint64_t len; /* used */ 
    
    // 记录 buf 数组中未使用字节的数量
    // alloc分别以uint8, uint16, uint32, uint64表示整个SDS, 除过头部与末尾的\0, 剩余的字节
    uint64_t alloc; /* excluding the header and null terminator */ 
    
    flag始终为一字节, 以低三位标示着头部的类型, 高5位未使用.
    unsigned char flags; /* 3 lsb of type, 5 unused bits */ 
    
    // 字节数组，用于保存字符串
    char buf[];
};

```
##### SDS的优势
- 常数复杂度获取字符串长度
- 杜绝缓冲区溢出
- 减少修改字符串时候带来的内存分配次数
- - 未使用空间的存在，使得SDS实现了空间与分配和惰性空间释放两种优化策略
- 二进制安全（使用len属性而不是空字符判断是否结束）
- 兼容部分C字符串函数（实际值的部分存储使用了C语言"\0"结尾字符数组表示）


##### C字符串和 SDS之间的区别

C 字符串 | SDS
---|---
获取字符串长度的复杂度为 O(N)                  | 获取字符串长度的复杂度为 O(1)
API 是不安全的，可能会造成缓冲区溢出             | API 是安全的，不会造成缓冲区溢出
修改字符串长度 N 次必然需要执行 N 次内存重分配	   | 修改字符串长度 N 次最多需要执行 N 次内存重分配
只能保存文本数据                              | 可以保存文本或者二进制数据
可以使用所有 <string.h> 库中的函数             | 可以使用一部分 <string.h> 库中的函数


##### SDS API
```
函数	        作用	                                        时间复杂度
sdsnew	    创建一个包含给定 C 字符串的 SDS 。	            O(N) ， N 为给定 C 字符串的长度。
sdsempty	创建一个不包含任何内容的空 SDS 。         	    O(1)
sdsfree     释放给定的 SDS 。	                            O(1)
sdslen	    返回 SDS 的已使用空间字节数。	                这个值可以通过读取 SDS 的 len 属性来直接获得， 复杂度为 O(1) 。
sdsavail	返回 SDS 的未使用空间字节数。	                这个值可以通过读取 SDS 的 free 属性来直接获得， 复杂度为 O(1) 。
sdsdup	    创建一个给定 SDS 的副本（copy）。	            O(N) ， N 为给定 SDS 的长度。
sdsclear	清空 SDS 保存的字符串内容。	                    因为惰性空间释放策略，复杂度为 O(1) 。
sdscat	    将给定 C 字符串拼接到 SDS 字符串的末尾。	        O(N) ， N 为被拼接 C 字符串的长度。
sdscatsds	将给定 SDS 字符串拼接到另一个 SDS 字符串的末尾。   O(N) ， N 为被拼接 SDS 字符串的长度。
sdscpy	    将给定的 C 字符串复制到 SDS 里面， 
            覆盖 SDS 原有的字符串。                      	O(N) ， N 为被复制 C 字符串的长度。
sdsgrowzero	用空字符将 SDS 扩展至给定长度。	                O(N) ， N 为扩展新增的字节数。
sdsrange	保留 SDS 给定区间内的数据， 
            不在区间内的数据会被覆盖或清除。	                O(N) ， N 为被保留数据的字节数。
sdstrim	    接受一个 SDS 和一个 C 字符串作为参数， 
            从 SDS 左右两端分别移除所有在 C 字符串中出现过的字符。 O(M*N) ， M 为 SDS 的长度， N 为给定 C 字符串的长度。
sdscmp	    对比两个 SDS 字符串是否相同。	                O(N) ， N 为两个 SDS 中较短的那个 SDS 的长度。
```



## 3. embstr

##### 简介
embstr编码是专门用来保存短字符串的一种优化编码方式，
其实他和raw编码一样，底层都会使用SDS，
只不过raw编码是调用两次内存分配函数分别创建redisObject和SDS，
而embstr只调用一次内存分配函数来分配一块连续的空间，
embstr编码的的redisObject和SDS是紧凑在一起的。 


##### 其优势是：
- embstr的创建只需分配一次内存，而raw为两次（一次为sds分配对象，另一次为objet分配对象，embstr省去了第一次）。
- 相对地，释放内存的次数也由两次变为一次。
- embstr的objet和sds放在一起，更好地利用缓存带来的优势。

不过很显然，紧凑型的方式只适合短字符串，长字符串占用空间太大，就没有优势了。


> 如果字符串对象保存的是一个字符串值， 并且这个字符串值的长度小于等于 39 字节， 那么字符串对象将使用 embstr 编码的方式来保存这个字符串值。否则采用raw编码的SDS来存储。
这在3.0以上版本的Redis出现。但是在3.2版本之后，这个分界变成了44

> 至于为什么是39?
  embstr是一块连续的内存区域，由redisObject和sdshdr组成。
  其中redisObject占16个字节，当buf内的字符串长度是39时，sdshdr的大小为8+39+1=48，那一个字节是'\0'。加起来刚好64。
  而在3.2版本之后，则变成了44字节为分界


## 4. 双端链表 linkedlist

##### 结构介绍
```
// C语言中没有内置链表结构，Redis构建了自己的链表实现。
// list的容量是2的32次方减1个元素，即最多有4294967295个元素数量。
// 每个链表节点使用一个 adlist.h/listNode 结构来表示：
typedef struct listNode {
    // 前置节点
    struct listNode *prev;
    // 后置节点
    struct listNode *next;
    // 节点的值
    void *value;
} listNode;

typedef struct list {
    // 表头节点
    listNode *head;
    // 表尾节点
    listNode *tail;
    // 链表所包含的节点数量
    unsigned long len;
    // 节点值复制函数
    void *(*dup)(void *ptr);
    // 节点值释放函数
    void (*free)(void *ptr);
    // 节点值对比函数
    int (*match)(void *ptr, void *key);
} list;
```
list 结构为链表提供了表头指针 head 、表尾指针 tail ， 以及链表长度计数器 len ， 而 dup 、 free 和 match 成员则是用于实现多态链表所需的类型特定函数：
- dup 函数用于复制链表节点所保存的值；
- free 函数用于释放链表节点所保存的值；
- match 函数则用于对比链表节点所保存的值和另一个输入值是否相等。


##### Redis 的链表实现的特性可以总结如下：
- 双端： 链表节点带有 prev 和 next 指针， 获取某个节点的前置节点和后置节点的复杂度都是 O(1) 。
- 无环： 表头节点的 prev 指针和表尾节点的 next 指针都指向 NULL ， 对链表的访问以 NULL 为终点。
- 带表头指针和表尾指针： 通过 list 结构的 head 指针和 tail 指针， 程序获取链表的表头节点和表尾节点的复杂度为 O(1) 。
- 带链表长度计数器： 程序使用 list 结构的 len 属性来对 list 持有的链表节点进行计数， 程序获取链表中节点数量的复杂度为 O(1) 。
- 多态： 链表节点使用 void* 指针来保存节点值， 并且可以通过 list 结构的 dup 、 free 、 match 三个属性为节点值设置类型特定函数， 所以链表可以用于保存各种不同类型的值。


##### 应用
- 链表被广泛用于实现 Redis 的各种功能， 比如列表键， 发布与订阅， 慢查询， 监视器， 等等。
- 每个链表节点由一个 listNode 结构来表示， 每个节点都有一个指向前置节点和后置节点的指针， 所以 Redis 的链表实现是双端链表。
- 每个链表使用一个 list 结构来表示， 这个结构带有表头节点指针、表尾节点指针、以及链表长度等信息。
- 因为链表表头节点的前置节点和表尾节点的后置节点都指向 NULL ， 所以 Redis 的链表实现是无环链表。
- 通过为链表设置不同的类型特定函数， Redis 的链表可以用于保存各种不同类型的值。



## 5. 字典

#### Redis定义了dictEntry、dictType、dictht和dict四个结构体来实现哈希表的功能。它们具体定义如下：
##### dictEntry结构体
```
/* 保存键值（key - value）对的结构体，类似于STL的pair。*/
typedef struct dictEntry {
    // 关键字key定义
    void *key;  
    // 值value定义，只能存放一个被选中的成员
    union {
        void *val;      
        uint64_t u64;   
        int64_t s64;    
        double d;       
    } v;
    // 指向下一个键值对节点
    struct dictEntry *next;
} dictEntry;
```

##### dictType结构体
```
/* 定义了字典操作的公共方法，类似于adlist.h文件中list的定义，将对节点的公共操作方法统一定义。搞不明白为什么要命名为dictType */
typedef struct dictType {
    /* hash方法，根据关键字计算哈希值 */
    unsigned int (*hashFunction)(const void *key);
    /* 复制key */
    void *(*keyDup)(void *privdata, const void *key);
    /* 复制value */
    void *(*valDup)(void *privdata, const void *obj);
    /* 关键字比较方法 */
    int (*keyCompare)(void *privdata, const void *key1, const void *key2);
    /* 销毁key */
    void (*keyDestructor)(void *privdata, void *key);
    /* 销毁value */
    void (*valDestructor)(void *privdata, void *obj);
} dictType;
```

##### dictht结构体
```
/* 哈希表结构 */
typedef struct dictht {
    // 散列数组。
    dictEntry **table;
    // 散列数组的长度
    unsigned long size;
    // sizemask等于size减1
    unsigned long sizemask;
    // 散列数组中已经被使用的节点数量
    unsigned long used;
} dictht;
```

##### dict结构体
```
/* 字典的主操作类，对dictht结构再次包装  */
typedef struct dict {
    // 字典类型
    dictType *type;
    // 私有数据
    void *privdata;
    // 一个字典中有两个哈希表
    dictht ht[2];
    //rehash的标记，rehashidx==-1，表示没在进行rehash
    long rehashidx; 
    // 当前正在使用的迭代器的数量
    int iterators; 
} dict;
```

![image](https://note.youdao.com/favicon.ico)

参考
 - Redis设计与实现 http://redisbook.com/
 - https://my.oschina.net/lscherish/blog/3145142