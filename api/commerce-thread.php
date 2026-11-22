<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/commerce-thread.inc.php';
playerApi(static function():array{
 $uid=alphanoRequireLogin(true);$in=playerInput();$id=commercioIntero($in['id']??null,1,2147483647);
 if(!$id){ throw new DomainException('Trattativa non valida.',400); }
 if($_SERVER['REQUEST_METHOD']==='GET'){ $t=alphanoTradeThread($id,$uid); }
 else{playerMutation();$t=alphanoTradeThreadAct($id,$uid,$in);}
 $t['progress']=alphanoTradeSummary($t);return $t;
});
