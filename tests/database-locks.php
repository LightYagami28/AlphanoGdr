<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/database-lock.inc.php';
$count=0;
foreach(['session','install','ai-worker',str_repeat('scope',50)] as $scope)foreach(['my_test',str_repeat('d',64)] as $database)foreach(['room_123',str_repeat('chat_è',150)] as $identity){
 $name=alphanoLockName($scope,$identity,$database);
 if(strlen($name)>64||!preg_match('/^[a-z0-9:]+$/iD',$name)||$name!==alphanoLockName($scope,$identity,$database)||$name===alphanoLockName($scope,$identity,$database.'_other'))throw new RuntimeException('Invalid MySQL named lock');
 $count++;
}
if(alphanoLockName('session','a','db')===alphanoLockName('session','b','db')||alphanoLockName('session','a','db')===alphanoLockName('install','a','db'))throw new RuntimeException('Lock namespaces collided');
echo "PASS $count MySQL lock length, determinism and namespace cases\n";
