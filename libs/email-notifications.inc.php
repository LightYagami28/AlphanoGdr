<?php
declare(strict_types=1);
require_once __DIR__.'/alphano-exceptions.inc.php';

require_once __DIR__.'/kernel.inc.php';
require_once __DIR__.'/experience.inc.php';
/** Enabling one category must not discard pending mail in another category. */
function alphanoEmailSince(int $recipient,string $category):int {
 $row=connectDB()->query('SELECT email_preferences,email_enabled_at FROM user_experience_settings WHERE user_id=?',[$recipient])[0]??[];
 $json=json_decode($row['email_preferences']??'{}',true)??[];return (int)($json['_since']['email_'.$category]??$row['email_enabled_at']??0);
}
/** Only opaque source keys and short public labels are queued; never message bodies. */
function alphanoEmailEnqueue(int $recipient,string $key,string $category,string $title,string $path,int $created):void {
 if(alphanoConfig('mail_transport')==='disabled'||!isset(alphanoEmailCategories()[$category])){ return; }
 $db=connectDB();$u=$db->query('SELECT id,bloccato FROM utenti WHERE id=?',[$recipient])[0]??null;
 if(!$u||$u['bloccato']||!alphanoExperienceSettings($recipient)['email_'.$category]){ return; }
 $enabled=alphanoEmailSince($recipient,$category);
 if(!$enabled||$created<$enabled){ return; }
 if(!str_starts_with($path,'/')||str_starts_with($path,'//')){ $path='/notifiche/'; }
 $db->execute('INSERT IGNORE INTO email_notification_outbox(recipient,event_key,category,title,path,created_at) VALUES(?,?,?,?,?,?)',[$recipient,substr($key,0,190),$category,mb_substr($title,0,255),substr($path,0,1000),$created]);
}
function alphanoEmailMailNotice(int $recipient,int $sender,string $subject,string $body,int $id,?int $created=null):void {
 if($recipient===$sender){ return; }
 $trade=preg_match('/\[alphano-trade:|trasferimento|trattativa|ordine|acquisto|consegna|proposta di prezzo/iu',$subject.' '.substr($body,0,100));
 if($trade){ $category='trade'; }elseif($sender>0){ $category='mail'; }else{ $category='alerts'; }
 if($trade){ $title='Aggiornamento di una trattativa'; }elseif($sender>0){ $title='Hai una nuova missiva'; }else{ $title='Nuovo avviso per il tuo personaggio'; }
 alphanoEmailEnqueue($recipient,'mail:'.$id,$category,$title,'/posta/',$created??time());
}
function alphanoEmailNoticeCategory(string $group,string $section,string $key):string {
 if($section==='giochi'){ return 'games'; }
 if(in_array($group,['organizzazioni','corporazioni','accademia'],true)){ return 'groups'; }
 if($group==='citta'){ return 'civic'; }
 if($group==='bacheche'){ return 'boards'; }
 if($group==='master'){ return 'quests'; }
 return 'alerts';
}
function alphanoEmailCollectMails($db,int $uid,int $since):void {
 foreach($db->query('SELECT m.id,m.idmittente,m.oggetto,m.messaggio,m.data,d.event_id AS civic_event FROM mail m LEFT JOIN civic_election_deliveries d ON d.mail_id=m.id WHERE m.iddestinatario=? AND m.idmittente<>? AND m.data>=? AND m.letto=0 AND m.deldest=0 ORDER BY m.id DESC LIMIT 200',[$uid,$uid,max($since,time()-86400)]) as $mail){
  if($mail['civic_event']){ alphanoEmailEnqueue($uid,'civic:'.$mail['civic_event'],'civic','Aggiornamento delle elezioni cittadine','/citta/elezioni.php',(int)$mail['data']); }
  else{ alphanoEmailMailNotice($uid,(int)$mail['idmittente'],$mail['oggetto'],$mail['messaggio'],(int)$mail['id'],(int)$mail['data']); }
 }
}
function alphanoEmailCollectNotices(int $uid):int {
 $count=0;
 foreach(alphanoActivityNotifications($uid) as $notice){
  if(($notice['event_type']??'')==='civic-election'){ continue; }
  $key=(string)$notice['key'];$group=$notice['group'];
  $category=alphanoEmailNoticeCategory((string)$group,(string)($notice['section']??''),$key);
  // Personal trade receipts can have the gioco group.
  if(preg_match('/trade|transfer|order|loan|commerc|trasferiment/i',$key.' '.($notice['section']??''))){ $category='trade'; }
  alphanoEmailEnqueue($uid,'notice:'.$key,$category,'Nuovo aggiornamento · '.alphanoEmailCategories()[$category][0],'/notifiche/',(int)($notice['created_at']??0));$count++;
 }
 return $count;
}
function alphanoEmailCollectQuests($db,int $uid,int $since):void {
 foreach($db->query('SELECT id,title,chat_key,created_at FROM quest_notifications WHERE recipient=? AND read_at IS NULL AND created_at>=?',[$uid,$since]) as $q){ alphanoEmailEnqueue($uid,'quest:'.$q['id'],'quests','Una nuova quest ti attende','/chat/index.php?chat='.rawurlencode($q['chat_key']),(int)$q['created_at']); }
}
/** Reuse current notification ACLs, including memberships revoked since the event. */
function alphanoEmailCollect(int $limit=25):int {
 require_once __DIR__.'/notifications.inc.php';$db=connectDB();
 $cursor=(int)($db->query("SELECT state_value FROM email_dispatch_state WHERE state_key='cursor'")[0]['state_value']??0);
 $rows=$db->query("SELECT user_id,email_preferences,email_enabled_at FROM user_experience_settings WHERE user_id>? AND email_enabled_at>0 ORDER BY user_id LIMIT ".max(1,min(100,$limit)),[$cursor]);
 if(!$rows){$db->execute("INSERT INTO email_dispatch_state VALUES('cursor',0) ON DUPLICATE KEY UPDATE state_value=0");return 0;}
 $count=0;
 foreach($rows as $row){
  $uid=(int)$row['user_id'];$cursor=$uid;$prefs=json_decode($row['email_preferences']??'{}',true)??[];
  if(!in_array(true,$prefs,true)){ continue; }
  $since=(int)$row['email_enabled_at'];
  alphanoEmailCollectMails($db,$uid,$since);
  $count+=alphanoEmailCollectNotices($uid);
  alphanoEmailCollectQuests($db,$uid,$since);
 }
 $db->execute("INSERT INTO email_dispatch_state VALUES('cursor',?) ON DUPLICATE KEY UPDATE state_value=VALUES(state_value)",[$cursor]);return $count;
}
function alphanoEmailSendRow($db,array $row,array $u):bool {
 $body='Ciao '.$u['nome'].",\n\n".$row['title'].". Accedi a ".alphanoConfig('game_name')." per consultare i dettagli aggiornati e rispondere.\n\nLe informazioni riservate restano disponibili soltanto nel gioco.";
 $html=alphanoEmailRender($row['title'],$body,$row['category'],$row['path'],true);
 if(!alphanoMailer()->send(alphanoConfig('mail_from'),$u['email'],alphanoConfig('game_name').' · '.$row['title'],$body,$html)){ throw new AlphanoStorageException('Invio non completato'); }
 $db->execute("UPDATE email_notification_outbox SET status='sent',sent_at=?,retry_at=0 WHERE id=?",[time(),(int)$row['id']]);
 return true;
}
/** Worker only: UI mutations never wait for SMTP. A claim also tolerates worker crashes. */
function alphanoEmailDeliver(int $limit=20,int $budget=15):array {
 if(alphanoConfig('mail_transport')==='disabled'){ return ['sent'=>0,'skipped'=>0]; }
 require_once __DIR__.'/sendmail.inc.php';$db=connectDB();$until=microtime(true)+$budget;$sent=0;$skipped=0;
 foreach($db->query("SELECT * FROM email_notification_outbox WHERE status IN('pending','retry','sending') AND retry_at<=? ORDER BY id LIMIT ".max(1,min(100,$limit)),[time()]) as $row){
  if(microtime(true)>$until){ break; }$id=(int)$row['id'];$uid=(int)$row['recipient'];
  if(!$db->execute("UPDATE email_notification_outbox SET status='sending',retry_at=?,attempts=attempts+1 WHERE id=? AND status IN('pending','retry','sending') AND retry_at<=?",[time()+300,$id,time()])){ continue; }
  $u=$db->query('SELECT nome,email,bloccato FROM utenti WHERE id=?',[$uid])[0]??null;
  $authorized=true;if(str_starts_with($row['event_key'],'notice:')){require_once __DIR__.'/notifications.inc.php';$authorized=in_array(substr($row['event_key'],7),array_column(alphanoActivityNotifications($uid),'key'),true);}
  $enabled=alphanoEmailSince($uid,$row['category']);
  if(!$authorized||$row['created_at']<$enabled||!$u||$u['bloccato']||!alphanoExperienceSettings($uid)['email_'.$row['category']]||$row['created_at']<time()-86400){$db->execute("UPDATE email_notification_outbox SET status='skipped' WHERE id=?",[$id]);$skipped++;continue;}
  if((int)$db->query('SELECT COUNT(*) n FROM email_notification_outbox WHERE recipient=? AND sent_at>=?',[$uid,time()-86400])[0]['n']>=20){$db->execute("UPDATE email_notification_outbox SET status='retry',retry_at=? WHERE id=?",[time()+3600,$id]);continue;}
  try{
   if(alphanoEmailSendRow($db,$row,$u)){ $sent++; }
  }catch(Throwable $e){$db->execute('UPDATE email_notification_outbox SET status=?,retry_at=? WHERE id=?',[(int)$row['attempts']>=4?'failed':'retry',time()+min(86400,300*(2**min(8,(int)$row['attempts']))),$id]);error_log('[email-notifications] Consegna non riuscita, ricevuta '.$id);}
 }return ['sent'=>$sent,'skipped'=>$skipped];
}
