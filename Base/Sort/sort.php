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
    if (!$len) {
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
 * 选择排序(基于交换)
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
    if (!$len) {
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
 * 插入排序（Insertion-Sort） 的算法描述是一种简单直观的排序算法。(基于交换)
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
    if (!$len) {
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


$a = [1, 5, 6, 7, 8, 3, 2];
print_r(insertionSort($a));