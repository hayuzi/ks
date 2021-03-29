<?php


/**
 * 冒泡排序 是一种简单的排序算法。 (基于交换)
 * 它重复地走访过要排序的数列，一次比较两个元素，如果它们的顺序错误就把它们交换过来。
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
 * 选择排序 是表现最稳定的排序算法之一 ，因为无论什么数据进去都是O(n2)的时间复杂度 ，
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
 * 插入排序（Insertion-Sort） 的算法描述是一种简单直观的排序算法
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
 * 希尔排序是希尔（Donald Shell） 于1959年提出的一种排序算法。
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
 * 归并排序是建立在归并操作上的一种有效的排序算法。
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
 * 快速排序 的基本思想：
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
 * 堆排序（Heapsort） 是指利用堆这种数据结构所设计的一种排序算法。
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


$a = [1, 5, 6, 2, 8, 4, 3, 9];
print_r(heapSort($a));