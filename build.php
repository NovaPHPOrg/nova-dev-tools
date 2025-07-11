<?php


// 创建Phar对象
$pharFile = 'nova.phar';

if(file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile, 0, $pharFile);

// 设置压缩算法
$phar->compressFiles(Phar::GZ);

// 从src目录构建Phar包
$srcDir = dirname(__FILE__) . '/src';
$phar->buildFromDirectory($srcDir);

// 设置默认启动脚本
$phar->setDefaultStub('start.php', 'start.php');

// 设置只读属性
//$phar->rea(true);

echo "Phar包 {$pharFile} 已成功创建。\n";