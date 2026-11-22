<?php
declare(strict_types=1);
require_once __DIR__ . '/../../libs/player-runtime.inc.php';

function alphanoDiscussionFormat(array $post,int $idpg,bool $moderator):array {
 return ['id'=>(int)$post['id'],'oggetto'=>$post['oggetto'],'messaggio'=>processText($post['messaggio']),
  'message_raw'=>$post['messaggio'],'data'=>(int)$post['data'],'author_id'=>(int)$post['idpg'],'author'=>$post['author'] ?? 'Sistema',
  'unread'=>!empty($post['unread']),'chiusa'=>!empty($post['chiusa']),'ontop'=>!empty($post['ontop']),
  'can_edit'=>$moderator || (int)$post['idpg']===$idpg,'can_moderate'=>$moderator,'numrisp'=>(int)($post['numrisp'] ?? 0)];
}
function alphanoDiscussionList(int $idpg,bool $moderator,$db,string $type,string $action):array {
 $rows=$db->query('SELECT d.*,u.nome AS author,EXISTS(SELECT 1 FROM discussioni p LEFT JOIN activity_notification_reads nr ON nr.recipient=? AND nr.notice_key=CONCAT(\'discussion:\',p.id) WHERE p.tipo=d.tipo AND (p.id=d.id OR p.replyid=d.id) AND p.idpg<>? AND nr.notice_key IS NULL) AS unread,(SELECT COUNT(*) FROM discussioni p WHERE p.replyid=d.id AND p.tipo=d.tipo) AS numrisp FROM discussioni d LEFT JOIN utenti u ON u.id=d.idpg WHERE d.replyid=0 AND d.tipo=? AND d.chiusa=? ORDER BY d.ontop DESC,d.data DESC,d.id DESC',[$idpg,$idpg,$type,$action==='list_closed' ? '1':'0']);
 return ['threads'=>array_map(static fn($post)=>alphanoDiscussionFormat($post,$idpg,$moderator),$rows),'can_moderate'=>$moderator];
}
function alphanoDiscussionCreate(int $idpg,bool $moderator,$db,string $type,array $in):array {
 $subject=playerText($in,'oggetto',255) ?: 'Nessun Oggetto'; $message=playerText($in,'messaggio',100000,true);
 $db->execute('INSERT INTO discussioni(data,oggetto,messaggio,tipo,idpg,ontop,chiusa) VALUES(?,?,?,?,?,?,?)',[time(),$subject,$message,$type,$idpg,$moderator && !empty($in['ontop']) ? '1':'0',$moderator && !empty($in['chiudi']) ? '1':'0']);
 return ['new_id'=>(int)$db->lastInsertId()];
}
function alphanoDiscussionRow($db,string $type,string $action,array $in):array {
 $id=(int)($action==='reply' ? ($in['replyID'] ?? 0) : ($in['id'] ?? 0));
 $row=$db->query('SELECT d.*,u.nome AS author FROM discussioni d LEFT JOIN utenti u ON u.id=d.idpg WHERE d.id=? AND d.tipo=?',[$id,$type])[0] ?? null;
 if(!$row){ throw new RuntimeException('Messaggio non trovato',404); }
 return [$id,$row];
}
function alphanoDiscussionShow(int $idpg,bool $moderator,$db,string $type,int $id,array $row):array {
 if((int)$row['replyid']!==0){ throw new RuntimeException('Thread non trovato',404); }
 $posts=$db->query('SELECT d.*,u.nome AS author FROM discussioni d LEFT JOIN utenti u ON u.id=d.idpg WHERE d.replyid=? AND d.tipo=? ORDER BY d.id',[$id,$type]);
 return ['thread'=>alphanoDiscussionFormat($row,$idpg,$moderator),'posts'=>array_map(static fn($post)=>alphanoDiscussionFormat($post,$idpg,$moderator),$posts)];
}
function alphanoDiscussionReply(int $idpg,bool $moderator,$db,string $type,int $id,array $row,array $in):array {
 if((int)$row['replyid']!==0){ throw new RuntimeException('Thread non trovato',404); }
 if(!empty($row['chiusa'])){ throw new RuntimeException('Thread chiuso',409); }
 $subject=playerText($in,'oggetto',255) ?: 'Nessun Oggetto'; $message=playerText($in,'messaggio',100000,true);
 $db->beginTransaction();
 try{
  $locked=$db->query('SELECT chiusa FROM discussioni WHERE id=? AND tipo=? FOR UPDATE',[$id,$type])[0] ?? null;
  if(!$locked){ throw new RuntimeException('Thread non trovato',404); }
  if(!empty($locked['chiusa'])){ throw new RuntimeException('Thread chiuso',409); }
  $db->execute('INSERT INTO discussioni(replyid,data,oggetto,messaggio,tipo,idpg) VALUES(?,?,?,?,?,?)',[$id,time(),$subject,$message,$type,$idpg]);
  $newId=(int)$db->lastInsertId();
  if($moderator && !empty($in['chiudi'])){ $db->execute('UPDATE discussioni SET chiusa=1 WHERE id=? AND tipo=?',[$id,$type]); }
  $db->commit();
 }catch(Throwable $e){ $db->rollBack(); throw $e; }
 return ['post_id'=>$newId];
}
function alphanoDiscussionClose(bool $moderator,$db,string $type,int $id,array $row,string $action):array {
 if(!$moderator){ throw new RuntimeException('Permesso negato',403); }
 if((int)$row['replyid']!==0){ throw new RuntimeException('Non è un thread',400); }
 $db->execute('UPDATE discussioni SET chiusa=? WHERE id=? AND tipo=?',[$action==='close'?'1':'0',$id,$type]);
 return [$action==='close'?'closed':'opened'=>true];
}
function alphanoDiscussionOwner(int $idpg,bool $moderator,$db,string $type,int $id,array $row,array $in,string $action):array {
 if(!$moderator && (int)$row['idpg']!==$idpg){ throw new RuntimeException('Permesso negato',403); }
 if($action==='modify'){
  $subject=playerText($in,'oggetto',255) ?: 'Nessun Oggetto'; $message=playerText($in,'messaggio',100000,true);
  $db->execute('UPDATE discussioni SET oggetto=?,messaggio=? WHERE id=? AND tipo=?',[$subject,$message,$id,$type]);
  return ['modified'=>true];
 }
 if($action==='delete'){
  $db->execute('DELETE FROM discussioni WHERE (id=? OR replyid=?) AND tipo=?',[$id,$id,$type]);
  return ['deleted'=>true];
 }
 throw new RuntimeException('Azione non valida',400);
}
function alphanoDiscussionWrite(int $idpg,bool $moderator,$db,string $type,int $id,array $row,array $in,string $action):array {
 if($action==='reply'){ return alphanoDiscussionReply($idpg,$moderator,$db,$type,$id,$row,$in); }
 if($action==='close' || $action==='open'){ return alphanoDiscussionClose($moderator,$db,$type,$id,$row,$action); }
 return alphanoDiscussionOwner($idpg,$moderator,$db,$type,$id,$row,$in,$action);
}
function alphanoDiscussionRowDispatch(int $idpg,bool $moderator,$db,string $type,array $in,string $action):array {
 [$id,$row]=alphanoDiscussionRow($db,$type,$action,$in);
 if($action==='show'){ return alphanoDiscussionShow($idpg,$moderator,$db,$type,$id,$row); }
 return alphanoDiscussionWrite($idpg,$moderator,$db,$type,$id,$row,$in,$action);
}
function alphanoDiscussionDispatch(int $idpg,bool $moderator,$db,string $type,array $in,string $action):array {
 if(in_array($action,['list_open','list_closed'],true)){ return alphanoDiscussionList($idpg,$moderator,$db,$type,$action); }
 if($action==='create'){ return alphanoDiscussionCreate($idpg,$moderator,$db,$type,$in); }
 return alphanoDiscussionRowDispatch($idpg,$moderator,$db,$type,$in,$action);
}
playerApi(function (): array {
 $idpg=alphanoRequireLogin(true); $user=alphanoUser(); $db=getDB(); $in=playerInput();
 $type=playerText($in,'tipo',50,true); $moderator=playerDiscussionAccess($type,$user);
 $action=playerText($in,'action',30) ?: 'list_open';
 if (!in_array($action,['list_open','list_closed','show'],true)) { playerMutation(); }
 return alphanoDiscussionDispatch($idpg,$moderator,$db,$type,$in,$action);
});
