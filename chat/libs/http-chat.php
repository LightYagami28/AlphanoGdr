<?php
declare(strict_types=1);

require_once __DIR__ . '/../../libs/kernel.inc.php';
require_once __DIR__ . '/../../libs/libpg.inc.php';
require_once __DIR__ . '/../../libs/pvt/private-core.inc.php';
require_once __DIR__.'/../../libs/chat-history.inc.php';

/** Convalida la stanza sulla configurazione già usata dalla pagina chat. */
function httpChatRoom(string $chat, ?int $idpg = null): array
{
 $room = getDB()->query('SELECT chat_key, position, vampiredamage FROM mappe WHERE chat_key = ? AND chat = 1 AND public_enabled=1 AND (area_id IS NULL OR area_id IN(SELECT id FROM world_areas WHERE enabled=1)) LIMIT 1',[$chat])[0] ?? null;
 if(!$room && ($private = alphanoPrivateRoom($chat, $idpg ?? (int)($_SESSION['user_id'] ?? 0)))){ return $private; }
 if(!$room){ throw new DomainException('Chat inesistente', 404); }
 return $room;
}
function alphanoChatWindow($db,string $chat,int $since,bool $initial,string $liveFilter,string $archiveColumn):array {
 if($initial){
  $cursor=(int)($db->query('SELECT MAX(id) AS id FROM chat WHERE chat = ?',[$chat])[0]['id']??0);
  $rows=$db->query('SELECT id, pg, dest, msg, lican, location, ora'.$archiveColumn.' FROM chat WHERE chat = ?'.$liveFilter.' AND id <= ? AND ora > ? ORDER BY id DESC LIMIT 50',[$chat,$cursor,time()-7200]);
  return [array_reverse($rows),false,$cursor];
 }
 $rows=$db->query('SELECT id, pg, dest, msg, lican, location, ora'.$archiveColumn.' FROM chat WHERE chat = ? AND id > ? ORDER BY id ASC LIMIT 101',[$chat,$since]);
 $more=count($rows)>100;
 $rows=array_slice($rows,0,100);
 return [$rows,$more,$rows?(int)$rows[count($rows)-1]['id']:$since];
}
/** Le risposte IA precedenti al profilo ricevono lo stesso header delle nuove. */
function alphanoChatLegacyJobs($db,string $chat,array $rows):array {
 $legacyIds=[];
 foreach($rows as $row){
  if(!(int)$row['pg']&&!str_contains($row['msg'],'chat-character-header')&&str_contains($row['msg'],'data-ai-job=')){ $legacyIds[]=(int)$row['id']; }
 }
 if(!$legacyIds){ return []; }
 require_once __DIR__.'/../../libs/ai.inc.php';
 $legacy=[];
 foreach($db->query("SELECT id,npc_id,result,message_id,finished_at FROM ai_jobs WHERE chat_key=? AND status='done' AND kind='play' AND message_id IN (".implode(',',array_fill(0,count($legacyIds),'?')).')',[$chat,...$legacyIds]) as $job){ $legacy[(int)$job['message_id']]=$job; }
 return $legacy;
}
function alphanoChatLupineMask(array $row,int $viewerRace):string {
 require_once __DIR__.'/../../libs/chat-format.inc.php';
 if(str_contains($row['msg'],'data-language="lupine"')){ return alphanoChatMaskLupine($row['msg']); }
 $name=htmlspecialchars(GetPGFromID((int)$row['pg'])??'Qualcuno',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 return '<span class="String">'.$name.' emette un ringhio sommesso.</span>';
}
function alphanoChatVisibleMessages(array $rows,array $legacy,int $viewer,int $viewerRace):array {
 require_once __DIR__.'/../../libs/chat-presentation.inc.php';
 $messages=[];
 foreach($rows as $row){
  if(!empty($row['history_only'])){ continue; }
  if(isset($legacy[(int)$row['id']])){
   try{ $row['msg']=aiMessageHtml($legacy[(int)$row['id']]); }
   catch(Throwable $e){ error_log('[Master IA storico] '.$e->getMessage()); }
  }
  if((int)$row['dest']!==0&&(int)$row['dest']!==$viewer&&(int)$row['pg']!==$viewer){ continue; }
  if((int)$row['lican']===1&&!in_array($viewerRace,[ALPHANO_RACE_LYCAN,ALPHANO_RACE_ANCESTRAL],true)){ $row['msg']=alphanoChatLupineMask($row,$viewerRace); }
  $messages[]=['id'=>(int)$row['id'],'msg'=>$row['msg'],'pg'=>(int)$row['pg'],'location'=>$row['location']??'','sent_at'=>(int)$row['ora'],...alphanoChatView($row)];
 }
 return $messages;
}
/** Letture brevi: nessuna attesa e nessun lock sulla sessione PHP durante le query. */
function httpChatHistory(string $chat, int $since, int $viewer, bool $initial, int $viewerRace = 0): array
{
 $db=getDB();
 $liveFilter=alphanoChatLiveFilter();$archiveColumn=alphanoChatHasArchive()?',history_only':',0 AS history_only';
 [$rows,$more,$cursor]=alphanoChatWindow($db,$chat,$since,$initial,(string)$liveFilter,$archiveColumn);
 $legacy=alphanoChatLegacyJobs($db,$chat,$rows);
 return ['messages'=>alphanoChatVisibleMessages($rows,$legacy,$viewer,$viewerRace),'lastId'=>$cursor,'more'=>$more];
}
