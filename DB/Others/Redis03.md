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

#### 字典的实现
Redis定义了dictEntry、dictType、dictht和dict四个结构体来实现哈希表的功能。它们具体定义如下：

##### dictht结构体（哈希表）
```
/* 哈希表结构 */
typedef struct dictht {
    // 哈希表数组
    // table 属性是一个数组， 数组中的每个元素都是一个指向 dict.h/dictEntry 结构的指针， 每个 dictEntry 结构保存着一个键值对
    dictEntry **table;
    
    // 散列数组的长度、哈希表大小
    unsigned long size;
    
    // 哈希表大小掩码，用于计算索引值
    // 总是等于 size - 1
    unsigned long sizemask;
    
    // 散列数组中已经被使用的节点数量
    unsigned long used;
    
} dictht;
```


##### dictEntry结构体（哈希表节点）

哈希表节点使用 dictEntry 结构表示， 每个 dictEntry 结构都保存着一个键值对：
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


##### dict结构体（字典）
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
type 属性和 privdata 属性是针对不同类型的键值对， 为创建多态字典而设置的：
- type 属性是一个指向 dictType 结构的指针， 每个 dictType 结构保存了一簇用于操作特定类型键值对的函数， Redis 会为用途不同的字典设置不同的类型特定函数。
- 而 privdata 属性则保存了需要传给那些类型特定函数的可选参数。

##### dictType结构体（类型特定）
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

#### 字典其他相关问题
##### 哈希算法

Redis提供了三种不同的散列函数，分别是：
- 使用Thomas Wang’s 32 bit Mix哈希算法，对一个整型进行哈希，该方法在dictIntHashFunction函数中实现。
- 使用MurmurHash2哈希算法对字符串进行哈希，该方法在dictGenHashFunction函数中实现。(当字典被用作数据库的底层实现，或者哈希键的底层实现时，Redis用MurmurHash2算法来计算哈希值，能产生32-bit或64-bit哈希值。)
- 在dictGenCaseHashFunction函数中提供了一种比较简单的djb哈希算法，对字符串进行哈希。（djb哈希算法，算法的思想是利用字符串中的ascii码值与一个随机seed，通过len次变换，得到最后的hash值。）

##### 哈希键冲突解决
从字典数据结构组织中可以是看出，redis采用的链地址法解决的hash键冲突


##### rehash
随着操作的不断执行， 哈希表保存的键值对会逐渐地增多或者减少， 为了让哈希表的负载因子（load factor）维持在一个合理的范围之内， 当哈希表保存的键值对数量太多或者太少时， 程序需要对哈希表的大小进行相应的扩展或者收缩。

当以下条件中的任意一个被满足时， 程序会自动开始对哈希表执行扩展操作：
- 服务器目前没有在执行 BGSAVE 命令或者 BGREWRITEAOF 命令， 并且哈希表的负载因子大于等于 1 ；
- 服务器目前正在执行 BGSAVE 命令或者 BGREWRITEAOF 命令， 并且哈希表的负载因子大于等于 5 ；

其中哈希表的负载因子可以通过公式：
```
# 负载因子 = 哈希表已保存节点数量 / 哈希表大小
load_factor = ht[0].used / ht[0].size
```

##### 渐进式 rehash
扩展或收缩哈希表需要将 ht[0] 里面的所有键值对 rehash 到 ht[1] 里面， 但是， 这个 rehash 动作并不是一次性、集中式地完成的， 而是分多次、渐进式地完成的。

如果字典结构中存储的数据量太大，一次性操作可能会导致服务器在一段时间内停止服务。
因此， 为了避免 rehash 对服务器性能造成影响， 服务器不是一次性将 ht[0] 里面的所有键值对全部 rehash 到 ht[1] ， 而是分多次、渐进式地将 ht[0] 里面的键值对慢慢地 rehash 到 ht[1] 。

因为在进行渐进式 rehash 的过程中， 字典会同时使用 ht[0] 和 ht[1] 两个哈希表， 所以在渐进式 rehash 进行期间， 字典的删除（delete）、查找（find）、更新（update）等操作会在两个哈希表上进行： 比如说， 要在字典里面查找一个键的话， 程序会先在 ht[0] 里面进行查找， 如果没找到的话， 就会继续到 ht[1] 里面进行查找， 诸如此类。

另外， 在渐进式 rehash 执行期间， 新添加到字典的键值对一律会被保存到 ht[1] 里面， 而 ht[0] 则不再进行任何添加操作： 这一措施保证了 ht[0] 包含的键值对数量会只减不增， 并随着 rehash 操作的执行而最终变成空表。


##### 


参考
 - Redis设计与实现 http://redisbook.com/
 - https://my.oschina.net/lscherish/blog/3145142