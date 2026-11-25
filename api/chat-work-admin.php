<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';require_once __DIR__.'/../libs/chat-work.inc.php';

function alphanoWorkAdminSnapshot($d):array {
 return ['settings'=>alphanoWorkSettings(),'version'=>(int)$d->query('SELECT version FROM chat_work_settings WHERE id=1')[0]['version'],'jobs'=>alphanoWorkCatalog(),'resources'=>alphanoWorkResources(),'rooms'=>$d->query('SELECT chat_key,title FROM mappe WHERE chat=1 ORDER BY title'),'stats'=>rpgCatalog()['stats'],'history'=>$d->query('SELECT w.id,w.player_id,u.nome,w.chat_key,w.roll,w.bonus,w.difficulty,w.quantity,w.snapshot,w.created_at FROM chat_work_attempts w LEFT JOIN utenti u ON u.id=w.player_id ORDER BY w.id DESC LIMIT 100'),'audit'=>$d->query('SELECT a.id,a.kind,a.actor_id,u.nome,a.target_id,a.created_at FROM chat_work_admin_audit a LEFT JOIN utenti u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT 30')];
}
function alphanoWorkAdminSaveSettings($d,array $in):array {
 $before=alphanoWorkSettings();$after=$before;
 foreach(['enabled','require_narration'] as $k){ $after[$k]=rpgBool($in[$k]??false); }
 foreach(['max_units'=>[1,1000],'window_seconds'=>[60,604800],'rest_seconds'=>[60,604800],'narration_min_chars'=>[5,500],'attempt_cooldown'=>[0,3600],'bonus_step'=>[1,100],'max_bonus'=>[0,10]] as $k=>[$min,$max]){ $after[$k]=rpgInt($in[$k]??null,$min,$max,$k); }
 $d->execute('UPDATE chat_work_settings SET settings=? WHERE id=1',[rpgJson($after)]);
 return [$before,$after,0];
}
function alphanoWorkAdminSaveJob($d,array $in):array {
 $target=rpgInt($in['id']??0,0,2147483647,'Attività');$chat=rpgText($in['chat']??'',50,true);$text=rpgText($in['azione']??'',255,true);$phrase=rpgText($in['frase']??'',1000);$prob=rpgInt($in['prob']??null,5,95,'Probabilità base');$res=rpgInt($in['res']??0,1,2147483647,'Materia prima');$quantity=rpgInt($in['quantity']??1,1,100,'Unità');$stat=rpgText($in['stat_key']??'',20,true);$enabled=rpgBool($in['enabled']??false);
 if(!in_array($stat,RPG_STATS,true)||!$d->query('SELECT id FROM mappe WHERE chat_key=? AND chat=1',[$chat])||!in_array($res,array_column(alphanoWorkResources(),'id'),true)){ throw new DomainException('Seleziona luogo, caratteristica e materia prima validi.',422); }
 if($d->query('SELECT id FROM lavori WHERE chat=? AND azione=? AND id<>?',[$chat,$text,$target])){ throw new DomainException('Esiste già questa attività nel luogo selezionato. Modifica quella esistente.',409); }
 $before=[];
 if($target){
  $before=$d->query('SELECT * FROM lavori WHERE id=?',[$target])[0]??null;
  if(!$before){ throw new DomainException('Attività non trovata.',404); }
  $before['options']=$d->query('SELECT * FROM chat_work_options WHERE job_id=?',[$target])[0]??null;
  $d->execute('UPDATE lavori SET chat=?,azione=?,prob=?,res=?,frase=? WHERE id=?',[$chat,$text,$prob,$res,$phrase,$target]);
 }else{
  $d->execute('INSERT INTO lavori(chat,azione,prob,res,frase) VALUES(?,?,?,?,?)',[$chat,$text,$prob,$res,$phrase]);
  $target=(int)$d->lastInsertId();
 }
 $d->execute('INSERT INTO chat_work_options(job_id,enabled,quantity,stat_key) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),quantity=VALUES(quantity),stat_key=VALUES(stat_key)',[$target,$enabled,$quantity,$stat]);
 $after=['chat'=>$chat,'azione'=>$text,'prob'=>$prob,'res'=>$res,'frase'=>$phrase,'quantity'=>$quantity,'stat_key'=>$stat,'enabled'=>$enabled];
 return [$before,$after,$target];
}
playerApi(static function():array{
 $uid=alphanoRequireLogin(true);
 if(!alphanoWorkStaff($uid)){ throw new DomainException('Gestione riservata ad amministratori e responsabili dell’Accademia.',403); }
 $d=connectDB();
 if($_SERVER['REQUEST_METHOD']==='GET'){ return alphanoWorkAdminSnapshot($d); }
 playerMutation();$in=playerInput();$action=rpgText($in['action']??'',20,true);$version=rpgInt($in['version']??0,1,2147483647,'Versione');
 $d->beginTransaction();try{
  $current=$d->query('SELECT * FROM chat_work_settings WHERE id=1 FOR UPDATE')[0];
  if((int)$current['version']!==$version){ throw new DomainException('Un altro gestore ha aggiornato il pannello. Ricarica prima di salvare.',409); }
  if($action==='settings'){ [$before,$after,$target]=alphanoWorkAdminSaveSettings($d,$in); }
  elseif($action==='job'){ [$before,$after,$target]=alphanoWorkAdminSaveJob($d,$in); }
  else { throw new DomainException('Operazione non valida.',422); }
  $d->execute('UPDATE chat_work_settings SET version=version+1,updated_by=?,updated_at=? WHERE id=1',[$uid,time()]);$d->execute('INSERT INTO chat_work_admin_audit(actor_id,kind,target_id,before_state,after_state,created_at) VALUES(?,?,?,?,?,?)',[$uid,$action,$target,rpgJson($before),rpgJson($after),time()]);$d->commit();return ['ok'=>true,'id'=>$target];
 }catch(Throwable $e){if($d->inTransaction()){ $d->rollBack(); }throw $e;}
});
