<?php
declare(strict_types=1);

require_once __DIR__.'/ai-narrative.inc.php';

/** The same typed interpretation drives dialogue routing and the rules engine. */
function aiSceneIntent(string $raw): ?string {return aiNarrativeParse($raw)['intent'];}

/** Role aliases come from actual sheets, never titles claimed by a player in chat. */
function aiNarrativeRoleAliases(string $role):array {
    $aliases=[];
    foreach(['sergent[ei]'=>['sergente'],'guardia|presidio'=>['guardia'],'parroc[oi]'=>['parroco'],'sacerdot[ei]'=>['sacerdote'],'religiosa|suora'=>['suora'],'musicista'=>['musicista'],'locandiere|oste'=>['oste','locandiere'],'sindac[oa]'=>['sindaco','sindaca'],'reggente'=>['reggente'],'capitan[oa]'=>['capitano','capitana'],'camerier[ae]'=>['cameriera','cameriere']] as $pattern=>$names){ if(preg_match('/'.$pattern.'/iu',$role)){ $aliases=array_merge($aliases,$names); } }
    return array_values(array_unique($aliases));
}

/**
 * Reusable, bounded public-scene evidence. No whispers, other rooms, other users'
 * memories or off-scene cast can become the antecedent of an implicit target.
 * Callers may reuse this result for address and action resolution in one request.
 */
function aiNarrativeSceneContext(string $chat,int $player,int $source=0,?array $npcs=null,bool $withPlayers=false):array {
    $db=aiDb();$npcs??=aiDialogueNpcs($chat,$player);$present=[];$ids=[];
    foreach($npcs as $n){ if(($n['chat_key']??$chat)===$chat&&!empty($n['active'])){ $ids[]=(int)$n['id']; } }
    $settings=[];if($ids){ foreach($db->query('SELECT npc_id,settings FROM aiNpcGame WHERE npc_id IN('.implode(',',array_fill(0,count($ids),'?')).')',$ids) as $row){ $settings[(int)$row['npc_id']]=json_decode($row['settings'],true)?:[]; } }
    foreach($npcs as $n){if(!in_array((int)$n['id'],$ids,true)){ continue; }$p=aiNpcProfile($n);$g=$settings[(int)$n['id']]??[];$present[]=['kind'=>'npc','id'=>(int)$n['id'],'name'=>$n['name'],'active'=>true,'gender'=>$p['sex'],'race_id'=>(int)$p['race_id'],'titles'=>aiNarrativeRoleAliases(($g['rank']??'').' '.$p['occupation'])];}
    if($withPlayers){ foreach($db->query('SELECT id,nome,sesso,razza FROM utenti WHERE chat=? AND id<>? AND bloccato=0 AND puntif>0',[$chat,$player]) as $p){ $present[]=['kind'=>'pg','id'=>(int)$p['id'],'name'=>$p['nome'],'gender'=>$p['sesso'],'race_id'=>(int)$p['razza'],'active'=>true]; } }
    $context=['actor'=>'pg:'.$player];
    if($player>0&&!str_starts_with($chat,'rent_')){
        $before=$source>0?$source:PHP_INT_MAX;$since=time()-1800;
        $events=$db->query('SELECT e.content FROM ai_events e JOIN chat c ON c.id=e.source_id WHERE e.chat_key=? AND c.chat=? AND e.npc_id=0 AND c.pg=? AND c.dest=0 AND e.source_id<? AND e.created_at>=? ORDER BY e.id DESC LIMIT 6',[$chat,$chat,$player,$before,$since]);
        foreach($events as $event){$prior=aiNarrativeResolve(aiNarrativeParse($event['content']),$present,['purpose'=>'action','actor'=>'pg:'.$player]);if($prior['status']==='resolved'&&in_array($prior['reason'],['nome_nel_gesto_o_dialogo','titolo_o_descrizione_univoca'],true)){$context['last_target']=aiNarrativeActorKey($prior['target']);break;}}
        $last=$db->query("SELECT j.npc_id FROM ai_jobs j JOIN chat c ON c.id=j.source_id WHERE j.chat_key=? AND c.chat=? AND j.user_id=? AND c.pg=? AND c.dest=0 AND j.source_id<? AND j.kind='play' AND j.status='done' AND j.finished_at>=? ORDER BY j.id DESC LIMIT 1",[$chat,$chat,$player,$player,$before,$since])[0]??[];
        if($last){ $context['last_interlocutor']='npc:'.(int)$last['npc_id']; }
    }
    return ['present'=>$present,'context'=>$context];
}

/** Explicit object wins; valid selection and demonstrated continuity narrow ambiguity. */
function aiSceneTarget(array $npcs,string $raw,int $selected,int $player=0,int $source=0,?array $scene=null): ?array {
    if(!$npcs){ return null; }$scene??=aiNarrativeSceneContext((string)($npcs[0]['chat_key']??''),$player,$source,$npcs);
    $analysis=aiNarrativeParse($raw);$purpose=in_array($analysis['intent'],['assalto','potere','furto','arresto','rilascio'],true)?'action':'address';
    $result=aiNarrativeResolve($analysis,$scene['present'],array_replace($scene['context'],['purpose'=>$purpose,'selected'=>$selected]));
    if($result['status']!=='resolved'||($result['target']['kind']??'')!=='npc'){ return null; }
    foreach($npcs as $n){ if((int)$n['id']===(int)$result['target']['id']){ return $n; } }return null;
}
function aiSceneKind(array $q): string {return json_decode($q['policy']??'{}',true)['scene_kind']??'combattimento';}
/** A complete round may suggest closure only when all active players have actually ceased the scene. */
function aiSceneCanFinish(int $sid): bool {
    $g=aiGameGuidance($sid);
    foreach(aiDb()->query('SELECT state FROM ai_quest_cast WHERE session_id=? AND active=1',[$sid]) as $row){ if(aiTacticalRestrained(json_decode($row['state'],true)?:[],(int)$g['round_number'])){ return false; } }
    $pgs=aiDb()->query("SELECT c.actor_id,a.content FROM ai_quest_cast c LEFT JOIN ai_quest_actions a ON a.session_id=c.session_id AND a.round_number=? AND a.kind='pg' AND a.actor_id=c.actor_id WHERE c.session_id=? AND c.kind='pg' AND c.active=1",[(int)$g['round_number'],$sid]);
    if(!$pgs){ return true; }
    foreach($pgs as $pg){ if(aiSceneIntent((string)($pg['content']??''))!=='fine'){ return false; } }
    return true;
}
/** Shared close path: actual Session Manager state, queued work, announcement and durable log stay consistent. */
function aiSceneClose(int $sid,string $reason,int $actor=0): void {
    $db=aiDb();if(!$db->inTransaction()){ throw new LogicException('Chiusura della quest senza transazione.'); }
    $q=$db->query('SELECT * FROM ai_quests WHERE session_id=? FOR UPDATE',[$sid])[0]??null;
    if(!$q||$q['status']==='closed'){ return; }
    aiQuestSettle($sid,$reason);
    $db->execute('UPDATE sessioni SET run=0,dataout=?,masterout=? WHERE id=?',[time(),$actor,$sid]);
    $db->execute("UPDATE ai_quests SET status='closed',version=version+1 WHERE session_id=?",[$sid]);
    $db->execute("UPDATE ai_jobs SET status='cancelled',finished_at=?,error='Quest conclusa' WHERE session_id=? AND status IN('queued','running') AND kind<>'verdict'",[time(),$sid]);
    aiQuestPublish($sid,'quest:'.$sid.':closed','Quest conclusa · '.aiText($reason,450)."\nIl resoconto e le conseguenze registrate restano consultabili.");
    aiQuestAudit($sid,'closed',['reason'=>aiText($reason,450)],$actor);
}
