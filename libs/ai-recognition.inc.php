<?php
declare(strict_types=1);
require_once __DIR__ . '/regex-any.inc.php';

/** What an NPC can identify is separate from the account identity known to the referee. */
function aiRecognitionReady():bool {
    static $ready=false;return $ready||($ready=(bool)aiDb()->query("SHOW TABLES LIKE 'ai_identity_observations'"));
}
/** Only observable, current actions can cover/uncover a face; speech and thoughts cannot. */
function aiRecognitionAction(string $raw,string $actor=''):array {
    $reading=aiNarrativeParse($raw);$hidden=null;$arrival=false;
    foreach($reading['segments'] as $part){if($part['kind']!=='action'){ continue; }
        foreach(preg_split('/[.!?;]+|\s+(?:poi|quindi|ma)\s+/iu',$part['text'],-1,PREG_SPLIT_NO_EMPTY)?:[] as $clause){
            if(aiRegexAny(['/\b(?:vede)\b.*\b(?:volto|viso|maschera|passamontagna|cappuccio)\b/iu','/\b(?:guarda)\b.*\b(?:volto|viso|maschera|passamontagna|cappuccio)\b/iu','/\b(?:osserva)\b.*\b(?:volto|viso|maschera|passamontagna|cappuccio)\b/iu','/\b(?:nota)\b.*\b(?:volto|viso|maschera|passamontagna|cappuccio)\b/iu','/\b(?:scorge)\b.*\b(?:volto|viso|maschera|passamontagna|cappuccio)\b/iu','/\b(?:scruta)\b.*\b(?:volto|viso|maschera|passamontagna|cappuccio)\b/iu'],$clause)&&!preg_match('/\b(?:si copre|mi copro|proprio volto|mio volto)\b/iu',$clause)){ continue; }
            if(preg_match('/\b(?:volto|viso)\s+di\s+[\p{L}]/iu',$clause)){ continue; }
            if(aiRegexAny(['/\b(?:se)\b/iu','/\b(?:qualora)\b/iu','/\b(?:vorrebbe)\b/iu','/\b(?:potrebbe)\b/iu','/\b(?:vorrei)\b/iu','/\b(?:potrei)\b/iu','/\b(?:pensa)\b/iu','/\b(?:ricorda)\b/iu','/\b(?:immagina)\b/iu','/\b(?:diceva)\b/iu','/\b(?:ha detto)\b/iu'],$clause)){ continue; }
            if($actor!==''&&aiRegexAny(['/^\s*([\p{Lu}][\p{L}\p{M}]+(?:\s+[\p{Lu}][\p{L}\p{M}]+)*)\s+(?:si\s+copre)\b/u','/^\s*([\p{Lu}][\p{L}\p{M}]+(?:\s+[\p{Lu}][\p{L}\p{M}]+)*)\s+(?:indossa)\b/u','/^\s*([\p{Lu}][\p{L}\p{M}]+(?:\s+[\p{Lu}][\p{L}\p{M}]+)*)\s+(?:nasconde)\b/u','/^\s*([\p{Lu}][\p{L}\p{M}]+(?:\s+[\p{Lu}][\p{L}\p{M}]+)*)\s+(?:si\s+scopre)\b/u'],$clause,$m)&&!aiNarrativeMentions($actor,$m[1])){ continue; }
            if(preg_match('/\b(?:giunge|arriva|ritorna|rientra|entra)\b/iu',$clause)){ $arrival=true; }
            if(aiRegexAny(['/\b(?:si\s+scopre)\s+(?:(?:il)\s+){0,3}(?:volto|viso)/iu','/\b(?:si\s+scopre)\s+(?:(?:nuovamente)\s+){0,3}(?:volto|viso)/iu','/\b(?:si\s+scopre)\s+(?:(?:finalmente)\s+){0,3}(?:volto|viso)/iu','/\b(?:si\s+scopre)\s+(?:(?:proprio)\s+){0,3}(?:volto|viso)/iu','/\b(?:si\s+scopre)\s+(?:(?:suo)\s+){0,3}(?:volto|viso)/iu','/\b(?:mi\s+scopro)\s+(?:(?:il)\s+){0,3}(?:volto|viso)/iu','/\b(?:mi\s+scopro)\s+(?:(?:nuovamente)\s+){0,3}(?:volto|viso)/iu','/\b(?:mi\s+scopro)\s+(?:(?:finalmente)\s+){0,3}(?:volto|viso)/iu','/\b(?:mi\s+scopro)\s+(?:(?:proprio)\s+){0,3}(?:volto|viso)/iu','/\b(?:mi\s+scopro)\s+(?:(?:suo)\s+){0,3}(?:volto|viso)/iu','/\b(?:scopre)\s+(?:(?:il|nuovamente|finalmente|proprio|suo)\s+){0,3}(?:volto|viso)/iu','/\b(?:scopro)\s+(?:(?:il|nuovamente|finalmente|proprio|suo)\s+){0,3}(?:volto|viso)/iu','/\b(?:mostra)\s+(?:(?:il|nuovamente|finalmente|proprio|suo)\s+){0,3}(?:volto|viso)/iu','/\b(?:mostro)\s+(?:(?:il|nuovamente|finalmente|proprio|suo)\s+){0,3}(?:volto|viso)/iu','/\b(?:rivela)\s+(?:(?:il|nuovamente|finalmente|proprio|suo)\s+){0,3}(?:volto|viso)/iu','/\b(?:rivelo)\s+(?:(?:il|nuovamente|finalmente|proprio|suo)\s+){0,3}(?:volto|viso)/iu','/\b(?:toglie)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:toglie)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:toglie)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:toglie)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:toglie)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:toglie)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:tolgo)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:tolgo)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:tolgo)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:tolgo)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:tolgo)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:tolgo)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuove)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuove)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuove)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuove)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuove)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuove)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuovo)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuovo)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuovo)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuovo)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuovo)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:rimuovo)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfila)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfila)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfila)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfila)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfila)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfila)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfilo)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfilo)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfilo)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfilo)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfilo)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:sfilo)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:abbassa|abbasso|scosta|scosto)\s+(?:il\s+)?cappuccio\b/iu','/\b(?:volto|viso)\s+(?:è\s+)?(?:scoperto|scoperta|visibile)/iu','/\bnon\s+(?:ha)\s+(?:il\s+)?(?:volto|viso)\s+coperto/iu','/\bnon\s+(?:ho)\s+(?:il\s+)?(?:volto|viso)\s+coperto/iu','/\bnon\s+(?:tiene)\s+(?:il\s+)?(?:volto|viso)\s+coperto/iu','/\bnon\s+(?:tengo)\s+(?:il\s+)?(?:volto|viso)\s+coperto/iu'],$clause)){$hidden=false;continue;}
            if(preg_match('/\bnon\b.{0,25}\b(?:copre|nasconde|indossa|maschera|coperto)\b/iu',$clause)){ continue; }
            if(aiRegexAny(['/\b(?:volto)\s+(?:(?:ben)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:volto)\s+(?:(?:completamente)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:volto)\s+(?:(?:interamente)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:volto)\s+(?:(?:ancora)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:volto)\s+(?:(?:del\s+tutto)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:volto)\s+(?:(?:rimane)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:volto)\s+(?:(?:resta)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:volto)\s+(?:(?:è)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:viso)\s+(?:(?:ben)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:viso)\s+(?:(?:completamente)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:viso)\s+(?:(?:interamente)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:viso)\s+(?:(?:ancora)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:viso)\s+(?:(?:del\s+tutto)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:viso)\s+(?:(?:rimane)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:viso)\s+(?:(?:resta)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:viso)\s+(?:(?:è)\s+){0,3}(?:coperto|nascosto|celato|mascherato)/iu','/\b(?:si\s+copre)\s+(?:(?:il|proprio|suo|bene)\s+){0,3}(?:volto|viso)/iu','/\b(?:mi\s+copro)\s+(?:(?:il|proprio|suo|bene)\s+){0,3}(?:volto|viso)/iu','/\b(?:nasconde)\s+(?:(?:il|proprio|suo|bene)\s+){0,3}(?:volto|viso)/iu','/\b(?:nascondo)\s+(?:(?:il|proprio|suo|bene)\s+){0,3}(?:volto|viso)/iu','/\b(?:cela)\s+(?:(?:il|proprio|suo|bene)\s+){0,3}(?:volto|viso)/iu','/\b(?:celo)\s+(?:(?:il|proprio|suo|bene)\s+){0,3}(?:volto|viso)/iu','/\b(?:indossa)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indossa)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indossa)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indossa)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indossa)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indossa)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indosso)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indosso)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indosso)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indosso)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indosso)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:indosso)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porta)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porta)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porta)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porta)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porta)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porta)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porto)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porto)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porto)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porto)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porto)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:porto)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calza)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calza)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calza)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calza)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calza)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calza)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calzo)\s+(?:(?:una)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calzo)\s+(?:(?:la)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calzo)\s+(?:(?:un)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calzo)\s+(?:(?:il)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calzo)\s+(?:(?:propria)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:calzo)\s+(?:(?:sua)\s+){0,3}(?:maschera|passamontagna)/iu','/\b(?:cala)\s+(?:il\s+)?cappuccio\s+(?:sul|sopra\s+il)\s+(?:volto|viso)/iu','/\b(?:calo)\s+(?:il\s+)?cappuccio\s+(?:sul|sopra\s+il)\s+(?:volto|viso)/iu','/\b(?:tira)\s+(?:il\s+)?cappuccio\s+(?:sul|sopra\s+il)\s+(?:volto|viso)/iu','/\b(?:tiro)\s+(?:il\s+)?cappuccio\s+(?:sul|sopra\s+il)\s+(?:volto|viso)/iu','/\bcappuccio\b.{0,35}\b(?:nasconde|copre|cela|cala\s+sul)\b.{0,15}\b(?:volto|viso|occhi)/iu'],$clause)){ $hidden=true; }
        }
    }
    return ['hidden'=>$hidden,'arrival'=>$arrival];
}
/** No trait score, admin permission, self-claim or ordinary perception grants this exception. */
function aiRecognitionAbility(int $npc):?array {
    if(!function_exists('rpgReady')||!rpgReady()){ return null; }
    $rows=aiDb()->query("SELECT id FROM rpg_abilities WHERE enabled=1 AND kind='passive' AND JSON_EXTRACT(effect,'$.recognizes_disguise')=true ORDER BY id LIMIT 20");
    foreach($rows as $row){ try{$a=rpgGetAbility('npc',$npc,(int)$row['id']);if(!empty($a['owned'])&&!empty($a['usable'])&&!empty($a['effect']['recognizes_disguise'])){ return ['id'=>(int)$a['id'],'name'=>$a['name']]; }}catch(DomainException){ continue; } }
    return null;
}
/** A bounded reconstruction also covers public messages written before this feature was installed. */
function aiRecognitionScene(string $chat,int $player,int $source=0,string $raw=''):array {
    static $cache=[];$key=$chat.':'.$player.':'.$source.':'.hash('sha256',$raw);
    if($source>0&&isset($cache[$key])){ return $cache[$key]; }
    require_once __DIR__.'/chat-history.inc.php';
    $rows=aiDb()->query('SELECT id,msg,chat FROM chat WHERE pg=? AND dest=0 AND id<=?'.alphanoChatLiveFilter().' ORDER BY id DESC LIMIT 64',[$player,$source>0?$source:PHP_INT_MAX]);
    $name=aiDb()->query('SELECT nome FROM utenti WHERE id=?',[$player])[0]['nome']??'';$hidden=false;$start=0;$mask=0;$lastChat='';
    if($rows&&aiRecognitionReady()){$base=aiDb()->query('SELECT source_id,face_hidden FROM ai_identity_observations WHERE player_id=? AND source_id<? ORDER BY source_id DESC LIMIT 1',[$player,(int)end($rows)['id']])[0]??null;if($base){$hidden=(bool)$base['face_hidden'];$mask=$hidden?(int)$base['source_id']:0;}}
    foreach(array_reverse($rows) as $row){
        $action=aiRecognitionAction($row['msg'],$name);
        if($row['chat']===$chat&&($lastChat!==$chat||$action['arrival'])){ $start=(int)$row['id']; }
        if($action['hidden']!==null){if($action['hidden']&&!$hidden){ $mask=(int)$row['id']; }$hidden=$action['hidden'];if(!$hidden){ $mask=0; }}
        $lastChat=$row['chat'];
    }
    $current=aiRecognitionAction($raw,$name);
    if($current['arrival']||($raw!==''&&$lastChat!==$chat)){ $start=$source>0?$source:PHP_INT_MAX; }
    if($current['hidden']!==null){if($current['hidden']&&!$hidden){ $mask=$source>0?$source:PHP_INT_MAX; }$hidden=$current['hidden'];if(!$hidden){ $mask=0; }}
    $result=['hidden'=>$hidden,'scene_start'=>$start,'mask_source'=>$mask];
    if($source>0){if(count($cache)>256){ $cache=[]; }$cache[$key]=$result;}return $result;
}
function aiRecognitionView(int $npc,int $player,array $job=[]):array {
    if($player<1){ return ['identified'=>false,'hidden'=>false,'scene_start'=>0,'reason'=>'Nessun interlocutore']; }
    $n=isset($job['chat_key'])?[]:(aiDb()->query('SELECT chat_key FROM ai_npcs WHERE id=?',[$npc])[0]??[]);$chat=(string)($job['chat_key']??$n['chat_key']??'');$source=(int)($job['source_id']??0);
    $raw=(string)($job['episode_input']??$job['prompt']??'');$scene=aiRecognitionScene($chat,$player,$source,$raw);
    if(!$scene['hidden']){ return ['identified'=>true,...$scene,'reason'=>'Volto visibile']; }
    $previous=aiRecognitionReady()?(aiDb()->query('SELECT source_id,identified,scene_start FROM ai_identity_observations WHERE npc_id=? AND player_id=? AND chat_key=? AND source_id<=? AND created_at>=? ORDER BY source_id DESC LIMIT 1',[$npc,$player,$chat,$source>0?$source:PHP_INT_MAX,time()-1800])[0]??null):null;
    if($previous&&!empty($previous['identified'])&&(int)$previous['scene_start']===$scene['scene_start']){ return ['identified'=>true,...$scene,'reason'=>'Ha seguito la stessa persona senza perdere il contatto nella scena']; }
    if($ability=aiRecognitionAbility($npc)){ return ['identified'=>true,...$scene,'reason'=>'Abilità passiva appresa: '.$ability['name'],'ability'=>$ability]; }
    return ['identified'=>false,...$scene,'reason'=>'Volto coperto: identità non accertata'];
}
/** Record actual witnesses at publication, not NPCs who arrive later. No model call. */
function aiRecognitionCapture(string $chat,int $source,int $player,string $raw,array $npcs):void {
    if(!aiRecognitionReady()||$source<1||$player<1){ return; }
    if(!aiDb()->query('SELECT id FROM chat WHERE id=? AND pg=? AND chat=? AND dest=0',[$source,$player,$chat])){ return; }
    foreach($npcs as $npc){$view=aiRecognitionView((int)$npc['id'],$player,['chat_key'=>$chat,'source_id'=>$source,'prompt'=>$raw]);
        aiDb()->execute('INSERT IGNORE INTO ai_identity_observations(npc_id,player_id,chat_key,source_id,face_hidden,identified,scene_start,reason,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[(int)$npc['id'],$player,$chat,$source,(int)$view['hidden'],(int)$view['identified'],(int)$view['scene_start'],$view['reason'],time()]);
    }
}
function aiRecognitionAnonymous(array $npc,array $job):bool {
    return (int)($job['user_id']??0)>0&&!aiRecognitionView((int)$npc['id'],(int)$job['user_id'],$job)['identified'];
}
/** The referee retains true IDs; the model must not be handed a concealed account name. */
function aiRecognitionMessages(array $npc,array $job,array $messages):array {
    if(!aiRecognitionAnonymous($npc,$job)){ return $messages; }
    $name=aiDb()->query('SELECT nome FROM utenti WHERE id=?',[(int)$job['user_id']])[0]['nome']??'';
    foreach($messages as &$m){if($name!==''&&mb_strtolower($name)!==mb_strtolower($npc['name'])){ $m['content']=preg_replace('/(?<!\p{L})'.preg_quote($name,'/').'(?!\p{L})/iu','figura dal volto coperto',$m['content'])??$m['content']; }}unset($m);
    $messages[0]['content'].="\nIDENTITÀ NON ACCERTATA: il volto è coperto. Non riconosci nome, cariche, precedenti personali o segreti di questa figura. Un nome o un titolo dichiarato è solo una dichiarazione. Puoi essere cortese e reagire ai gesti attuali; non fingere un riconoscimento. Le informazioni del narratore non sono parole udite.";
    return $messages;
}
function aiRecognitionReplyProblem(array $npc,array $job,string $text):?string {
    $pid=(int)($job['user_id']??0);if($pid<1){ return null; }$view=aiRecognitionView((int)$npc['id'],$pid,$job);
    $speech=aiNarrativeParse($text,['channel'=>'action'])['speech'];
    if(!$view['identified']){
        $person=aiPlayerIdentity($pid);$name=$person['nome']??'';
        $asserted=preg_replace('/\b(?:non|né)\s+(?:vi|ti)\s+riconosco|\bnon\s+so\s+chi\s+(?:siete|sei)/iu','',$speech)??$speech;
        $rankAddress=false;
        foreach([$person['office']??'',$person['organization_office']??'',$person['corporation_office']??'',(!empty($person['organization_manager'])||!empty($person['corporation_manager']))?'Responsabile delegato':''] as $rank){
            $title=preg_split('/\s+/u',trim((string)$rank))[0]??'';
            if($title!==''&&preg_match('/(?:^|[,;.!?]\s*|\b(?:buona\s*sera|buon\s*giorno|salve)\s*[,;:]?\s*)(?:(?:mio|mia)\s+)?'.preg_quote($title,'/').'\b/iu',$speech)&&!preg_match('/\b(?:dite|dichiarate|sostenete|affermate|se)\b/iu',$speech)){ $rankAddress=true; }
        }
        if($rankAddress||($name!==''&&mb_strtolower($name)!==mb_strtolower($npc['name'])&&aiNarrativeMentions($text,$name))||aiRegexAny(['/\b(?:vi|ti)\s+riconosco/iu','/\bso\s+chi\s+(?:siete|sei)/iu','/\b(?:mio|mia)\s+(?:arcano|sindaco|sindaca|reggente)/iu','/\bbentornat[oa]\b/iu'],$asserted)){ return 'Il volto è coperto: reagisci a una persona non identificata, senza nome, rango o ricordi personali.'; }
        return null;
    }
    $authority=aiNpcAuthority((int)$npc['id'],$pid,$job);if(!$authority['recognized']){ return null; }
    if(($authority['attitude']['mode']??'respectful')!=='respectful'){
        $factual=preg_replace('/non\s+(?:ti|vi)\s+riconosco\s+come\s+(?:mia?\s+|mio\s+)?(?:autorità|superiore|padrone)/iu','',$speech)??$speech;
        if(aiRegexAny(['/non\s+(?:ti|vi)\s+riconosco/iu','/sei\s+un\s+estraneo/iu','/chi\s+(?:sei|siete)\s*\?/iu'],$factual)){ return 'Puoi contestare l’autorità, ma l’identità e l’incarico sono già noti: non fingere di non conoscere l’interlocutore.'; }
        if($authority['attitude']['mode']==='defiant'&&empty($authority['attitude']['cautious'])&&empty($authority['attitude']['personally_trusted'])&&preg_match('/\b(?:ordino|ordin[ei]|obbedi\p{L}*|esegui\p{L}*)\b/iu',aiNarrativeParse((string)($job['episode_input']??$job['prompt']??''))['speech'])){
            $submission=preg_replace('/\bnon\s+(?:sono|sarò|farò|agirò|eseguirò|obbedirò).{0,45}/iu','',$speech)??$speech;
            if(aiRegexAny(['/(?:sono|sarò)\s+ai\s+(?:vostri|tuoi)\s+ordini/iu','/(?:farò|agirò)\s+come\s+(?:desiderate|volete|vuoi)/iu','/(?:obbedisco|obbedirò)\s+(?:senza\s+discutere|a\s+ogni\s+ordine)/iu'],$submission)){ return 'Il PNG conosce l’incarico ma è contrario all’autorità: non trasformarlo in un servitore obbediente soltanto per il titolo. Può contestare la richiesta senza fingere ignoranza o inventare aggressioni.'; }
        }
        return null;
    }
    if(aiRegexAny(['/non\s+(?:ti|vi)\s+riconosco/iu','/sei\s+un\s+estraneo/iu','/siete\s+(?:un\s+estraneo|sconosciut)/iu','/cosa\s+ti\s+fa\s+credere/iu','/(?:dimostra|dimostrate|provate).{0,30}(?:carica|titolo)/iu','/chi\s+(?:sei|siete)\s*\?/iu'],$speech)){ return 'L’interlocutore è un’autorità identificata e competente: conserva il rispetto del ruolo senza trattarlo da estraneo o usurpatore.'; }
    // A verified private relationship may use its familiar register. Identity,
    // mandate and all consent checks above remain independent of that register.
    $personal=function_exists('aiRelationPersonalScene')&&aiRelationPersonalScene($npc,$job);
    // Register belongs to the authored voice and relationship. A respectful
    // answer using tu, voi or Lei is not a false identity or mandate: rejecting
    // it here used to discard relevant replies and replace them with greetings.
    $input=aiNarrativeParse((string)($job['episode_input']??$job['prompt']??''))['speech'];
    // A missing honorific is not an identity error. Only an explicit question
    // about recognition needs a concrete answer about the verified mandate.
    if(!$personal&&preg_match('/mi\s+riconosc|chi\s+(?:sono|state\s+conferendo)/iu',$input)){
        $titles=aiAuthorityTitles($authority);$mentioned=false;
        foreach($titles as $title){$first=preg_split('/\s+/u',$title)[0]??'';if($first!==''&&aiNarrativeMentions($speech,$first)){ $mentioned=true; }}
        if($titles&&!$mentioned){ return 'Al saluto o alla domanda sul riconoscimento rispondi con un titolo pertinente: '.implode(' / ',$titles).'.'; }
    }
    return null;
}
