<?php
declare(strict_types=1);require_once __DIR__.'/../libs/board-games.inc.php';

function bgApiPost(int $uid,array $in):array {
  playerMutation();
  return str_starts_with((string)($in['action']??''),'admin_') ? bgAdminSave($uid,$in) : bgAction($uid,$in);
}
function bgApiPublic(int $uid,array $in,string $action):array {
  if($action==='invite_options'){ return bg_invite_options($uid,rpg_int($in['opponent']??0,1,PHP_INT_MAX),playerText($in,'chat',100)); }
  if($action==='history'){ return bgHistory(rpg_int($in['player']??$uid,1,PHP_INT_MAX),rpg_int($in['page']??1,1,10000)); }
  return bgRanking((string)($in['game']??'scacchi'),rpg_int($in['page']??1,1,10000));
}
function bgApiAdmin():array {
  if(empty(alphanoUser()['admin'])){ throw new DomainException('Riservato alla gestione.',403); }
  $db=connectDB();
  return ['settings'=>bgSettings(),'revision'=>(int)$db->query('SELECT revision FROM board_game_settings WHERE id=1')[0]['revision'],'rooms'=>$db->query('SELECT m.chat_key,m.title AS nome,r.scacchi,r.dama,r.coins,r.items,r.materials FROM mappe m LEFT JOIN board_game_rooms r ON r.chat_key=m.chat_key WHERE m.chat=1 AND m.chat_key<>\'\' ORDER BY m.title'),'active'=>$db->query("SELECT id,game_key,chat_key,a,b,status FROM board_games WHERE status IN('pending','active') ORDER BY id DESC LIMIT 100")];
}
function bgApiView(int $uid,array $in):array {
  return economiaAtomicaResiliente(static function()use($in,$uid){
    $m=connectDB()->query('SELECT * FROM board_games WHERE id=? FOR UPDATE',[rpg_int($in['id']??0,1,PHP_INT_MAX)])[0]??null;
    if(!$m){ throw new DomainException('Partita non trovata.',404); }
    bg_access($m,$uid);
    if(!bgCanWatch($m,$uid)){ throw new DomainException('Interagisci narrativamente con uno dei giocatori prima di osservare.',403); }
    bgExpire($m);
    return bgView($m,$uid);
  });
}
function bgApiGet(int $uid,array $in):array {
  $action=(string)($in['action']??'lobby');
  if(in_array($action,['invite_options','history','ranking'],true)){ return bgApiPublic($uid,$in,$action); }
  if($action==='admin'){ return bgApiAdmin(); }
  if($action==='view'){ return bgApiView($uid,$in); }
  return bgLobby($uid,playerText($in,'chat',100,true));
}
playerApi(static function(){
  $uid=alphanoRequireLogin(true);
  if(!bgReady()){ throw new DomainException('I giochi da tavolo non sono ancora disponibili.',503); }
  $in=playerInput();
  return $_SERVER['REQUEST_METHOD']!=='GET' ? bgApiPost($uid,$in) : bgApiGet($uid,$in);
});
