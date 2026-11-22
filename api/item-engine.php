<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';require_once __DIR__.'/../libs/ai.inc.php';require_once __DIR__.'/../libs/item-engine.inc.php';
playerApi(static function():array{$owner=alphanoRequireLogin(true);if($_SERVER['REQUEST_METHOD']==='GET'){ return ['items'=>itemInventory($owner)]; }playerMutation();$in=playerInput();$id=rpg_int($in['instance']??0,1,PHP_INT_MAX,'Oggetto');$op=playerText($in,'operation',20,true);$target=rpg_int($in['target']??0,0,PHP_INT_MAX,'Oggetto di supporto');$key=rpg_request_key($in['request_key']??'');return ['success'=>true,'result'=>itemUsePersonal($owner,$id,$op,$target,$key)];});
