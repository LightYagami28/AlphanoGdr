<?php
declare(strict_types=1);

if (!defined('S1192_LIBS_SESSION_CONTROL_INC_PHP_84E933_CONTROL')) { define('S1192_LIBS_SESSION_CONTROL_INC_PHP_84E933_CONTROL', ':control:'); }
require_once __DIR__.'/ai.inc.php';
require_once __DIR__.'/operations.inc.php';
require_once __DIR__.'/session-context.inc.php';

function alphanoSessionGuard(string $chat):string {return alphanoLockName('session',$chat,(string)alphanoConfig('db_db'));}

function alphanoSessionControlReady():bool {
    static $ready=null;return $ready??=(bool)aiDb()->query("SHOW COLUMNS FROM sessioni LIKE 'control_version'");
}
function alphanoSessionActor(int $uid,string $chat,bool $present=true):array {
    $u=aiDb()->query('SELECT id,nome,admin,fatepower,bloccato,chat,lastmove FROM utenti WHERE id=?',[$uid])[0]??[];
    if(!$u||!empty($u['bloccato'])||(empty($u['admin'])&&empty($u['fatepower']))){ throw new DomainException('Accesso riservato ai Master autorizzati.',403); }
    if(($present||empty($u['admin']))&&$u['chat']!==$chat){ throw new DomainException('Entra in questo luogo per dirigere la sessione.',403); }
    if(!aiDb()->query("SELECT chat_key FROM mappe WHERE chat_key=? AND chat=1",[$chat])){ throw new DomainException('Luogo pubblico non disponibile.',404); }
    return $u;
}
function alphanoSessionCurrent(string $chat,bool $lock=false):?array {
    return aiDb()->query('SELECT * FROM sessioni WHERE chat=? AND run=1 ORDER BY id DESC LIMIT 1'.($lock?' FOR UPDATE':''),[$chat])[0]??null;
}
function alphanoSessionOwner(array $session,?array $quest=null):int {
    $quest??=aiDb()->query('SELECT mode,conductor_id FROM ai_quests WHERE session_id=?',[(int)$session['id']])[0]??null;
    if($quest&&$quest['mode']==='auto'){ return 0; }
    return (int)($quest['conductor_id']??0)?:((int)($session['conductor_id']??0)?:(int)$session['masterin']);
}
function alphanoSessionCanManage(int $uid,string $chat):bool {
    try{$u=alphanoSessionActor($uid,$chat);$s=alphanoSessionCurrent($chat);return !empty($u['admin'])||($s&&alphanoSessionOwner($s)===$uid);}
    catch(DomainException){return false;}
}
function alphanoSessionRequireManager(int $uid,string $chat):array {
    $u=alphanoSessionActor($uid,$chat);$s=alphanoSessionCurrent($chat);
    if(!empty($u['admin'])){ return $s??['id'=>0,'chat'=>$chat,'partecipanti'=>'']; }
    if(!$s){ throw new DomainException('Apri una sessione prima di intervenire sui partecipanti.',409); }
    if(alphanoSessionOwner($s)!==$uid){ throw new DomainException('Assumi prima la regia oppure chiedi il passaggio al Master che dirige.',403); }
    return $s;
}
function alphanoSessionTurnCandidates(string $chat,int $exclude=0):array {
    $out=[];
    foreach(aiDb()->query('SELECT id,nome FROM utenti WHERE chat=? AND bloccato=0 AND puntif>0 ORDER BY nome',[$chat]) as $p)
        { if((int)$p['id']!==$exclude){ $out[]=['kind'=>'pg','id'=>(int)$p['id'],'name'=>$p['nome']]; } }
    foreach(aiDialogueNpcs($chat,0) as $n){ if((int)aiNpcProfile($n)['hit_points']>0){ $out[]=['kind'=>'npc','id'=>(int)$n['id'],'name'=>$n['name']]; } }
    return $out;
}
/** Operational projection only: never serialize policies, prompts, private memories or rules. */
function alphanoSessionControlState(int $uid,string $chat):array {
    $u=alphanoSessionActor($uid,$chat,false);$s=alphanoSessionCurrent($chat);$q=$s?(aiDb()->query('SELECT status,mode,conductor_id FROM ai_quests WHERE session_id=?',[(int)$s['id']])[0]??null):null;
    $owner=$s?alphanoSessionOwner($s,$q):0;$g=$s?aiGameGuidance((int)$s['id']):null;$turn=$g?aiGameTurn($g):null;
    $name=$owner?(aiDb()->query('SELECT nome FROM utenti WHERE id=?',[$owner])[0]['nome']??'Master non disponibile'):($s&&$q&&$q['mode']==='auto'?'Master IA':'—');
    $masters=aiDb()->query('SELECT id,nome AS name FROM utenti WHERE chat=? AND bloccato=0 AND (admin=1 OR fatepower=1) AND id<>? AND lastmove>? ORDER BY nome',[$chat,$uid,time()-900]);
    $order=[];foreach($g?json_decode($g['turn_order'],true)?:[]:[] as $entry){ $order[]=[...$entry,'name'=>aiQuestName($entry['kind'],(int)$entry['id'])]; }
    return ['chat_key'=>$chat,'session'=>$s?['id'=>(int)$s['id'],'nome'=>$s['nome'],'locked'=>(int)$s['lock'],'dadi'=>(int)$s['dadi']]:null,
        'quest'=>$q?['status'=>$q['status'],'mode'=>$q['mode'],'conductor_id'=>(int)$q['conductor_id']]:null,
        'conductor'=>['id'=>$owner,'name'=>$name,'mode'=>$q['mode']??'human'],'version'=>(int)($s['control_version']??1),
        'can_manage'=>alphanoSessionCanManage($uid,$chat),'can_takeover'=>$s&&$u['chat']===$chat&&(($q&&$q['mode']==='auto')||(!empty($u['admin'])&&$owner!==$uid)),
        'can_open'=>!$s&&$u['chat']===$chat,'ai_available'=>(bool)(aiRoom($chat)['enabled']??false),
        'masters'=>array_map(static fn($m)=>['id'=>(int)$m['id'],'name'=>$m['name']],$masters),
        'round'=>$g?(int)$g['round_number']:null,'turn'=>$turn?aiQuestName($turn['kind'],(int)$turn['id']):null,
        'turn_order'=>$order,'guidance_version'=>(int)($g['version']??0),'turn_candidates'=>alphanoSessionTurnCandidates($chat,$owner?:$uid)];
}
/** Include every current public PG turn independently of whether AI was enabled. */
function alphanoSessionCaptureMessage(string $chat,int $source,int $uid,string $raw,int $dest):void {
    if($dest!==0||!alphanoSessionControlReady()){ return; }$s=alphanoSessionCurrent($chat);if(!$s){ return; }
    $u=aiDb()->query('SELECT nome,admin,fatepower FROM utenti WHERE id=?',[$uid])[0]??[];
    $master=str_starts_with(ltrim($raw),'#')&&(!empty($u['admin'])||!empty($u['fatepower']));
    if($master&&alphanoSessionOwner($s)!==$uid&&empty($u['admin'])){ return; }
    $reading=aiNarrativeParse($raw);$text=$master?aiText(ltrim($raw,"# \t"),6000):trim(($reading['action']!==''?'Gesti: '.$reading['action'].'. ':'').($reading['speech']!==''?'Parole: '.$reading['speech']:''));
    if($text===''){ return; }
    aiDb()->execute('INSERT IGNORE INTO session_public_context(session_id,source_id,actor_kind,actor_id,speaker,content,created_at) VALUES(?,?,?,?,?,?,?)',[(int)$s['id'],$source,$master?'master':'pg',$uid,(string)($u['nome']??'Personaggio'),aiText($text,6000),time()]);
}

/** Turn-list validation is shared by handoff and the human manager. */
function alphanoSessionValidateTurns(mixed $value,string $chat,int $owner):array {
    if(is_string($value)){try{$value=json_decode($value,true,32,JSON_THROW_ON_ERROR);}catch(JsonException){throw new DomainException('Ordine dei turni non valido.',422);}}
    if(!is_array($value)||!array_is_list($value)||count($value)>60){ throw new DomainException('Puoi ordinare fino a 60 partecipanti.',422); }
    $present=[];foreach(alphanoSessionTurnCandidates($chat,$owner) as $m){ $present[$m['kind'].':'.$m['id']]=true; }
    $seen=[];$out=[];foreach($value as $m){if(!is_array($m)||!in_array($m['kind']??'',['pg','npc'],true)||filter_var($m['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false){ throw new DomainException('Partecipante non valido.',422); }
        $id=(int)$m['id'];$key=$m['kind'].':'.$id;if(isset($seen[$key])||!isset($present[$key])){ throw new DomainException('Un partecipante è duplicato, assente o non può agire.',409); }$seen[$key]=true;$out[]=['kind'=>$m['kind'],'id'=>$id];}
    return $out;
}
function alphanoSessionInvalidateWork(string $chat,int $sid,string $reason):void {
    aiDb()->execute("UPDATE ai_jobs SET status='cancelled',finished_at=?,error=? WHERE status IN('queued','running') AND (session_id=? OR (chat_key=? AND kind IN('play','observe','reaction')))",[time(),$reason,$sid,$chat]);
}
function alphanoSessionStoreGuidance(int $sid,array $order,int $uid,bool $paused=false):void {
    aiDb()->execute("INSERT INTO ai_session_guidance(session_id,notes,phase,paused,turn_order,turn_started_at,updated_by,updated_at) VALUES(?,'','narrazione',?,?,?,?,?) ON DUPLICATE KEY UPDATE turn_order=VALUES(turn_order),turn_index=0,paused=VALUES(paused),turn_started_at=VALUES(turn_started_at),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at),version=version+1",[$sid,$paused?1:0,aiQuestJson($order),time(),$uid,time()]);
}

/** The only public lifecycle mutation route; the chat lock also fences human messages. */
function alphanoSessionControlAction(int $uid,string $chat,string $action,array $in):array {
    $u=alphanoSessionActor($uid,$chat);if(!alphanoSessionControlReady()){ throw new DomainException('Il pannello è in aggiornamento.',503); }
    if(!in_array($action,['crea','chiudi','cambia','dadi','takeover','ai','transfer','turns','next'],true)){ throw new DomainException('Comando della sessione non valido.',422); }
    $db=aiDb();$guard=alphanoSessionGuard($chat);
    if(!(int)($db->query('SELECT GET_LOCK(?,5) locked',[$guard])[0]['locked']??0)){ throw new DomainException('La regia sta aggiornando la scena. Riprova.',409); }
    try{
        $db->beginTransaction();$db->query('SELECT chat_key FROM ai_rooms WHERE chat_key=? FOR UPDATE',[$chat]);$s=alphanoSessionCurrent($chat,true);
        $key=$in['request_key']??$in['request_id']??null;if($key!==null&&!is_string($key)){ throw new DomainException('Identificativo operazione non valido.',422); }
        $payload=$in;unset($payload['_csrf'],$payload['csrfToken'],$payload['request_key'],$payload['request_id']);
        if(!alphanoOperationOnce('session-control-'.$action,$uid,$key,[$chat,$payload])){$db->commit();return [...alphanoSessionControlState($uid,$chat),'replayed'=>true];}
        if($action!=='crea'){
            if(!$s){ throw new DomainException('Nessuna sessione aperta.',409); }
            if(isset($in['session_id'])&&(int)$in['session_id']!==(int)$s['id']){ throw new DomainException('La sessione è cambiata. Ricarica il pannello.',409); }
            if(isset($in['version'])&&(int)$in['version']!==(int)$s['control_version']){ throw new DomainException('La regia è cambiata in un altro pannello. Ricarica prima di procedere.',409); }
            if($action==='takeover'){
                $q=aiQuestRow($chat,true);if((!$q||$q['mode']!=='auto')&&alphanoSessionOwner($s)!==$uid&&empty($u['admin'])){ throw new DomainException('La sessione appartiene a un altro Master: chiedigli il passaggio.',403); }
            }else { alphanoSessionRequireManager($uid,$chat); }
        }
        if($action==='crea'){
            if($s){ throw new DomainException('Una sessione è già aperta in questo luogo.',409); }
            $title=$in['nomeSessione']??'';if(!is_string($title)||trim($title)===''||mb_strlen($title)>255){ throw new DomainException('Scrivi un titolo valido per la sessione.',422); }
            $place=$db->query('SELECT title FROM mappe WHERE chat_key=?',[$chat])[0]['title'];
            $boundary=(int)($db->query('SELECT COALESCE(MAX(id),0) id FROM chat WHERE chat=?',[$chat])[0]['id']??0);
            $db->execute('INSERT INTO sessioni(chat,nomechat,nome,run,`lock`,dadi,partecipanti,datain,masterin,conductor_id,start_source_id) VALUES(?,?,?,1,?,?,?,?,?,?,?)',[$chat,$place,trim($title),!empty($in['lock'])?1:0,!empty($in['dadi'])?1:0,';'.$uid.';',time(),$uid,$uid,$boundary]);
            $sid=(int)$db->lastInsertId();alphanoSessionStoreGuidance($sid,[],$uid,true);alphanoSessionInvalidateWork($chat,$sid,'Il Master umano ha aperto la sessione.');
            alphanoNotifyQuest($sid,trim($title),$chat,$place,time());aiQuestPublish($sid,'session:'.$sid.':opened','Il Master '.$u['nome'].' apre la sessione «'.trim($title).'». La regia è umana.');
            aiQuestAudit($sid,'human_session_opened',['name'=>trim($title)],$uid);
        }else{
            $sid=(int)$s['id'];$owner=alphanoSessionOwner($s);$q=aiQuestRow($chat,true);$nextVersion=(int)$s['control_version']+1;
            if($action==='chiudi'){
                if($q){ aiSceneClose($sid,'Sessione conclusa dal Master '.$u['nome'],$uid); }
                else{$db->execute('UPDATE sessioni SET run=0,dataout=?,masterout=? WHERE id=?',[time(),$uid,$sid]);aiQuestPublish($sid,'session:'.$sid.':closed','Il Master '.$u['nome'].' conclude la sessione «'.$s['nome'].'». Il resoconto resta nell’archivio.');aiQuestAudit($sid,'human_session_closed',[],$uid);}
                alphanoSessionInvalidateWork($chat,$sid,'Sessione conclusa dal Master.');
            }elseif(in_array($action,['cambia','dadi'],true)){
                $field=$action==='cambia'?'lock':'dadi';$db->execute('UPDATE sessioni SET `'.$field.'`=1-`'.$field.'` WHERE id=?',[$sid]);aiQuestAudit($sid,'session_options_changed',['field'=>$field,'value'=>1-(int)$s[$field]],$uid);
            }elseif($action==='transfer'){
                $target=filter_var($in['master_id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($target===false||$target===$owner){ throw new DomainException('Seleziona un altro Master presente.',422); }
                $to=$db->query('SELECT id,nome FROM utenti WHERE id=? AND chat=? AND bloccato=0 AND (admin=1 OR fatepower=1) AND lastmove>? FOR UPDATE',[$target,$chat,time()-900])[0]??null;
                if(!$to){ throw new DomainException('Il Master destinatario deve essere presente e collegato in questo luogo.',409); }
                alphanoSessionSetHuman($s,$q,(int)$target,$uid);aiQuestPublish($sid,'session:'.$sid.S1192_LIBS_SESSION_CONTROL_INC_PHP_84E933_CONTROL.$nextVersion,$u['nome'].' affida la regia a '.$to['nome'].'. La sessione prosegue senza perdere la cronaca.');
                aiQuestAudit($sid,'master_transferred',['from'=>$owner,'to'=>(int)$target],$uid);
            }elseif($action==='takeover'){
                alphanoSessionSetHuman($s,$q,$uid,$uid);aiQuestPublish($sid,'session:'.$sid.S1192_LIBS_SESSION_CONTROL_INC_PHP_84E933_CONTROL.$nextVersion,$u['nome'].' assume la regia umana della sessione. Gli interventi automatici della scena sono sospesi.');aiQuestAudit($sid,'human_takeover',['from'=>$owner],$uid);
            }elseif($action==='ai'){
                alphanoSessionHandoffAi($s,$q,$uid);
            }elseif($action==='turns'){
                if($q&&$q['mode']==='auto'){ throw new DomainException('Assumi la regia per modificare l’ordine dei turni.',409); }
                $g=aiGameGuidance($sid);if(isset($in['guidance_version'])&&(int)$in['guidance_version']!==(int)$g['version']){ throw new DomainException('I turni sono cambiati: ricarica il pannello.',409); }
                if($q&&(int)$g['turn_index']>0){ throw new DomainException('Il giro è già iniziato. Concludilo prima di cambiare l’ordine.',409); }
                $order=alphanoSessionValidateTurns($in['turn_order']??[],$chat,$owner);alphanoSessionStoreGuidance($sid,$order,$uid,!$q);if($q){ alphanoSessionSyncCast($sid,$order); }alphanoSessionInvalidateWork($chat,$sid,'Ordine dei turni aggiornato dal Master.');
                aiQuestAudit($sid,'human_turn_order',['order'=>$order],$uid);if($q){ aiQuestSchedule(aiQuestRow($chat,true)); }
            }elseif($action==='next'){
                if($q&&$q['mode']==='auto'){ throw new DomainException('La turnazione è attualmente affidata all’IA.',409); }
                $g=aiGameGuidance($sid);$order=json_decode($g['turn_order'],true)?:[];if(!$order){ throw new DomainException('Configura un ordine di gioco prima di passare il turno.',409); }
                if($q&&$q['status']==='review'){ throw new DomainException('Il giro è concluso: scrivi l’esito in chat con # per proseguire.',409); }
                if($q){$current=aiGameTurn($g);if($current){ $db->execute("INSERT IGNORE INTO ai_quest_actions(session_id,round_number,kind,actor_id,content,details,status,created_at) VALUES(?,?,?,?,?,'{}','skipped',?)",[$sid,(int)$g['round_number'],$current['kind'],(int)$current['id'],'Intervento passato dal Master umano',time()]); }aiQuestAdvance($q);}
                else{$next=((int)$g['turn_index']+1)%count($order);$db->execute('UPDATE ai_session_guidance SET turn_index=?,round_number=round_number+?,turn_started_at=?,version=version+1,updated_by=?,updated_at=? WHERE session_id=?',[$next,$next===0?1:0,time(),$uid,time(),$sid]);}
                aiQuestAudit($sid,'human_next_turn',[],$uid);
            }
            $db->execute('UPDATE sessioni SET control_version=control_version+1 WHERE id=?',[$sid]);
        }
        $db->commit();aiQuestSyncLogs();return [...alphanoSessionControlState($uid,$chat),'success'=>true,'last_session_id'=>$sid,'logFile'=>$action==='chiudi'?'/sessione/viewlog.php?azione=aprilog&id='.$sid:null];
    }catch(Throwable $e){if($db->inTransaction()){ $db->rollBack(); }throw $e;}
    finally{$db->query('SELECT RELEASE_LOCK(?)',[$guard]);}
}
function alphanoSessionSetHuman(array $s,?array $q,int $owner,int $actor):void {
    $db=aiDb();$sid=(int)$s['id'];$db->execute('UPDATE sessioni SET conductor_id=? WHERE id=?',[$owner,$sid]);
    if($q){
        $status=$q['status'];if($status==='resolving'){ $status='review'; }
        elseif($status==='attention'){$guidance=aiGameGuidance($sid);$status=(int)$guidance['turn_index']>=count(json_decode($guidance['turn_order'],true)?:[])?'review':'active';}
        $db->execute("UPDATE ai_quests SET mode='human',status=?,conductor_id=?,version=version+1 WHERE session_id=?",[$status,$owner,$sid]);$db->execute('UPDATE ai_session_guidance SET paused=0,turn_started_at=?,updated_by=?,updated_at=?,version=version+1 WHERE session_id=?',[time(),$actor,time(),$sid]);
    }
    alphanoSessionInvalidateWork($s['chat'],$sid,'Il Master umano dirige la sessione.');
    if($q){ aiQuestSchedule(aiQuestRow($s['chat'],true)); }
}
function alphanoSessionSyncCast(int $sid,array $order):void {
    $db=aiDb();$db->execute('UPDATE ai_quest_cast SET active=0 WHERE session_id=?',[$sid]);
    foreach($order as $m){ $db->execute("INSERT INTO ai_quest_cast(session_id,kind,actor_id,active,state) VALUES(?,?,?,1,'{}') ON DUPLICATE KEY UPDATE active=1",[$sid,$m['kind'],$m['id']]); }
}
/** Promote the existing human session rather than opening a second quest or replaying old actions. */
function alphanoSessionHandoffAi(array $s,?array $q,int $uid):void {
    $db=aiDb();$sid=(int)$s['id'];$room=aiRoom($s['chat']);
    if(!$room||empty($room['enabled'])){ throw new DomainException('L’amministratore deve prima rendere disponibile l’IA in questo luogo.',409); }
    $context=alphanoSessionReadContext($s);$owner=alphanoSessionOwner($s,$q);$g=aiGameGuidance($sid);$new=$q===null;
    if($new){
        $policy=['scene_kind'=>'narrazione','encounter'=>'narrative','resolution'=>'narrative_only','initiative'=>'entry'];
        $db->execute("INSERT INTO ai_quests(session_id,chat_key,status,mode,policy,conductor_id,created_at) VALUES(?,?,'active','human',?,?,?)",[$sid,$s['chat'],aiQuestJson($policy),$owner,time()]);
        $q=aiQuestRow($s['chat'],true);
    }
    $order=json_decode($g['turn_order'],true)?:[];
    if(!$order){
        $members=array_map('intval',array_filter(explode(';',(string)$s['partecipanti'])));$candidates=alphanoSessionTurnCandidates($s['chat'],$owner);
        $players=array_values(array_filter($candidates,static fn($m)=>$m['kind']==='pg'&&in_array($m['id'],$members,true)));
        if(!$players){ $players=array_values(array_filter($candidates,static fn($m)=>$m['kind']==='pg')); }
        if(!$players){ throw new DomainException('Serve almeno un personaggio presente diverso dal Master per affidare la giocata all’IA.',409); }
        if(count($players)>60){ throw new DomainException('Imposta un gruppo di massimo 60 partecipanti prima del passaggio all’IA.',422); }
        $npcs=array_slice(array_values(array_filter($candidates,static fn($m)=>$m['kind']==='npc')),0,AI_MAX_NPCS);
        $npcs=array_slice($npcs,0,60-count($players));
        $order=array_map(static fn($m)=>array_intersect_key($m,array_flip(['kind','id'])),[...$players,...$npcs]);
        alphanoSessionStoreGuidance($sid,$order,$uid);alphanoSessionSyncCast($sid,$order);
    }elseif($new){
        $order=alphanoSessionValidateTurns($order,$s['chat'],$owner);alphanoSessionSyncCast($sid,$order);
    }
    $db->execute('INSERT INTO session_handoffs(session_id,actor_id,from_master,to_master,mode,source_boundary,message_count,transcript_hash,context,created_at) VALUES(?,?,?,0,\'auto\',?,?,?,?,?)',[$sid,$uid,$owner,$context['boundary'],$context['count'],$context['hash'],aiQuestJson($context['context']),time()]);
    // Session ownership and the existing engine conductor are changed together.
    $db->execute('UPDATE sessioni SET conductor_id=0 WHERE id=?',[$sid]);
    alphanoSessionInvalidateWork($s['chat'],$sid,'Passaggio della sessione alla regia IA.');
    aiConductorSet(aiQuestRow($s['chat'],true),$uid,'auto');
    aiQuestPublish($sid,'session:'.$sid.S1192_LIBS_SESSION_CONTROL_INC_PHP_84E933_CONTROL.((int)$s['control_version']+1),aiQuestName('pg',$uid).' affida la regia al Master IA. La cronaca pubblica della stessa sessione è stata riletta; turni, ferite e conseguenze già registrate restano validi.');
    aiQuestAudit($sid,'ai_handoff',['from'=>$owner,'messages'=>$context['count'],'source_boundary'=>$context['boundary'],'transcript_hash'=>$context['hash'],'new_engine_adapter'=>$new],$uid);
}
