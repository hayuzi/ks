WebSocket协议

### 简介

WebSocket协议支持web浏览器与web服务器之间的交互,是一个全双工通信协议.

### WebSocket握手流程

我们截取了一次 websocket 连接的建立使用与关闭流程（ wireshark ）

```
89	1.943976	127.0.0.1	127.0.0.1	TCP	68	58901 → 2021 [SYN] Seq=0 Win=65535 Len=0 MSS=16344 WS=64 TSval=851165646 TSecr=0 SACK_PERM=1
90	1.944120	127.0.0.1	127.0.0.1	TCP	68	2021 → 58901 [SYN, ACK] Seq=0 Ack=1 Win=65535 Len=0 MSS=16344 WS=64 TSval=851165646 TSecr=851165646 SACK_PERM=1
91	1.944128	127.0.0.1	127.0.0.1	TCP	56	58901 → 2021 [ACK] Seq=1 Ack=1 Win=408256 Len=0 TSval=851165646 TSecr=851165646
92	1.944134	127.0.0.1	127.0.0.1	TCP	56	[TCP Window Update] 2021 → 58901 [ACK] Seq=1 Ack=1 Win=408256 Len=0 TSval=851165646 TSecr=851165646
93	1.944391	127.0.0.1	127.0.0.1	HTTP	368	GET /ws HTTP/1.1 
94	1.944404	127.0.0.1	127.0.0.1	TCP	56	2021 → 58901 [ACK] Seq=1 Ack=313 Win=407936 Len=0 TSval=851165646 TSecr=851165646
95	1.944571	127.0.0.1	127.0.0.1	HTTP	324	HTTP/1.1 101 Switching Protocols 
96	1.944581	127.0.0.1	127.0.0.1	TCP	56	58901 → 2021 [ACK] Seq=313 Ack=269 Win=408000 Len=0 TSval=851165646 TSecr=851165646
97	1.944824	127.0.0.1	127.0.0.1	WebSocket	93	WebSocket Continuation [FIN] [MASKED]
98	1.944834	127.0.0.1	127.0.0.1	TCP	56	2021 → 58901 [ACK] Seq=269 Ack=350 Win=407936 Len=0 TSval=851165647 TSecr=851165647
99	1.945229	127.0.0.1	127.0.0.1	WebSocket	85	WebSocket Continuation [FIN] 
100	1.945240	127.0.0.1	127.0.0.1	TCP	56	58901 → 2021 [ACK] Seq=350 Ack=298 Win=408000 Len=0 TSval=851165647 TSecr=851165647
101	1.945259	127.0.0.1	127.0.0.1	WebSocket	60	WebSocket Connection Close [FIN] 
102	1.945270	127.0.0.1	127.0.0.1	TCP	56	58901 → 2021 [ACK] Seq=350 Ack=302 Win=407936 Len=0 TSval=851165647 TSecr=851165647
103	1.945337	127.0.0.1	127.0.0.1	WebSocket	64	WebSocket Connection Close [FIN] [MASKED]
104	1.945346	127.0.0.1	127.0.0.1	TCP	56	2021 → 58901 [ACK] Seq=302 Ack=358 Win=407936 Len=0 TSval=851165647 TSecr=851165647
105	1.945396	127.0.0.1	127.0.0.1	TCP	56	58901 → 2021 [FIN, ACK] Seq=358 Ack=302 Win=407936 Len=0 TSval=851165647 TSecr=851165647
106	1.945409	127.0.0.1	127.0.0.1	TCP	56	2021 → 58901 [ACK] Seq=302 Ack=359 Win=407936 Len=0 TSval=851165647 TSecr=851165647
107	1.945435	127.0.0.1	127.0.0.1	TCP	56	2021 → 58901 [FIN, ACK] Seq=302 Ack=359 Win=407936 Len=0 TSval=851165647 TSecr=851165647
108	1.945460	127.0.0.1	127.0.0.1	TCP	56	58901 → 2021 [ACK] Seq=359 Ack=303 Win=407936 Len=0 TSval=851165647 TSecr=851165647
109	2.006821	::1	::1	TCP	88	58902 → 9229 [SYN] Seq=0 Win=65535 Len=0 MSS=16324 WS=64 TSval=851165708 TSecr=0 SACK_PERM=1
110	2.006841	::1	::1	TCP	64	9229 → 58902 [RST, ACK] Seq=1 Ack=1 Win=0 Len=0
```

具体描述如下:

```
- TCP握手   客户端 SYN
- TCP握手   服务端 SYN ACK
- TCP握手   客户端 ACK **成功建立TCP连接**
- TCP       服务端 窗口更新（跳过，改内容是TCP协议的滑动窗口问题）
- HTTP      客户端 发送HTTP/1.1请求的请求 并带上协议升级的请求头
    - Connection: Upgrade
    - Upgrade: websocket
    - Sec-WebSocket-Key: hj0eNqbhE/A0GkBXDRrYYw==
    - Sec-WebSocket-Version: 13
- TCP     服务端 ACK 接收到的HTTP请求数据
- HTTP    服务端 升级协议到 Websocket
    - 服务端会根据 Sec-WebSocket-Key 的数据，生成 Sec-Websocket-Accept 放在head中返回给客户端
    - **客户端收到服务端的这个请求之后，判断Sec-Websocket-Accept则 建立websocket连接**
- TCP       客户端 ACK 接收到的HTTP升级协议数据包
- WebSocket 客户端 (WebSocket Continuation) FIN MASKED 发送ws消息（客户端发送数据会进行掩码处理）
- TCP       服务端 ACK
- WebSocket 服务端 (WebSocket Continuation) FIN 发送ws消息
- TCP       客户端 ACK  
- WebSocket 客户端  (WebSocket Connection Close) FIN MASKED 关闭连接
- TCP       服务端 ACK
- WebSocket 服务端 (WebSocket Connection Close) FIN 关闭连接
- TCP       客户端 ACK  
- TCP挥手   客户端 FIN ACK
- TCP挥手   服务端 ACK
- TCP挥手   服务端 FIN ACK
- TCP挥手   客户端 ACK
```

### WebSocket协议的优点：

- 较少的控制开销
    - 连接建立后数据交换时，用于协议控制的数据包头部相对较小
    - 对比HTTP每次都要携带完整的头部，开销显著减少
- 更强的实时性
    - 全双工通信
    - 延迟更小
- 保持连接状态
- 更好的二进制支持
    - 二进制帧的支持
- 支持扩展
- 更好的压缩效果


