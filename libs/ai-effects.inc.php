<?php
declare(strict_types=1);
require_once __DIR__.'/libferite.inc.php';
require_once __DIR__.'/libblocco.inc.php';
require_once __DIR__.'/rpg-combat.inc.php';
/** Technical resolution belongs here. No numeric rule is inferred from prose or general lore. */
function aiQuestTechnical(int $sid):array{
 $mode=aiDb()->query('SELECT policy FROM ai_quests WHERE session_id=?',[$sid])[0]??null;
 if($mode&&aiSceneKind($mode)==='narrazione'){ return alphanoSessionNarrativeTechnical($sid); }
 if(aiAdjudicationEnabled($sid)){ return aiAdjudicate($sid); }
 $db=aiDb();$g=aiGameGuidance($sid);$q=$db->query('SELECT * FROM ai_quests WHERE session_id=?',[$sid])[0];$p=json_decode($q['policy'],true)?:[];$current=aiKnowledgePolicy((int)($p['knowledge_id']??0));
 $actions=$db->query('SELECT * FROM ai_quest_actions WHERE session_id=? AND round_number=? ORDER BY id',[$sid,(int)$g['round_number']]);$order=json_decode($g['turn_order'],true)?:[];
 if(count($actions)!==count($order)){ throw new DomainException('Il giro non è completo: nessun verdetto disponibile.',409); }
 $reasons=[];$changes=[];$results=[];$automatic=($current['resolution']??'review')==='opposed_force_d20'&&isset($current['damage']);
 $needsRules=aiSceneKind($q)!=='allenamento'&&!aiSceneCanFinish($sid);
 if(!$automatic&&$needsRules){ $reasons[]='Manca una meccanica di risoluzione ufficiale configurata'; }
 if(isset($p['knowledge_version'])&&($p['knowledge_version']!==($current['knowledge_version']??null))){ $reasons[]='La versione della regola tecnica è cambiata'; }
 foreach($actions as $a){
  $d=json_decode($a['details'],true)?:[];$actor=aiQuestActor($a['kind'],(int)$a['actor_id']);$entry=['id'=>(int)$a['id'],'actor'=>$actor['name']??'Assente','attempt'=>aiText($a['content'],210),'outcome'=>'tentativo'];
  if($a['status']==='skipped'){$entry['outcome']='turno saltato';$results[]=$entry;continue;}
  $state=json_decode($db->query('SELECT state FROM ai_quest_cast WHERE session_id=? AND kind=? AND actor_id=?',[$sid,$a['kind'],(int)$a['actor_id']])[0]['state']??'{}',true)?:[];
  if(($actor['puntif']??0)<1){$entry['outcome']='azione impossibile: personaggio incapace';$results[]=$entry;continue;}
  if(in_array($d['intent']??'',['difesa','negozia','ritirata','resa','aiuto','azione','fine','allenamento'],true)){$entry['outcome']='intenzione dichiarata; nessun danno automatico';$results[]=$entry;continue;}
  if(trim((string)($actor['ferite']??''))!==''){ $reasons[]='Le ferite persistenti di '.$entry['actor'].' richiedono una valutazione fisica del Master'; }
  $targetKind=$d['target_kind']??'';$targetId=(int)($d['target_id']??0);$target=$targetId&&in_array($targetKind,['pg','npc'],true)?aiQuestActor($targetKind,$targetId):[];
  if(!$target||!$db->query('SELECT actor_id FROM ai_quest_cast WHERE session_id=? AND kind=? AND actor_id=? AND active=1',[$sid,$targetKind,$targetId])){$reasons[]='Bersaglio da precisare per '.$entry['actor'];$results[]=$entry;continue;}
  if(($d['intent']??'')==='potere'){$rules=aiGameRules((int)$actor['razza'],(bool)($actor['senziente']??0),$a['content']);$entry['racial_rules']=$rules;$reasons[]='Potere razziale da valutare secondo il manuale della stirpe effettiva';$results[]=$entry;continue;}
  if(($d['intent']??'')==='ricarica'){
   $items=aiGameInventory($a['kind'],(int)$a['actor_id']);$held=rpgDeclaredWeapon($items,$a['content'],true);$ammo=$held?array_filter($items,static fn($i)=>(int)$i['id']===(int)($state['ammo_instance']??0)&&rpgRangedAmmo($i,$held)&&(int)$i['consumo']<(int)$i['durata']):[];
   if(!$held||!$ammo){$reasons[]='Ricarica: arma o munizioni compatibili non disponibili';$results[]=$entry;continue;}
   if(!isset($current['reload_rounds'],$state['ammo_instance'])||(int)$g['round_number']-(int)($state['last_shot_round']??0)<(int)$current['reload_rounds']){$reasons[]='Ricarica: tempo o munizioni non verificati';$results[]=$entry;continue;}
   $entry['outcome']='ricarica verificabile dal backend';$changes[]=['type'=>'reload','kind'=>$a['kind'],'id'=>(int)$a['actor_id']];$results[]=$entry;continue;
  }
  $inventory=aiGameInventory($a['kind'],(int)$a['actor_id']);$weapon=rpgDeclaredWeapon($inventory,$a['content']);
  $raw=mb_strtolower($a['content']);$ranged=($weapon&&rpgRangedFamily($weapon)!=='')||(bool)preg_match('/balestra|dardo|scocca|freccia|\barco\b|fucil|pistol|moschett|carabin|trombon|archibug|\bspar\p{L}*/u',$raw);$charge=(bool)preg_match('/carica|galoppo/u',$raw);
  if($weapon&&(int)$weapon['consumo']>=(int)$weapon['durata']){$entry['outcome']='arma inutilizzabile';$results[]=$entry;continue;}
  if(!$weapon&&!preg_match('/pugno|calcio|mani nude|artiglio|morso|\bmord\p{L}*/iu',$raw)){$reasons[]='Arma impugnata non registrata per '.$entry['actor'];$results[]=$entry;continue;}
  if($ranged){
   if(!$weapon||rpgRangedFamily($weapon)===''){$entry['outcome']='nessuna arma da tiro impugnata';$results[]=$entry;continue;}
   if(!isset($state['ammo'],$state['ammo_instance'],$state['loaded'])){$reasons[]='Munizioni o caricamento non verificati per '.$entry['actor'];$results[]=$entry;continue;}
   if((int)$state['ammo']<1||!$state['loaded']){$entry['outcome']='tiro impossibile: arma scarica o senza munizioni';$results[]=$entry;continue;}
   $ammo=array_values(array_filter($inventory,static fn($i)=>(int)$i['id']===(int)$state['ammo_instance']&&rpgRangedAmmo($i,$weapon)));
   if(!$ammo||(int)$ammo[0]['durata']-(int)$ammo[0]['consumo']<(int)$state['ammo']){$reasons[]='Munizioni dichiarate non corrispondenti all’inventario';$results[]=$entry;continue;}
   $last=(int)($state['last_shot_round']??0);if($last&&(int)$g['round_number']-$last<=(int)($current['reload_rounds']??0)){$entry['outcome']='ricarica non ancora completata';$results[]=$entry;continue;}
  }
  if(!isset($state['distance'])){$reasons[]='Distanza non verificata per '.$entry['actor'];$results[]=$entry;continue;}
  if(!isset($current['max_range'])){$reasons[]='Portata non definita nella regola selezionata';$results[]=$entry;continue;}
  if((int)$state['distance']>(int)$current['max_range']){$entry['outcome']='bersaglio fuori portata';$results[]=$entry;continue;}
  if($charge&&(!isset($state['mount_instance'],$state['mount_trained'],$state['clear_space'],$current['charge_space'])||!$state['mount_trained']||(int)$state['clear_space']<(int)$current['charge_space'])){$entry['outcome']='carica non praticabile: cavalcatura o spazio non verificati';$results[]=$entry;continue;}
  if($charge){$mount=array_filter($inventory,static fn($i)=>(int)$i['id']===(int)$state['mount_instance']&&preg_match('/cavall|destrier|corsier/iu',$i['shortDesc'].' '.$i['type']));if(!$mount){$entry['outcome']='cavalcatura non posseduta';$results[]=$entry;continue;}}
  if(($d['intent']??'')==='potere'){$rules=aiGameRules((int)$actor['razza'],(bool)($actor['senziente']??0),$a['content']);$entry['racial_rules']=$rules;$reasons[]='Potere razziale da valutare secondo il manuale della stirpe effettiva';$results[]=$entry;continue;}
  if(($d['intent']??'')!=='assalto'&&($d['intent']??'')!=='attacco'){$reasons[]='Esito speciale da convalidare: '.($d['intent']??'azione');$results[]=$entry;continue;}
  if(!$automatic){$results[]=$entry;continue;}
  $targetAction=$db->query('SELECT details FROM ai_quest_actions WHERE session_id=? AND round_number=? AND kind=? AND actor_id=?',[$sid,(int)$g['round_number'],$targetKind,$targetId])[0]??[];
  $targetDetails=json_decode($targetAction['details']??'{}',true)?:[];$defence=(int)($targetDetails['roll']??$state['defence_roll']??0);if($defence<1||$defence>20){$reasons[]='Tiro difensivo non ancora registrato per '.$entry['actor'];$results[]=$entry;continue;}
  $atk=(int)$actor['forza']+(int)$d['roll'];$def=(int)$target['forza']+$defence;$damage=$atk>$def?(int)$current['damage']:0;
  $armored=array_filter(aiGameInventory($targetKind,$targetId),static fn($i)=>$i['slot']==='armatura'&&(int)$i['consumo']<(int)$i['durata']);if($armored){ $damage=max(0,$damage-(int)($current['armor_absorption']??0)); }$entry['outcome']=$damage?'successo tecnico':'difesa riuscita';$entry['calculation']=['attack'=>$atk,'defence'=>$def,'rule'=>(int)$current['knowledge_id']];
  if(aiNpcSuperDecisiveDamage((int)$target['puntif'],$damage)&&($gate=aiNpcSuperEncounterCheck($sid,$actor,$target))){$entry['specialist_encounter']=$gate;if(!$gate['allowed']){$damage=0;$entry['outcome']='il tentativo decisivo fallisce e il PNG ripiega';$reasons[]='Il PNG non dispone di un varco decisivo contro la forma speciale: il Master deve gestire il suo ripiegamento.';}}
  if($damage){ $changes[]=['type'=>'hp','kind'=>$targetKind,'id'=>$targetId,'delta'=>-$damage]; }
  if($ranged){ $changes[]=['type'=>'ammunition','kind'=>$a['kind'],'id'=>(int)$a['actor_id'],'round'=>(int)$g['round_number']]; }$results[]=$entry;
 }
 $totals=[];foreach($changes as $change){ if($change['type']==='hp'){$key=$change['kind'].':'.$change['id'];$totals[$key]=($totals[$key]??0)+(int)$change['delta'];} }foreach($totals as $key=>$delta){[$targetKind,$targetId]=explode(':',$key);$sheet=aiQuestActor($targetKind,(int)$targetId);if((int)$sheet['puntif']+$delta<1||(int)$sheet['puntif']+$delta>maxpuntif){ $reasons[]='Esito cumulativo sui PF da convalidare: morte e superamento dei limiti non sono automatici'; }}
 if($changes&&array_filter($changes,static fn($c)=>in_array($c['type'],['hp','ammunition','reload'],true)&&$c['kind']==='pg')&&empty($p['mandate'])){ $reasons[]='Manca il mandato di un Master umano per le modifiche alle schede PG'; }
 $query=implode(' ',array_column($actions,'content'));$knowledge=[];foreach(aiKnowledgeRetrieve($query,2) as $k){ $knowledge[]=['id'=>(int)$k['id'],'title'=>$k['title'],'origin'=>$k['origin'],'source'=>$k['source'],'summary'=>aiText($k['summary'],300)]; }
 $facts=[];foreach($db->query('SELECT kind,actor_id FROM ai_quest_cast WHERE session_id=? AND active=1 ORDER BY kind,actor_id',[$sid]) as $c){$sheet=aiQuestActor($c['kind'],(int)$c['actor_id']);$items=[];foreach(aiGameInventory($c['kind'],(int)$c['actor_id']) as $item){ if($item['slot']!==''){ $items[]=aiText($item['nome']?:$item['shortDesc'],40).' ('.$item['slot'].')'; } }$facts[]=['kind'=>$c['kind'],'id'=>(int)$c['actor_id'],'name'=>$sheet['name'],'hp'=>(int)($sheet['puntif']??0),'force'=>$sheet['forza']??null,'dexterity'=>null,'roles'=>$c['kind']==='pg'?aiAuthorityRoles(aiPlayerIdentity((int)$c['actor_id'])):'PNG del luogo','profile'=>aiMasterProfileSummary($sheet),'skills'=>rpgAiSkillFacts(rpg_sheet($c['kind'],(int)$c['actor_id'],false),$query),'equipment'=>array_slice($items,0,6),'wounds'=>aiText((string)($sheet['ferite']??''),100)];}
 require_once __DIR__.'/revelations.inc.php';
 return ['public_scene'=>aiSceneContext((string)$q['chat_key']),'individual_revelations'=>alphanoRevelationsMasterContext((string)$q['chat_key']),'knowledge_boundary'=>'Solo i singoli osservatori elencati conoscono la rivelazione. Non comunicarla agli altri PG o PNG.','combatants'=>$facts,'knowledge'=>$knowledge,'round'=>(int)$g['round_number'],'review'=>(bool)$reasons,'reasons'=>array_values(array_unique($reasons)),'results'=>$results,'allowed_changes'=>$changes,'can_finish'=>aiSceneCanFinish($sid),'rule'=>$current['title']??'Valutazione del Master richiesta'];
}
function aiQuestLockSheets(int $sid):void{
 $db=aiDb();$ids=$db->query('SELECT kind,actor_id FROM ai_quest_cast WHERE session_id=? ORDER BY kind,actor_id',[$sid]);foreach($ids as $p){$table=$p['kind']==='pg'?'utenti':'ai_npcs';$db->query('SELECT id FROM '.$table.' WHERE id=? FOR UPDATE',[(int)$p['actor_id']]);$db->query('SELECT id FROM equippg WHERE idpg=? ORDER BY id FOR UPDATE',[$p['kind']==='pg'?(int)$p['actor_id']:aiNpcOwner((int)$p['actor_id'])]);}
}
function aiEffectState(string $kind,int $id):array{
 $db=aiDb();if($kind==='pg'){ return $db->query('SELECT puntif,ferite,razza,infetto,ancestrale,senziente,datamorte,imprigionato FROM utenti WHERE id=?',[$id])[0]??[]; }
 $r=$db->query('SELECT profile,active FROM ai_npcs WHERE id=?',[$id])[0]??null;return $r?['profile'=>aiNpcProfile($r),'active'=>(int)$r['active'],'game'=>aiNpcGame($id)]:[];
}
/** No effect is executable from a model response: only the validated round's allowlist or a signed Master review. */
function aiQuestApplyVerdict(int $vid,int $reviewer,string $reason,?array $reviewed=null,bool $irreversible=false):void{
 $db=aiDb();if(!$db->inTransaction()){ throw new LogicException('Verdetto e conseguenze devono condividere una transazione.'); }
 $v=$db->query('SELECT * FROM ai_quest_verdicts WHERE id=? FOR UPDATE',[$vid])[0]??null;if(!$v){ throw new DomainException('Verdetto non disponibile.',404); }if($v['status']==='applied'){ return; }if($v['status']!=='review'){ throw new DomainException('Verdetto non applicabile.',409); }
 $sid=(int)$v['session_id'];$q=$db->query('SELECT * FROM ai_quests WHERE session_id=? FOR UPDATE',[$sid])[0];$s=$db->query('SELECT * FROM sessioni WHERE id=? FOR UPDATE',[$sid])[0];if(!$s['run']){ throw new DomainException('Quest terminata.',409); }aiQuestLockSheets($sid);
 if(!hash_equals($v['snapshot'],aiQuestSnapshot($sid))){ throw new DomainException('Schede o regia cambiate. Rigenera il verdetto prima di applicarlo.',409); }
 if(!$reviewer&&aiAdjudicationEnabled($sid)){aiAdjudicationApply($v,$q,$s);return;}
 $policy=json_decode($q['policy'],true)?:[];$executor=$reviewer?:((int)($policy['mandate']??0));
 if($reviewer){$u=$db->query('SELECT admin,fatepower,chat,bloccato FROM utenti WHERE id=?',[$reviewer])[0]??[];if((empty($u['admin'])&&empty($u['fatepower']))||!empty($u['bloccato'])||$u['chat']!==$s['chat']){ throw new DomainException('Convalida riservata a un Master presente nella chat.',403); }}
 $changes=$reviewed??json_decode($v['payload'],true);if(!is_array($changes)||!array_is_list($changes)||count($changes)>30){ throw new DomainException('Conseguenze non valide.',422); }
 if(!$reviewer){$technical=aiQuestTechnical($sid);if($technical['review']||aiQuestJson($changes)!==aiQuestJson($technical['allowed_changes'])){ throw new DomainException('Il verdetto necessita della convalida umana.',409); }}
 foreach($changes as $i=>$e){
  if(!is_array($e)||!in_array($e['kind']??'',['pg','npc'],true)){ throw new DomainException('Destinatario non valido.',422); }$id=aiInteger($e,'id',1,2147483647);$kind=$e['kind'];$type=aiField($e,'type',30,true);
  if(!$db->query('SELECT actor_id FROM ai_quest_cast WHERE session_id=? AND kind=? AND actor_id=?',[$sid,$kind,$id])){ throw new DomainException('Destinatario estraneo alla quest.',422); }
  $key='verdict:'.$vid.':effect:'.$i;if($db->query('SELECT id FROM ai_quest_effects WHERE effect_key=?',[$key])){ continue; }$before=aiEffectState($kind,$id);if(!$before){ throw new DomainException('Scheda non disponibile.',404); }
  if($type==='hp'){$delta=aiInteger($e,'delta',-maxpuntif+1,maxpuntif-1);$hp=$kind==='pg'?(int)$before['puntif']:(int)$before['profile']['hit_points'];$next=$hp+$delta;if($next<1||$next>maxpuntif){ throw new DomainException('PF fuori limite: la morte richiede una convalida esplicita separata.',422); }
   if($kind==='pg'){if(!setFerite($id,$next,(string)$before['ferite'],$executor,$s['chat'])){ throw new DomainException('Modifica PF non consentita.',422); }}
   else{$profile=$before['profile'];$profile['hit_points']=$next;require_once __DIR__.'/npc-mortality.inc.php';alphanoNpcAssertProfileEdit($id,$profile,(int)$before['active']);$db->execute('UPDATE ai_npcs SET profile=? WHERE id=?',[aiQuestJson($profile),$id]);}
  }elseif(in_array($type,['wound','death','prison','note','condition'],true)){
   if(!$reviewer||!$irreversible){ throw new DomainException('Conseguenza permanente: serve conferma esplicita del Master.',422); }$text=aiField($e,'text',250,true);
   if($type==='death'){if($kind==='pg'){if(!uccidi($id,$executor,aiInteger($e,'spirit',0,1),$s['chat'])){ throw new DomainException('Morte non applicabile.',422); }}else{$profile=$before['profile'];$db->execute('UPDATE ai_npcs SET active=0 WHERE id=?',[$id]);$profile['hit_points']=0;$profile['wounds']=aiText($profile['wounds'].'; '.$text,500);$db->execute('UPDATE ai_npcs SET profile=? WHERE id=?',[aiQuestJson($profile),$id]);}}
   elseif($kind==='pg'){
    if($type==='wound'){if(!setFerite($id,(int)$before['puntif'],aiText($before['ferite'].'; '.$text,255),$executor,$s['chat'])){ throw new DomainException('Ferita non registrabile.',422); }}
    elseif($type==='prison'){$value=aiInteger($e,'city_id',0,2147483647);$ok=$value?incarcera($id,$value,$executor):scarcera($id,$executor);if(!$ok){ throw new DomainException('Città di detenzione non valida.',422); }insertNote($id,$executor,$text);}
    else { insertNote($id,$executor,$text); }
   }else{$profile=$before['profile'];$field=$type==='wound'?'wounds':'notes';$profile[$field]=aiText($profile[$field]."\n".$text,$field==='wounds'?500:3000);$db->execute('UPDATE ai_npcs SET profile=? WHERE id=?',[aiQuestJson($profile),$id]);$g=aiNpcGame($id);$g['conditions']=aiText($g['conditions'].'; '.$text,1500);$db->execute('INSERT INTO aiNpcGame(npc_id,settings,updated_by,updated_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE settings=VALUES(settings),version=version+1,updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$id,aiQuestJson($g),$reviewer,time()]);}
  }elseif(in_array($type,['ammunition','reload'],true)){
   $c=$db->query('SELECT state FROM ai_quest_cast WHERE session_id=? AND kind=? AND actor_id=? FOR UPDATE',[$sid,$kind,$id])[0];$state=json_decode($c['state'],true)?:[];
   $owner=$kind==='pg'?$id:aiNpcOwner($id);$ammoItem=$db->query('SELECT p.*,e.shortDesc,e.durata,t.nome AS type FROM equippg p JOIN equip e ON e.id=p.uid LEFT JOIN tipo t ON t.id=e.tipo WHERE p.id=? AND p.idpg=? FOR UPDATE',[(int)($state['ammo_instance']??0),$owner])[0]??null;
   $held=rpgDeclaredWeapon(aiGameInventory($kind,$id),'',true);
   if(!$ammoItem||!$held||!rpgRangedAmmo($ammoItem,$held)){ throw new DomainException('Munizioni effettive non disponibili.',409); }
   $before=['cast'=>$state,'ammo_item'=>array_intersect_key($ammoItem,array_flip(['id','uid','idpg','slot','nome','consumo','idpadre']))];
   if($type==='reload'){if(!isset($policy['reload_rounds'])||(int)$v['round_number']-(int)($state['last_shot_round']??0)<(int)$policy['reload_rounds']){ throw new DomainException('Tempo di ricarica non trascorso o non configurato.',409); }$state['loaded']=true;}
   else{if(empty($state['loaded'])||(int)($state['ammo']??0)<1){ throw new DomainException('Arma già scarica o senza munizioni.',409); }$state['ammo']--;
   if($kind==='pg'){if(!consumaMaster($id,(int)$state['ammo_instance'],1,$executor,$s['chat'],['request_key'=>$key])){ throw new DomainException('Munizioni non consumabili.',422); }}elseif(!consuma($owner,(int)$state['ammo_instance'],1)){ throw new DomainException('Munizioni non consumabili.',422); }$state['loaded']=false;$state['last_shot_round']=aiInteger($e,'round',1,PHP_INT_MAX);}
   $db->execute('UPDATE ai_quest_cast SET state=? WHERE session_id=? AND kind=? AND actor_id=?',[aiQuestJson($state),$sid,$kind,$id]);
  }elseif($type==='money'){
   if(!$reviewer||$kind!=='pg'){ throw new DomainException('Trasferimento da convalidare tra un PG e un PNG.',422); }$npc=aiInteger($e,'npc_id',1,2147483647);if(!$db->query('SELECT actor_id FROM ai_quest_cast WHERE session_id=? AND kind=\'npc\' AND actor_id=?',[$sid,$npc])){ throw new DomainException('PNG estraneo alla quest.',422); }
   $amount=aiInteger($e,'amount',1,1000000000);$direction=aiField($e,'direction',10,true);if(!in_array($direction,['to_npc','to_player'],true)){ throw new DomainException('Direzione non valida.',422); }
   $before=['money'=>['npc'=>$npc,'player'=>$id,'npc_balance'=>aiNpcBalance($npc),'player_balance'=>(int)$db->query('SELECT denari FROM utenti WHERE id=?',[$id])[0]['denari']]];
   $tx=aiMoneyTransfer($npc,$id,$direction==='to_npc'?'theft_resolved':'withdraw',$amount,$reason,$key,['actor'=>$reviewer]);$before['money']['transaction_id']=(int)$tx['id'];
  }elseif($type==='item'){
   if(!$reviewer){ throw new DomainException('Trasferimento oggetti da convalidare.',422); }$instance=aiInteger($e,'instance',1,PHP_INT_MAX);$fromKind=aiField($e,'from_kind',3,true);$from=aiInteger($e,'from_id',1,2147483647);if(!in_array($fromKind,['pg','npc'],true)||!$db->query('SELECT actor_id FROM ai_quest_cast WHERE session_id=? AND kind=? AND actor_id=?',[$sid,$fromKind,$from])){ throw new DomainException('Proprietario estraneo alla quest.',422); }
   $owner=$fromKind==='pg'?$from:aiNpcOwner($from);$row=$db->query('SELECT * FROM equippg WHERE id=? AND idpg=? FOR UPDATE',[$instance,$owner])[0]??null;if(!$row||$db->query('SELECT id FROM equippg WHERE idpadre=?',[$instance])){ throw new DomainException('Oggetto non disponibile o contenitore non vuoto.',422); }$before=['item'=>$row];$db->execute("UPDATE equippg SET idpg=?,slot='',idpadre=0 WHERE id=?",[$kind==='pg'?$id:aiNpcOwner($id),$instance]);
  }else { throw new DomainException('Tipo di conseguenza non autorizzato.',422); }
  $after=match($type){'ammunition','reload'=>['cast'=>json_decode($db->query('SELECT state FROM ai_quest_cast WHERE session_id=? AND kind=? AND actor_id=?',[$sid,$kind,$id])[0]['state'],true),'ammo_item'=>$db->query('SELECT * FROM equippg WHERE id=?',[(int)$before['ammo_item']['id']])[0]??null],'item'=>['item'=>$db->query('SELECT * FROM equippg WHERE id=?',[$instance])[0]],'money'=>['money'=>['transaction_id'=>(int)$tx['id'],'npc'=>$npc,'player'=>$id,'npc_balance'=>aiNpcBalance($npc),'player_balance'=>(int)$db->query('SELECT denari FROM utenti WHERE id=?',[$id])[0]['denari']]],default=>aiEffectState($kind,$id)};
  $db->execute('INSERT INTO ai_quest_effects(verdict_id,effect_key,effect_type,target_kind,target_id,before_state,after_state,actor_id,reason,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',[$vid,$key,$type,$kind,$id,aiQuestJson($before),aiQuestJson($after),$executor,aiText($reason,500),time()]);
  if($kind==='npc'&&$type==='death'){require_once __DIR__.'/npc-mortality.inc.php';alphanoNpcRecordEffectDeath($key,$e,$reviewer);}
  aiQuestAudit($sid,'effect_applied',['effect'=>$e,'before'=>$before,'after'=>$after],$executor);
  // Verified consequences become durable pair memories, not model-invented facts.
  if(!($kind==='pg'&&in_array($type,['note','condition'],true))){
   $players=$db->query('SELECT actor_id FROM ai_quest_cast WHERE session_id=? AND kind=\'pg\' AND active=1',[$sid]);$npcs=$db->query('SELECT actor_id FROM ai_quest_cast WHERE session_id=? AND kind=\'npc\' AND active=1',[$sid]);
   foreach($players as $pg){ foreach($npcs as $n){
    if($type==='money'&&((int)$n['actor_id']!==(int)$e['npc_id']||(int)$pg['actor_id']!==$id)){ continue; }
    if($kind==='npc'&&in_array($type,['note','condition'],true)&&(int)$n['actor_id']!==$id){ continue; }
    $detail=match($type){'wound'=>aiField($e,'text',250),'death'=>'morte convalidata','prison'=>'condizione di detenzione aggiornata','money'=>'scambio convalidato tra i due interlocutori','item'=>'passaggio di un oggetto convalidato','hp'=>'condizione fisica aggiornata','ammunition'=>'tiro e consumo di una munizione','reload'=>'ricarica verificata',default=>'annotazione pubblica del personaggio aggiornata'};
    $summary='Quest #'.$sid.', giro '.$v['round_number'].': '.aiQuestName($kind,$id).' — '.$detail.'. Le motivazioni riservate del Master non sono conoscenze del PNG.';
    $db->execute("INSERT INTO ai_personal_memories(npc_id,player_id,chat_key,category,summary,evidence,source,created_at,updated_at) VALUES(?,?,?,'quest',?,?,'system',?,?)",[(int)$n['actor_id'],(int)$pg['actor_id'],$s['chat'],$summary,'effect:'.$key,time(),time()]);
   }
   }
  }
 }
 $db->execute("UPDATE ai_quest_verdicts SET status='applied',payload=?,reviewed_by=? WHERE id=?",[aiQuestJson($changes),$executor,$vid]);aiQuestAudit($sid,'verdict_applied',['verdict'=>$vid,'effects'=>count($changes),'reason'=>$reason],$executor);
 aiQuestPublish($sid,'verdict:'.$vid.':published','Verdetto · giro '.$v['round_number']."\n".aiVerdictPublicText($v),'<div class="MasterKey">'.aiVerdictPublicHtml($v).'</div>');
 aiNpcLastActions($v);
 aiQuestNextRound($sid,$executor,!empty(json_decode($v['technical'],true)['scene_finished']));
}
/** Shared progression after a validated verdict or an explicitly effect-free narrative round. */
function aiQuestNextRound(int $sid,int $executor=0,bool $finish=false):void{
 $db=aiDb();if(!$db->inTransaction()){ throw new LogicException('Avanzamento fuori transazione.'); }
 $s=$db->query('SELECT * FROM sessioni WHERE id=? FOR UPDATE',[$sid])[0];$q=$db->query('SELECT * FROM ai_quests WHERE session_id=? FOR UPDATE',[$sid])[0];$policy=json_decode($q['policy'],true)?:[];
 if($finish&&aiSceneCanFinish($sid)){aiSceneClose($sid,'Il Master IA ha riconosciuto la conclusione della scena dopo il giro completo.',$executor);return;}
 $g=aiGameGuidance($sid);$order=json_decode($g['turn_order'],true)?:[];
 $order=array_values(array_filter($order,static function($member)use($db,$sid){$c=$db->query('SELECT active,state FROM ai_quest_cast WHERE session_id=? AND kind=? AND actor_id=?',[$sid,$member['kind'],(int)$member['id']])[0]??[];return !empty($c['active'])&&empty(json_decode($c['state']??'{}',true)['withdrew']);}));
 $remaining=['pg'=>0,'npc'=>0];foreach($order as $member){$sheet=aiQuestActor($member['kind'],(int)$member['id']);if($sheet&&$sheet['chat']===$s['chat']&&($sheet['puntif']??0)>0&&($member['kind']==='pg'||!empty($sheet['active']))){ $remaining[$member['kind']]++; }}
 if(!$remaining['pg']||(aiSceneKind($q)!=='narrazione'&&(($policy['encounter']??'npc')==='pvp'?array_sum($remaining)<2:!$remaining['npc']))){aiSceneClose($sid,'Non restano partecipanti attivi della scena nel luogo.',$executor);return;}
 foreach($db->query('SELECT kind,actor_id,state FROM ai_quest_cast WHERE session_id=? AND active=0',[$sid]) as $c){$state=json_decode($c['state'],true)?:[];if(empty($state['dead'])&&empty($state['withdrew'])&&(int)($state['join_round']??PHP_INT_MAX)<=(int)$g['round_number']+1){$order[]=['kind'=>$c['kind'],'id'=>(int)$c['actor_id']];$db->execute('UPDATE ai_quest_cast SET active=1 WHERE session_id=? AND kind=? AND actor_id=?',[$sid,$c['kind'],(int)$c['actor_id']]);}}
 if(($policy['initiative']??'entry')==='force'){ usort($order,static fn($a,$b)=>(int)(aiQuestActor($b['kind'],$b['id'])['forza']??0)<=>(int)(aiQuestActor($a['kind'],$a['id'])['forza']??0)); }
 $room=aiRoom($s['chat']);if(aiSceneKind($q)!=='narrazione'&&($room['quest_rules']??'legacy')==='alphano_scene_v1'&&empty($policy['knowledge_id'])&&($policy['resolution']??'')!=='alphano_scene_v1'){$policy['resolution']='alphano_scene_v1';$db->execute('UPDATE ai_quests SET policy=? WHERE session_id=?',[aiQuestJson($policy),$sid]);}
 $db->execute('UPDATE ai_session_guidance SET turn_order=?,turn_index=0,turn_started_at=?,round_number=round_number+1,version=version+1,updated_at=? WHERE session_id=?',[aiQuestJson($order),time(),time(),$sid]);$db->execute("UPDATE ai_quests SET status='active',version=version+1 WHERE session_id=?",[$sid]);aiQuestSchedule(aiQuestRow($s['chat'],true));
 aiQuestPublishQueue($sid,'quest:'.$sid.':round:'.((int)$g['round_number']+1));
}
function aiQuestReverseEffect(int $id,int $reviewer,string $reason):void{
 $db=aiDb();$e=$db->query('SELECT e.*,v.session_id FROM ai_quest_effects e JOIN ai_quest_verdicts v ON v.id=e.verdict_id WHERE e.id=? FOR UPDATE',[$id])[0]??null;if(!$e){ throw new DomainException('Conseguenza non trovata.',404); }if($e['status']==='reverted'){ return; }
 $before=json_decode($e['before_state'],true);$after=json_decode($e['after_state'],true);$kind=$e['target_kind'];$target=(int)$e['target_id'];$type=$e['effect_type']==='disarm'?'item':$e['effect_type'];
 if($type==='death'&&$kind==='pg'&&$db->query("SELECT player_id FROM lineage_members WHERE player_id=? AND status='eliminated'",[$target])){ throw new DomainException('Questa morte coinvolge una stirpe: non è possibile annullare soltanto il singolo personaggio. Usa una rettifica coordinata della discendenza.',409); }
 if($type==='scene'){$current=json_decode($db->query('SELECT state FROM ai_quest_cast WHERE session_id=? AND kind=? AND actor_id=? FOR UPDATE',[(int)$e['session_id'],$kind,$target])[0]['state'],true);if(aiQuestJson($current)!==aiQuestJson($after['cast'])){ throw new DomainException('La condizione tattica è cambiata: non può essere sovrascritta.',409); }$db->execute('UPDATE ai_quest_cast SET state=? WHERE session_id=? AND kind=? AND actor_id=?',[aiQuestJson($before['cast']),(int)$e['session_id'],$kind,$target]);$db->execute("UPDATE ai_quest_effects SET status='reverted' WHERE id=?",[$id]);aiQuestAudit((int)$e['session_id'],'scene_reverted',['effect'=>$id,'reason'=>$reason],$reviewer);return;}
 if(in_array($type,['note','condition'],true)&&$kind==='pg') {insertNote($target,$reviewer,'Rettifica della nota del verdetto #'.$e['verdict_id'].': '.$reason);$db->execute("UPDATE ai_quest_effects SET status='reverted' WHERE id=?",[$id]);aiQuestAudit((int)$e['session_id'],'note_corrected',['effect'=>$id,'reason'=>$reason],$reviewer);aiQuestCorrectEffectMemory($e);return;}
 if($type==='money'){$original=$db->query('SELECT * FROM ai_npc_transactions WHERE id=? FOR UPDATE',[(int)$after['money']['transaction_id']])[0];$key='reversal:'.$original['id'];aiMoneyTransfer((int)$original['npc_id'],(int)$original['player_id'],(int)$original['player_delta']>0?'reversal_to_npc':'reversal_to_player',(int)$original['amount'],'Storno effetto #'.$id.': '.$reason,$key,['actor'=>$reviewer]);$db->execute("UPDATE ai_quest_effects SET status='reverted' WHERE id=?",[$id]);aiQuestAudit((int)$e['session_id'],'money_reversed',['effect'=>$id,'reason'=>$reason],$reviewer);aiQuestCorrectEffectMemory($e);return;}
 if($type==='item'){$current=['item'=>$db->query('SELECT * FROM equippg WHERE id=? FOR UPDATE',[(int)$after['item']['id']])[0]??null];}
 elseif(in_array($type,['ammunition','reload'],true)){$current=['cast'=>json_decode($db->query('SELECT state FROM ai_quest_cast WHERE session_id=? AND kind=? AND actor_id=? FOR UPDATE',[(int)$e['session_id'],$kind,$target])[0]['state']??'{}',true),'ammo_item'=>$db->query('SELECT * FROM equippg WHERE id=? FOR UPDATE',[(int)$before['ammo_item']['id']])[0]??null];}
 else{$db->query('SELECT id FROM '.($kind==='pg'?'utenti':'ai_npcs').' WHERE id=? FOR UPDATE',[$target]);$current=aiEffectState($kind,$target);}
 if($type==='infection'&&array_key_exists('lineage_edge',$after)){
  $current['lineage_edge']=$db->query("SELECT * FROM lineage_members WHERE player_id=? AND kind='lycan' FOR UPDATE",[$target])[0]??null;
  if($db->query("SELECT player_id FROM lineage_members WHERE creator_id=? AND kind='lycan'",[$target])){ throw new DomainException('La discendenza è già proseguita: il contagio non può essere annullato isolatamente.',409); }
 }
 if(aiQuestJson($current)!==aiQuestJson($after)){ throw new DomainException('Lo stato è cambiato dopo questo effetto: correggi dal più recente, senza sovrascrivere eventi successivi.',409); }
 if($type==='infection'&&array_key_exists('lineage_edge',$before)){
  if($before['lineage_edge']===null){ $db->execute("DELETE FROM lineage_members WHERE player_id=? AND kind='lycan'",[$target]); }
  unset($before['lineage_edge']);
 }
 if($type==='item'){ $db->execute('UPDATE equippg SET idpg=?,slot=?,idpadre=? WHERE id=?',[$before['item']['idpg'],$before['item']['slot'],$before['item']['idpadre'],$before['item']['id']]); }
 elseif(in_array($type,['ammunition','reload'],true)){$db->execute('UPDATE ai_quest_cast SET state=? WHERE session_id=? AND kind=? AND actor_id=?',[aiQuestJson($before['cast']),$e['session_id'],$kind,$target]);$a=$before['ammo_item'];if($current['ammo_item']){ $db->execute('UPDATE equippg SET consumo=? WHERE id=?',[$a['consumo'],$a['id']]); }else { $db->execute('INSERT INTO equippg(id,uid,idpg,slot,nome,consumo,idpadre) VALUES(?,?,?,?,?,?,?)',[$a['id'],$a['uid'],$a['idpg'],$a['slot'],$a['nome'],$a['consumo'],$a['idpadre']]); }}
 elseif($kind==='pg'){$sets=[];$values=[];foreach($before as $k=>$v){$sets[]=$k.'=?';$values[]=$v;}$db->execute('UPDATE utenti SET '.implode(',',$sets).' WHERE id=?',[...$values,$target]);}
 else{$db->execute('UPDATE ai_npcs SET profile=?,active=? WHERE id=?',[aiQuestJson($before['profile']),(int)$before['active'],$target]);$db->execute('UPDATE aiNpcGame SET settings=?,version=version+1,updated_by=?,updated_at=? WHERE npc_id=?',[aiQuestJson($before['game']),$reviewer,time(),$target]);}
 $db->execute("UPDATE ai_quest_effects SET status='reverted' WHERE id=?",[$id]);
 if($kind==='npc'&&$type==='death'){require_once __DIR__.'/npc-mortality.inc.php';alphanoNpcRevertDeath($e['effect_key'],$reviewer,$reason);}
 aiQuestAudit((int)$e['session_id'],'effect_reverted',['id'=>$id,'reason'=>$reason],$reviewer);
 aiQuestCorrectEffectMemory($e);
}

function aiQuestCorrectEffectMemory(array $e):void{
 $db=aiDb();$id=(int)$e['id'];
 $db->execute("UPDATE ai_personal_memories SET active=0 WHERE source='system' AND evidence=?",['effect:'.$e['effect_key']]);
 foreach($db->query("SELECT DISTINCT npc_id,player_id,chat_key FROM ai_personal_memories WHERE source='system' AND evidence=?",['effect:'.$e['effect_key']]) as $m){ $db->execute("INSERT INTO ai_personal_memories(npc_id,player_id,chat_key,category,summary,evidence,source,created_at,updated_at) VALUES(?,?,?,'rettifica',?,?,'system',?,?)",[$m['npc_id'],$m['player_id'],$m['chat_key'],'Rettifica verificata della conseguenza #'.$id.': lo stato precedente non è più attuale. Considera soltanto la condizione corretta, senza dedurre dettagli riservati.','effect-reverse:'.$id,time(),time()]); }
}
