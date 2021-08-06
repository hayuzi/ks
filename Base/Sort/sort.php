<?php


/**
 * 1 冒泡排序 是一种简单的排序算法。 (基于交换)
 * 它重复地走访过要排序的数列，一依次比较两个元素，如果它们的顺序错误就把它们交换过来。
 * 走访数列的工作是重复地进行直到没有再需要交换，也就是说该数列已经排序完成。
 * 这个算法的名字由来是因为越小的元素会经由交换慢慢“浮”到数列的顶端
 *
 * @param array $arr
 * @return array
 */
function bubbleSort($arr)
{
    $len = count($arr);
    if ($len < 2) {
        return $arr;
    }
    for ($i = 0; $i < $len; $i++) {
        for ($j = 0; $j < $len - 1 - $i; $j++) {
            if ($arr[$j + 1] < $arr[$j]) {
                $tmp         = $arr[$j + 1];
                $arr[$j + 1] = $arr[$j];
                $arr[$j]     = $tmp;

            }
        }
    }
    return $arr;
}


/**
 * 2 选择排序 是表现最稳定的排序算法之一 ，因为无论什么数据进去都是O(n2)的时间复杂度 ，
 * 所以用到它的时候，数据规模越小越好。唯一的好处可能就是不占用额外的内存空间了吧。
 * 理论上讲，选择排序可能也是平时排序一般人想到的最多的排序方法了吧。
 *
 * @param $arr
 * @return mixed
 */
function selectionSort($arr)
{
    $len = count($arr);
    if ($len < 2) {
        return $arr;
    }
    for ($i = 0; $i < $len; $i++) {
        $minIndex = $i;
        for ($j = $i; $j < $len; $j++) {
            if ($arr[$j] < $arr[$minIndex]) {
                $minIndex = $j;
            }
        }
        $tmp            = $arr[$minIndex];
        $arr[$minIndex] = $arr[$i];
        $arr[$i]        = $tmp;

    }
    return $arr;
}


/**
 * 3 插入排序（Insertion-Sort）的算法描述是一种简单直观的排序算法
 * 它的工作原理是通过构建有序序列，对于未排序数据，在已排序序列中从后向前扫描，找到相应位置并插入。
 * 插入排序在实现上，通常采用in-place排序（即只需用到O(1)的额外空间的排序），
 * 因而在从后向前扫描过程中，需要反复把已排序元素逐步向后挪位，为最新元素提供插入空间。
 *
 * @param $arr
 * @return mixed
 */
function insertionSort($arr)
{
    $len = count($arr);
    if ($len < 2) {
        return $arr;
    }
    // $current = 0;
    for ($i = 1; $i < $len; $i++) {
        $current = $arr[$i];
        $preIdx  = $i - 1;
        // 对比之后因为要插入到当前对比开始位的最前面，所以得从后遍历一次移动位置
        while ($preIdx >= 0 && $current < $arr[$preIdx]) {
            $arr[$preIdx + 1] = $arr[$preIdx];
            $preIdx--;
        }
        $arr[$preIdx + 1] = $current;

    }
    return $arr;
}


/**
 * 4 希尔排序是希尔（Donald Shell）于1959年提出的一种排序算法。
 * 希尔排序也是一种插入排序，它是简单插入排序经过改进之后的一个更高效的版本，也称为缩小增量排序，同时该算法是冲破O(n2）的第一批算法之一。
 * 它与插入排序的不同之处在于，它会优先比较距离较远的元素。希尔排序又叫缩小增量排序。
 *
 * @param $arr
 * @return mixed
 */
function shellSort($arr)
{
    $len = count($arr);
    if ($len < 2) {
        return $arr;
    }
    $gap = floor($len / 2);
    while ($gap > 0) {
        for ($i = $gap; $i < $len; $i++) {
            $temp     = $arr[$i];
            $preIndex = $i - $gap;
            while ($preIndex >= 0 && $arr[$preIndex] > $temp) {
                $arr[$preIndex + $gap] = $arr[$preIndex];
                $preIndex              -= $gap;
            }
            $arr[$preIndex + $gap] = $temp;
        }
        $gap = floor($gap / 2);
    }
    return $arr;
}


/**
 * 5 归并排序是建立在归并操作上的一种有效的排序算法。
 * 该算法是采用分治法（Divide and Conquer）的一个非常典型的应用。
 * 归并排序是一种稳定的排序方法。将已有序的子序列合并，得到完全有序的序列；
 * 即先使每个子序列有序，再使子序列段间有序。
 * 若将两个有序表合并成一个有序表，称为2-路归并。
 * @param $arr
 * @return mixed
 */
function mergeSort($arr)
{
    $len = count($arr);
    if ($len < 2) {
        return $arr;
    }
    $mid   = floor($len / 2);
    $left  = array_slice($arr, 0, $mid);
    $right = array_slice($arr, $mid);
    return mergeSortMerge(mergeSort($left), mergeSort($right));
}

function mergeSortMerge($left, $right)
{
    $lenLeft  = count($left);
    $lenRight = count($right);
    $result   = [];
    $m        = $n = 0;
    for ($i = 0; $i < $lenLeft + $lenRight; $i++) {
        if ($m >= $lenLeft)
            $result[$i] = $right[$n++];
        else if ($n >= $lenRight)
            $result[$i] = $left[$m++];
        else if ($left[$m] > $right[$n])
            $result[$i] = $right[$n++];
        else
            $result[$i] = $left[$m++];
    }
    return $result;
}


/**
 * 6 快速排序 的基本思想：
 * 通过一趟排序将待排记录分隔成独立的两部分，其中一部分记录的关键字均比另一部分的关键字小，
 * 则可分别对这两部分记录继续进行排序，以达到整个序列有序。
 *
 * @param $arr
 * @param $l
 * @param $r
 * @return mixed
 */
function quickSort(&$arr, $l, $r)
{
    $len = count($arr);
    if ($len < 2) {
        return $arr;
    }
    if ($l > $r) {
        return $arr;
    }
    $mid = $arr[$l]; // 选择第一个数为key
    $i   = $l;
    $j   = $r;

    while ($i < $j) {
        while ($i < $j && $arr[$j] >= $mid) // 从右向左找第一个小于key的值
            $j--;
        if ($i < $j) {
            $arr[$i] = $arr[$j];
            $i++;
        }
        while ($i < $j && $arr[$i] < $mid)//从左向右找第一个大于key的值
            $i++;
        if ($i < $j) {
            $arr[$j] = $arr[$i];
            $j--;
        }
    }
    $arr[$i] = $mid;

    quickSort($arr, $l, $i - 1);
    quickSort($arr, $i + 1, $r);

    return $arr;
}


/**
 * 7 堆排序（Heapsort） 是指利用堆这种数据结构所设计的一种排序算法。
 * 堆积是一个近似完全二叉树的结构，并同时满足堆积的性质：
 * 即子结点的键值或索引总是小于（或者大于）它的父节点。
 *
 * @param $arr
 * @return mixed
 */
function heapSort(&$arr)
{
    $len = count($arr);
    if ($len < 2) {
        return $arr;
    }
    // 1.构建一个最大堆
    buildMaxHeap($arr);
    // 2.循环将堆首位（最大值）与末位交换，然后再重新调整最大堆
    $end = $len - 1;
    while ($end > 0) {
        swap($arr, 0, $end);
        $end--;
        adjustHeap($arr, 0, $end);
    }
    return $arr;
}

function buildMaxHeap(&$arr)
{
    $len = count($arr);
    //从最后一个非叶子节点开始向上构造最大堆
    //for循环这样写会更好一点：i的左子树和右子树分别2i+1和2(i+1)
    for ($i = (floor($len / 2) - 1); $i >= 0; $i--) {
        adjustHeap($arr, $i, $len - 1);
    }
}

function adjustHeap(&$arr, $i, $end)
{
    $maxIndex = $i;
    //如果有左子树，且左子树大于父节点，则将最大指针指向左子树
    if ($i * 2 <= $end && $arr[$i * 2] > $arr[$maxIndex])
        $maxIndex = $i * 2;
    // 如果有右子树，且右子树大于父节点，则将最大指针指向右子树
    if ($i * 2 + 1 <= $end && $arr[$i * 2 + 1] > $arr[$maxIndex])
        $maxIndex = $i * 2 + 1;
    // 如果父节点不是最大值，则将父节点与最大值交换，并且递归调整与父节点交换的位置。
    if ($maxIndex != $i) {
        swap($arr, $maxIndex, $i);
        adjustHeap($arr, $maxIndex, $end);
    }
}


/**
 * 交换数组内两个元素
 *
 * @param array
 * @param $i
 * @param $j
 */
function swap(&$arr, $i, $j)
{
    $temp    = $arr[$i];
    $arr[$i] = $arr[$j];
    $arr[$j] = $temp;
}


/**
 * 8 计数排序
 * 计数排序(Counting sort) 是一种稳定的排序算法。
 * 计数排序使用一个额外的数组C，其中第i个元素是待排序数组A中值等于i的元素的个数。
 * 然后根据数组C来将A中的元素排到正确的位置。它只能对整数进行排序。
 * @param $arr
 * @return mixed
 */
function countingSort($arr)
{
    $len = count($arr);
    if ($len < 2) {
        return $arr;
    }
    $max = $arr[0];
    $min = $arr[0];
    for ($i = 0; $i < $len; $i++) {
        if ($arr[$i] > $max) {
            $max = $arr[$i];
        }
        if ($arr[$i] < $min) {
            $min = $arr[$i];
        }
    }
    $base   = $min;
    $bucket = array_fill(0, $max - $min, 0);
    for ($i = 0; $i < $len; $i++) {
        $bucket[$arr[$i] - $base]++;
    }

    $index = $i = 0;
    while ($index < $len) {
        if ($bucket[$i] > 0) {
            $arr[$index] = $i + $base;
            $index++;
            $bucket[$i]--;
        } else {
            $i++;
        }
    }

    return $arr;
}


/**
 * 桶排序 (Bucket sort)的工作的原理：
 * 假设输入数据服从均匀分布，将数据分到有限数量的桶里，每个桶再分别排序
 * （有可能再使用别的排序算法或是以递归方式继续使用桶排序进行排
 *
 * @param $arr
 * @return mixed
 */
function bucketSort($arr)
{
    $len = count($arr);
    if ($len < 2) {
        return $arr;
    }

    // 1. 计算最大值与最小值
    $max = max($arr);
    $min = min($arr);

    // 2. 计算桶的数量
    $bucketNum = floor(($max - $min) / $len) + 1;
    $bucketArr = array_fill(0, $bucketNum, []);

    // 3. 将每个元素放入桶
    for ($i = 0; $i < $len; $i++) {
        $num = (int)floor(($arr[$i] - $min) / $len);
        // 分散到桶中
        $bucketArr[$num][] = $arr[$i];
    }

    // 4. 对每个桶进行排序
    for ($i = 0; $i < $bucketNum; $i++) {
        sort($bucketArr[$i]);
    }

    // 5. 将桶中的元素赋值到原序列
    $index = 0;
    for ($i = 0; $i < $bucketNum; $i++) {
        for ($j = 0; $j < count($bucketArr[$i]); $j++) {
            $arr[$index++] = $bucketArr[$i][$j];
        }
    }

    return $arr;
}


/**
 * 基数排序
 *
 * 基数排序是一种非比较型整数排序算法，其原理是将整数按位数切割成不同的数字，然后按每个位数分别比较。
 * 由于整数也可以表达字符串（比如名字或日期）和特定格式的浮点数，所以基数排序也不是只能使用于整数。
 *
 * 基数排序对于正整数和0的处理，可以很方便.
 * 先按照个位排序，然后按照十位排序，再按照百位依次进行
 *
 * 对比： 基数排序 vs 计数排序 vs 桶排序
 *      这三种排序算法都利用了桶的概念，但对桶的使用方法上有明显差异：
 *
 *      基数排序：根据键值的每位数字来分配桶；
 *      计数排序：每个桶只存储单一键值；
 *      桶排序：每个桶存储一定范围的数值；
 *
 * @param $arr
 * @return mixed
 */
function radixSort($arr)
{
    $len = count($arr);
    if ($len < 2) {
        return $arr;
    }
    // 最大数 [ 注意：该方法未考虑到负数的情况，如果有负数，需要额外处理 ]
    $m = $maxDigit = max($arr);
    // 求最大位数
    $d = 1;
    while ($m >= 10) {
        $m = floor($m / 10);
        $d++;
    }

    // 外层循环按照位数走
    $tmp   = [];
    $radix = 1;
    for ($i = 0; $i < $d; $i++) {

        // 每次分配前清空计数器 以及基数桶
        $counter = array_fill(0, 10, 0);
        $bucket  = array_fill(0, 10, []);

        // 分散到桶
        for ($j = 0; $j < $len; $j++) {
            $k = floor($arr[$j] / $radix) % 10;
            $counter[$k]++;
            $bucket[$k][] = $arr[$j];
        }

        // 存入临时数组
        foreach ($bucket as $item) {
            foreach ($item as $val) {
                $tmp[] = $val;
            }
        }
    }
    return $tmp;
}


$a = [1, 5, 6, 2, 8, 4, 3, 9];
print_r(radixSort($a));