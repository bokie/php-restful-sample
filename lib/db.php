<?php
/**
 * db.php 数据库配置文件
 * @return 返回数据库连接句柄
 */

 // 创建 PDO 对象
 $pdo = new PDO( 'mysql:host=localhost;dbname=test', 'test', '123456789' );
 $pdo->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
 return $pdo;

