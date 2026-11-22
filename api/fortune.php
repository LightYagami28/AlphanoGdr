<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/fortune.inc.php';
playerApi(static function():array{
 $id=alphano_require_login(true);
 if($_SERVER['REQUEST_METHOD']==='GET'){ return ['state'=>alphano_fortune_state($id),'catalog'=>alphano_fortune_catalog(),'multipliers'=>(require __DIR__.'/../config/fortune.php')['multipliers']]; }
 playerMutation();$in=playerInput();
 if(playerText($in,'action',12,true)==='welcome'){
  $state=alphano_fortune_state($id);$login=(string)($_SESSION['fortune_login_id']??'');
  $fresh=$login!==''&&($_SESSION['fortune_welcome_seen']??'')!==$login;
  $_SESSION['fortune_welcome_seen']=$login;
  return ['state'=>$state,'show_welcome'=>$fresh&&$state['phase']!=='cooldown'&&(bool)alphano_fortune_catalog(),'name'=>GetPGFromID($id)];
 }
 $round=filter_var($in['round_id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
 if($round===false){ throw new DomainException('Giro non valido.',400); }
 return ['state'=>alphano_fortune_play($id,playerText($in,'action',12,true),playerText($in,'request_key',80),$round)];
});
