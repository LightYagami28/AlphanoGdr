<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/libs/config-loader.inc.php';
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/fortune.inc.php';
playerApi(static function():array{
 $id=alphanoRequireLogin(true);
 if($_SERVER['REQUEST_METHOD']==='GET'){ return ['state'=>alphanoFortuneState($id),'catalog'=>alphanoFortuneCatalog(),'multipliers'=>alphanoLoadConfig(__DIR__.'/../config/fortune.php')['multipliers']]; }
 playerMutation();$in=playerInput();
 if(playerText($in,'action',12,true)==='welcome'){
  $state=alphanoFortuneState($id);$login=(string)($_SESSION['fortune_login_id']??'');
  $fresh=$login!==''&&($_SESSION['fortune_welcome_seen']??'')!==$login;
  $_SESSION['fortune_welcome_seen']=$login;
  return ['state'=>$state,'show_welcome'=>$fresh&&$state['phase']!=='cooldown'&&(bool)alphanoFortuneCatalog(),'name'=>GetPGFromID($id)];
 }
 $round=filter_var($in['round_id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
 if($round===false){ throw new DomainException('Giro non valido.',400); }
 return ['state'=>alphanoFortunePlay($id,playerText($in,'action',12,true),playerText($in,'request_key',80),$round)];
});
