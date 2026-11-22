<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
require_once __DIR__.'/../libs/ai.inc.php';
require_once __DIR__.'/../libs/ai-adult-admin.inc.php';
require_once __DIR__.'/../libs/ai-admin-controls.inc.php';
try {
    $user=alphanoRequireLogin(true);$admin=!empty(alphanoUser()['admin']);
    $method=$_SERVER['REQUEST_METHOD'];
    if (!in_array($method,['GET','POST'],true)) { throw new DomainException('Metodo non consentito.',405); }
    $multipart=$method==='POST' && str_contains($_SERVER['CONTENT_TYPE']??'','multipart/form-data');
    $in=$method==='POST'?($multipart?$_POST:alphanoRequestJson()):$_GET;
    if ($method==='POST') { alphanoRequireCsrf(true); }
    $action=aiField($in,'action',30,true);$db=aiDb();
    if ($action==='portrait' && $method==='GET') {
        $name=aiField($in,'file',40,true);
        $portraitDir=ai_portrait_directory();
        $root=realpath($portraitDir);
        $file=($root!==false && preg_match('/^[a-f0-9]{32}\.png$/D',$name)===1) ? realpath($portraitDir.'/'.$name) : false;
        if ($file===false || !str_starts_with($file, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) || !is_file($file)) { throw new DomainException('Ritratto non disponibile.',404); }
        header('Content-Type: image/png');header('X-Content-Type-Options: nosniff');
        session_write_close();readfile($file);exit;
    }
    if ($action==='job' && $method==='GET') {
        $job=$db->query('SELECT id,chat_key,user_id,kind,status,result,error FROM ai_jobs WHERE id=?',[aiInteger($in,'id',1,PHP_INT_MAX)])[0]??null;
        if (!$job || (!$admin && ((int)$job['user_id']!==$user || $job['kind']==='preview'))) { throw new DomainException('Richiesta non disponibile.',404); }
        if (!$admin) {
            ai_dialogue_room($job['chat_key'],$user);
            if ((alphanoUser()['chat']??'')!==$job['chat_key']) { throw new DomainException('Rientra nel luogo della richiesta.',403); }
        }
        echo json_encode(['job'=>['id'=>(int)$job['id'],'status'=>$job['status'],'result'=>$job['result']?json_decode($job['result'],true):null,'error'=>$job['error']]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
    }
    if ($action==='public' && $method==='GET') {
        $chat=aiField($in,'chat',100,true);ai_dialogue_room($chat,$user);$room=aiRoom($chat);
        $enabled=$room && (int)$room['enabled'];
        echo json_encode(['private'=>str_starts_with($chat,'rent_'),'enabled'=>(bool)$enabled,'mode'=>'request','npcs'=>$enabled?array_map('ai_public_npc',ai_dialogue_npcs($chat,$user)):[],
            'scene'=>$room['public_setting']??'','typing'=>$enabled?ai_pending_public($chat):[]],JSON_UNESCAPED_UNICODE);exit;
    }
    aiAdmin();
    if ($action==='overview' && $method==='GET') {
        session_write_close();
        try { $ready=(aiRuntime('/health')['status']??'')==='ok'; } catch (Throwable $e) { $ready=false; }
        $worker=$db->query('SELECT heartbeat,state,last_error FROM ai_worker WHERE id=1')[0]??null;
        $alive=$worker && $worker['state']!=='stopped' && time()-(int)$worker['heartbeat']<($worker['state']==='generating'?aiDeadline()+30:10);
        echo json_encode(['model'=>ai_model_name(),'model_ready'=>$ready,'worker_alive'=>(bool)$alive,'rooms'=>$db->query('SELECT m.chat_key,m.title,COALESCE(a.enabled,0) enabled FROM mappe m LEFT JOIN ai_rooms a ON a.chat_key COLLATE utf8mb4_unicode_ci=m.chat_key WHERE m.chat=1 ORDER BY m.title'), 'worker'=>$worker,'queue'=>$db->query('SELECT status,COUNT(*) total FROM ai_jobs GROUP BY status')],JSON_UNESCAPED_UNICODE);exit;
    }
    $chat=aiField($in,'chat',100,true);aiPublicRoom($chat);
    if($action==='controls'&&$method==='GET'){
        $nid=aiInteger($in,'npc_id',1,PHP_INT_MAX);$npc=$db->query('SELECT * FROM ai_npcs WHERE id=? AND chat_key=?',[$nid,$chat])[0]??null;if(!$npc){ throw new DomainException('PNG non disponibile.',404); }
        echo aiQuestJson(['rules'=>aiSocialRules($nid),'game'=>ai_npc_game($nid),'revision'=>ai_npc_controls_revision($nid),'schema'=>ai_npc_controls_schema()]);exit;
    }
    if($action==='inspect'&&$method==='GET'){
        $nid=aiInteger($in,'npc_id',1,PHP_INT_MAX);$npc=$db->query('SELECT * FROM ai_npcs WHERE id=? AND chat_key=?',[$nid,$chat])[0]??null;if(!$npc){ throw new DomainException('PNG non disponibile.',404); }
        $job=['id'=>0,'npc_id'=>$nid,'kind'=>'preview','user_id'=>$user,'chat_key'=>$chat,'source_id'=>PHP_INT_MAX,'created_at'=>time(),'prompt'=>aiField($in,'prompt',1200)?:'Buonasera, come state?'];
        echo aiQuestJson(['messages'=>aiPrompt(aiRoom($chat),$npc,$job,.5),'model'=>ai_model_name(),'context_limit'=>ai_model_context(),'note'=>'Anteprima amministrativa con il tuo personaggio; la chat usa anche il turno, il rapporto e i fatti verificati della giocata.']);exit;
    }
    if ($action==='upload_portrait' && $method==='POST') {
        echo json_encode(['url'=>ai_upload_portrait($_FILES['portrait']??[])],JSON_UNESCAPED_UNICODE);exit;
    }
    if ($action==='load' && $method==='GET') {
        echo json_encode(['room'=>aiRoom($chat),'npcs'=>array_map(static fn($n)=>[...$n,'adult'=>ai_adult_admin_settings((int)$n['id'])],$db->query('SELECT * FROM ai_npcs WHERE chat_key=? ORDER BY id',[$chat])),'memory'=>$db->query('SELECT npc_id,summary,canonical,updated_at FROM ai_memory WHERE chat_key=?',[$chat]),'jobs'=>$db->query('SELECT id,npc_id,kind,status,created_at,error FROM ai_jobs WHERE chat_key=? ORDER BY id DESC LIMIT 25',[$chat])],JSON_UNESCAPED_UNICODE);exit;
    }
    if ($method!=='POST') { throw new DomainException('Azione non consentita.',405); }
    $db->beginTransaction();
    $room=$db->query('SELECT * FROM ai_rooms WHERE chat_key=? FOR UPDATE',[$chat])[0]??null;
    if ($action==='save_room') {
        $mode=aiField($in,'mode',12,true);$length=aiField($in,'response_length',12,true);
        if (!in_array($mode,['auto','request','mixed'],true)||!in_array($length,['short','medium','long'],true)) { throw new DomainException('Modalità o lunghezza non valida.',422); }
        if ($room && (int)$room['version']!==aiInteger($in,'version',1,PHP_INT_MAX)) { throw new DomainException('Configurazione aggiornata altrove. Ricarica la pagina.',409); }
        $enabled=aiInteger($in,'enabled',0,1);
        if ($enabled && !$db->query('SELECT id FROM ai_npcs WHERE chat_key=? AND active=1',[$chat])) { throw new DomainException('Crea e attiva almeno un PNG prima di accendere la chat.',422); }
        $public=aiField($in,'public_setting',6000);
        $db->execute('INSERT INTO ai_rooms(chat_key,enabled,mode,setting,atmosphere,plot,style,response_length,cooldown,trigger_every,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),mode=VALUES(mode),setting=VALUES(setting),atmosphere=VALUES(atmosphere),plot=VALUES(plot),style=VALUES(style),response_length=VALUES(response_length),cooldown=VALUES(cooldown),trigger_every=VALUES(trigger_every),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at),version=version+1,turn_count=0',[$chat,$enabled,$mode,aiField($in,'setting',1600),aiField($in,'atmosphere',500),aiField($in,'plot',1000),aiField($in,'style',400),$length,aiInteger($in,'cooldown',10,600),aiInteger($in,'trigger_every',1,20),$user,time()]);
        if (array_key_exists('public_setting',$in)) { $db->execute('UPDATE ai_rooms SET public_setting=? WHERE chat_key=?',[$public,$chat]); }
        if(array_key_exists('quest_rules',$in)){$rule=aiField($in,'quest_rules',30,true);if(!in_array($rule,['legacy','alphano_scene_v1'],true)){ throw new DomainException('Regolamento non valido.',422); }$db->execute('UPDATE ai_rooms SET quest_rules=? WHERE chat_key=?',[$rule,$chat]);}
        foreach(['quest_lethal'=>1,'quest_reward'=>100] as $field=>$max){ if(array_key_exists($field,$in)){ $db->execute('UPDATE ai_rooms SET '.$field.'=? WHERE chat_key=?',[aiInteger($in,$field,0,$max),$chat]); } }
        foreach(['quest_auto','quest_auto_close','quest_auto_continue'] as $field){ if(array_key_exists($field,$in)){ $db->execute('UPDATE ai_rooms SET '.$field.'=? WHERE chat_key=?',[aiInteger($in,$field,0,1),$chat]); } }
    } elseif (!$room) { throw new DomainException('Salva prima l’ambientazione del luogo.',422); }
    elseif($action==='save_controls'){
        $nid=aiInteger($in,'npc_id',1,PHP_INT_MAX);if(!$db->query('SELECT id FROM ai_npcs WHERE id=? AND chat_key=? FOR UPDATE',[$nid,$chat])){ throw new DomainException('PNG non disponibile.',404); }
        $db->query('SELECT npc_id FROM ai_npc_rules WHERE npc_id=? FOR UPDATE',[$nid]);$db->query('SELECT npc_id FROM ai_npc_game WHERE npc_id=? FOR UPDATE',[$nid]);
        if(!hash_equals(ai_npc_controls_revision($nid),aiField($in,'revision',64,true))){ throw new DomainException('Regole modificate altrove: ricarica prima di salvare.',409); }
        if(!is_array($in['rules']??null)||!is_array($in['game']??null)){ throw new DomainException('Configurazione non valida.',422); }
        $reason=aiField($in,'reason',500,true);$before=['rules'=>aiSocialRules($nid),'game'=>ai_npc_game($nid)];$rules=aiSocialValidate($in['rules']);$game=ai_npc_game_validate($in['game'],$nid);
        $db->execute('INSERT INTO ai_npc_rules(npc_id,settings,updated_by,updated_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE settings=VALUES(settings),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$nid,aiQuestJson($rules),$user,time()]);
        $db->execute('INSERT INTO ai_npc_game(npc_id,settings,updated_by,updated_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE settings=VALUES(settings),version=version+1,updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$nid,aiQuestJson($game),$user,time()]);
        $db->execute("UPDATE ai_social_jobs s JOIN ai_jobs j ON j.id=s.job_id SET s.status='cancelled',s.error='Regole del PNG aggiornate dallo staff.' WHERE j.npc_id=? AND s.status IN('queued','running')",[$nid]);
        aiQuestAudit(0,'npc_controls_updated',['npc'=>$nid,'before'=>$before,'rules'=>$rules,'game'=>$game,'reason'=>$reason],$user);
    }
    elseif ($action==='correct_npc_death') {
        require_once __DIR__.'/../libs/npc-mortality.inc.php';
        if(aiField($in,'confirmation',20,true)!=='RETTIFICA'){ throw new DomainException('Conferma esplicitamente la rettifica della registrazione amministrativa.',422); }
        $npc=aiInteger($in,'npc_id',1,PHP_INT_MAX);
        if(!$db->query('SELECT id FROM ai_npcs WHERE id=? AND chat_key=?',[$npc,$chat])){ throw new DomainException('PNG non disponibile in questo luogo.',404); }
        $receipt=alphanoNpcCorrectAdminDeath($npc,aiInteger($in,'death_id',1,PHP_INT_MAX),$user,aiInteger($in,'hit_points',1,maxpuntif),aiField($in,'reason',1000,true));
        if(!$receipt['replayed']){ $db->execute('UPDATE ai_rooms SET version=version+1 WHERE chat_key=?',[$chat]); }
    }
    elseif ($action==='save_npc') {
        $id=aiInteger($in,'id',0,PHP_INT_MAX);
        $name=aiField($in,'name',80,true);
        if ($db->query('SELECT id FROM ai_npcs WHERE chat_key=? AND name=? AND id<>?',[$chat,$name,$id])) { throw new DomainException('Esiste già un PNG con questo nome nel luogo.',409); }
        if ($id && !$db->query('SELECT id FROM ai_npcs WHERE id=? AND chat_key=?',[$id,$chat])) { throw new DomainException('PNG non disponibile.',404); }
        $values=[$name,aiInteger($in,'active',0,1)];
        foreach (['character_text','personality','history','knowledge','behavior'] as $field) { $values[]=aiField($in,$field,700); }
        $previous=$id?($db->query('SELECT profile FROM ai_npcs WHERE id=? AND chat_key=?',[$id,$chat])[0]??[]):[];
        if (isset($in['profile']) && (!is_array($in['profile']) || ($in['profile']!==[] && array_is_list($in['profile'])))) { throw new DomainException('Profilo non valido.',422); }
        $profile=ai_validate_profile($in['profile']??[],ai_npc_profile($previous));
        require_once __DIR__.'/../libs/npc-mortality.inc.php';
        if($id){ alphanoNpcAssertProfileEdit($id,$profile,(int)$values[1]); }
        if((int)$profile['hit_points']<=0){ $values[1]=0; }
        // The room row above serializes competing activations. Archived profiles
        // retain their history without consuming one of the two public places.
        if((int)$values[1]===1&&(int)$db->query('SELECT COUNT(*) total FROM ai_npcs WHERE chat_key=? AND active=1 AND id<>?',[$chat,$id])[0]['total']>=AI_MAX_ACTIVE_NPCS){ throw new DomainException('Massimo '.AI_MAX_ACTIVE_NPCS.' PNG attivi per luogo. Disattiva prima un altro personaggio.',422); }
        if(isset($in['adult'])&&(!is_array($in['adult'])||($in['adult']!==[]&&array_is_list($in['adult'])))){ throw new DomainException('Configurazione adulta del PNG non valida.',422); }
        $adult=ai_adult_admin_validate($in['adult']??ai_adult_admin_settings($id),$profile,$chat);
        if((int)$profile['money']!==($id?aiNpcBalance($id):0)){ throw new DomainException(alphanoCurrencyText('Le Corone si trasferiscono dalla sezione Cassa del PNG, con un movimento registrato.'),422); }
        if ($id) { $db->execute('UPDATE ai_npcs SET name=?,active=?,character_text=?,personality=?,history=?,knowledge=?,behavior=? WHERE id=? AND chat_key=?',[...$values,$id,$chat]); }
        else { $db->execute('INSERT INTO ai_npcs(name,active,character_text,personality,history,knowledge,behavior,chat_key) VALUES(?,?,?,?,?,?,?,?)',[...$values,$chat]); }
        $savedId=$id?:((int)$db->lastInsertId());
        $db->execute('UPDATE ai_npcs SET profile=? WHERE id=? AND chat_key=?',[json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$savedId,$chat]);
        if((int)$profile['hit_points']<=0){ alphanoNpcRecordAdminDeath($savedId,$user); }
        ai_adult_admin_save($savedId,$adult);
        // Keep the saved player turn: active profile edits are re-read before
        // generation and fenced again before publication, including previews.
        if(!(int)$values[1]){ $db->execute("UPDATE ai_jobs SET status='cancelled',finished_at=?,error='PNG disattivato.' WHERE npc_id=? AND status IN('queued','running')",[time(),$savedId]); }
        $db->execute("UPDATE ai_social_jobs s JOIN ai_jobs j ON j.id=s.job_id SET s.status='cancelled',s.error='Profilo o limiti del PNG aggiornati.' WHERE j.npc_id=? AND s.status IN('queued','running')",[$savedId]);
        if (!$db->query('SELECT id FROM ai_npcs WHERE chat_key=? AND active=1',[$chat])) { $db->execute('UPDATE ai_rooms SET enabled=0 WHERE chat_key=?',[$chat]); }
    } elseif ($action==='memory') {
        $npc=aiInteger($in,'npc_id',0,PHP_INT_MAX);
        if ($npc && !$db->query('SELECT id FROM ai_npcs WHERE id=? AND chat_key=?',[$npc,$chat])) { throw new DomainException('PNG non disponibile.',404); }
        aiRemember($chat,$npc,aiField($in,'summary',600));
        $db->execute('UPDATE ai_rooms SET version=version+1 WHERE chat_key=?',[$chat]);
    } elseif ($action==='preview') {
        $npc=$db->query('SELECT * FROM ai_npcs WHERE id=? AND chat_key=?',[aiInteger($in,'npc_id',1,PHP_INT_MAX),$chat])[0]??null;
        if (!$npc) { throw new DomainException('Seleziona un PNG salvato.',422); }
        $request=aiField($in,'request_id',80,true);
        if (!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$request)) { throw new DomainException('Identificativo non valido.',422); }
        $job=aiEnqueueLocked($room,$npc,$user,'preview:'.$user.':'.hash('sha256',$chat.':'.$request),aiField($in,'prompt',1200,true),'preview');
        $db->commit();echo json_encode(['ok'=>true,'job'=>$job]);exit;
    } elseif ($action==='cancel') {
        $db->execute("UPDATE ai_jobs SET status='cancelled',finished_at=?,error='Annullata dall’amministratore' WHERE id=? AND chat_key=? AND status IN ('queued','running')",[time(),aiInteger($in,'id',1,PHP_INT_MAX),$chat]);
    } else { throw new DomainException('Azione non riconosciuta.',400); }
    if (in_array($action,['save_room','memory'],true)) { $db->execute("UPDATE ai_jobs SET status='cancelled',finished_at=?,error='Configurazione aggiornata' WHERE chat_key=? AND status IN ('queued','running')",[time(),$chat]); }
    $db->commit();echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    $status=$e instanceof DomainException?$e->getCode():($e instanceof JsonException?400:500);
    if ($status<400 || $status>599) { $status=500; }
    if ($status===500) { error_log('[Master IA API] '.$e->getMessage()); }
    http_response_code($status);
    echo json_encode(['error'=>$status===500?'Operazione non riuscita. Verifica i dati e riprova.':$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
