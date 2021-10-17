栈
==

### 1. 单调栈

单调栈是一种特殊的栈。 单调栈要求栈中的元素是单调递增或者单调递减的。 在我们的应用中，是否严格递增或递减可以根据实际情况来。

```
假如这里我用 [a,b,c] 表示一个栈。 其中 左侧为栈底，右侧为栈顶。
则：
[1,2,3,4] 就是一个单调递增栈
[3,2,1] 就是一个单调递减栈
[1,3,2] 就不是一个合法的单调栈
```

#### 应用场景

以下是自己PHP解答的算法
```php
// 参考一个遇到的题目：
// 从指定十进制数组中去掉m个数字，希望留下的结果最大 ( 不考虑0的问题 )。
// 例如：指定 345271 ，m=1 res=45271， m=2 res=5271
// 单调栈的方式解决该问题

function get_filtered_num($arr, $n)
{
    $len = count($arr);
    if ($n >= $len) {
        return [];
    }
    // 数据栈初始化，索引0元素压入（后续都和栈对比），单调递减栈（在未达到上限的时候是单调递增栈）
    $stack = []; // 栈空间
    array_push($stack, $arr[0]); // 压栈
    $top = 0; // 栈顶
    $dealt = 0; // 已经处理了几个

    for ($i = 1; $i < $len; $i++) {
        // 满足条件的时候，数据和栈内元素对比，如果大，那就推出栈内元素，如果小，跳过元素
        $needPush = true;
        while ($dealt < $n && $top >= 0) {
            if ($arr[$i] <= $stack[$top]) {
                // 如果当前元素比栈顶元素小，直接压入到结果中
                $needPush = true;
                break;
            } else {
                // 如果当前元素比栈顶元素大, 将栈顶元素弹出, 进行下一轮的对比
                array_pop($stack);
                $top--;
                $dealt++;
                $needPush = false;
                // 但是处理到数据上限的时候，需要将剩余结果压入
                if ($dealt == $n || $top < 0) {
                    $needPush = true;
                }
            }
        }
        if ($needPush) {
            array_push($stack, $arr[$i]);
            $top++;
        }
    }
    return $stack;
}
```

另外，力扣上一个经典题目题解可以学习：
- [柱状图中最大的矩形](https://leetcode-cn.com/problems/largest-rectangle-in-histogram/solution/bao-li-jie-fa-zhan-by-liweiwei1419/)