HTTPS协议
==


## 基本描述

HTTPS 对HTTP协议传输的数据进行非对称的加解密
加解密采用了 SSL/TLS协议


## SSL/TLS协议

#### SSL连接的具体流程
- 客户机c连接s时，他会向服务器发送一个28B的随机值 nc
- 服务器用它自己的值ns，加上它的证书，来响应客户机
- 客户机确认CA证书有效，服务器就证明了自己身份
- 接着客户端会产生一个随机的46B的预先控制秘密 pms，并向服务器发送 cpms（加密的pms）
- 服务器根据 cpms恢复得到 pms
- 现在客户端和服务器都有了 nc，ns，和pms，他们可以各自计算出一个48B的共享控制秘密 ms（这是一个未传输过的新值）
- ms可以用来计算： 双向传输加密消息的对称加密密钥、双向验证MAC产生密钥
- 之后客户机向服务器发送消息m时，他发送的是 加密算法加密过的数据c
- 服务器在接受消息 c时，它将会恢复被加密的内容
- 如果MAC验证通过，服务器就接受消息m


#### TLS协议版本
TLS（Transport Layer Security 安全传输层协议）是工业标准的SSL协议

- TLS 1.2
- TLS 1.3

## HTTPS

#### HTTPS握手过程

##### 1. Client Hello
握手第一步是客户端向服务端发送 Client Hello 消息，这个消息里包含了一个客户端生成的随机数 Random1、客户端支持的加密套件（Support Ciphers）和 SSL Version 等信息。

##### 2. Server Hello
第二步是服务端向客户端发送 Server Hello 消息，这个消息会从 Client Hello 传过来的 Support Ciphers 里确定一份加密套件，这个套件决定了后续加密和生成摘要时具体使用哪些算法，
另外还会生成一份随机数 Random2。注意，至此客户端和服务端都拥有了两个随机数（Random1+ Random2），这两个随机数会在后续生成对称秘钥时用到。

##### 3. Certificate
这一步是服务端将自己的证书下发给客户端，让客户端验证自己的身份，客户端验证通过后取出证书中的公钥。

##### 4. Certificate Verify
客户端收到服务端传来的证书后，先从 CA 验证该证书的合法性，验证通过后取出证书中的服务端公钥，再生成一个随机数 Random3，再用服务端公钥非对称加密 Random3 生成 PreMaster Key。

##### 5. Client Key Exchange
上面客户端根据服务器传来的公钥生成了 PreMaster Key，Client Key Exchange 就是将这个 key 传给服务端，服务端再用自己的私钥解出这个 PreMaster Key 得到客户端生成的 Random3。
至此，客户端和服务端都拥有 Random1 + Random2 + Random3，两边再根据同样的算法就可以生成一份秘钥，握手结束后的应用层数据都是使用这个秘钥进行对称加密。
为什么要使用三个随机数呢？这是因为 SSL/TLS 握手过程的数据都是明文传输的，并且多个随机数种子来生成秘钥不容易被暴力破解出来。客户端将 PreMaster Key 传给服务端的过程如下图所示：

##### 6. Encrypted Handshake Message(Client)
这一步对应的是 Client Finish 消息，客户端将前面的握手消息生成摘要再用协商好的秘钥加密，这是客户端发出的第一条加密消息。服务端接收后会用秘钥解密，能解出来说明前面协商出来的秘钥是一致的。

##### 7. Change Cipher Spec(Server)
这一步是服务端通知客户端后面再发送的消息都会使用加密，也是一条事件消息。

##### 8. Encrypted Handshake Message(Server)
这一步对应的是 Server Finish 消息，服务端也会将握手过程的消息生成摘要再用秘钥加密，这是服务端发出的第一条加密消息。客户端接收后会用秘钥解密，能解出来说明协商的秘钥是一致的。

##### 9. Application Data
到这里，双方已安全地协商出了同一份秘钥，所有的应用层数据都会用这个秘钥加密后再通过 TCP 进行可靠传输。


#### HTTPS的性能问题

- 比HTTP协议多出一个SSL握手的过程
- - 这其中CA证书验证还需要和CA网站握手交互
- - 如果无CA证书域名解析对应的IP缓存，则还需要一次DNS解析

##### HTTPS握手优化
如果每次重连都要重新握手还是比较耗时的，所以可以对握手过程进行优化。
Client Hello 消息里还附带了上一次的 Session ID，
服务端接收到这个 Session ID 后如果能复用就不再进行后续的握手过程。

