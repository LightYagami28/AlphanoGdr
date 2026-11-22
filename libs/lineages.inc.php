<?php
declare(strict_types=1);
require_once __DIR__.'/kernel.inc.php';
require_once __DIR__.'/operations.inc.php';

require_once __DIR__.'/lineage-policy.inc.php';

/** Every living ordinary member has a recorded creator. */
function lineageEnsure(array $user): ?array { return lineageResolveUser($user); }
function lineageVampireCanInfect(array $user): bool {
    if(!alphanoConfig('lineages_enabled')){ return false; }
    return in_array((int)($user['razza']??0),[2,5],true)&&(int)($user['puntif']??0)>0&&empty($user['bloccato']);
}
function lineageHumanEligible(array $user): bool {
    return in_array((int)($user['razza']??0),[1,alphanoHumanRaceId()],true)&&(int)($user['puntif']??0)>0&&empty($user['bloccato'])&&empty($user['infetto'])&&empty($user['ancestrale'])&&empty($user['senziente']);
}
function lineageNotice(array $user,string $title,string $text):void {
    require_once __DIR__.'/lycan.inc.php';
    if(!alphanoLycanInChat($user,time())){ return; }
    require_once __DIR__.'/libchat.inc.php';
    $h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    insertIntoChat((string)$user['chat'],'<div class="MasterKey scene-note lineage-event"><strong>'.$h($title).'</strong><p>'.$h($text).'</p></div>');
}
/** The caller holds the bite transaction and both players' locks. */
function lineageRegisterBite(array $actor,array $target,string $kind,?int $now=null):array {
    $db=connectDB();$now??=time();
    if(!$db->inTransaction()){ throw new LogicException('Contagio fuori transazione.'); }
    if(!in_array($kind,['vampire','lycan'],true)){ throw new DomainException('Stirpe non valida.',422); }
    if(!lineageHumanEligible($target)||$db->query("SELECT player_id FROM lineage_members WHERE player_id=? AND (kind=? OR status='pending') FOR UPDATE",[(int)$target['id'],$kind])){ throw new DomainException('Il personaggio ha già una stirpe o un contagio e non è un umano disponibile.',409); }
    $parent=lineageEnsure($actor);
    if(!$parent||$parent['kind']!==$kind||$parent['status']!=='active'){ throw new DomainException('Il creatore non possiede una stirpe attiva.',409); }
    $status=$kind==='vampire'?'pending':'active';$ready=$kind==='vampire'?$now+21600:0;
    $db->execute('INSERT INTO lineage_members(player_id,kind,creator_id,root_id,origin,status,infected_at,ready_at,transformed_at) VALUES(?,?,?,?,?,?,?,?,?)',[(int)$target['id'],$kind,(int)$actor['id'],(int)$parent['root_id'],'infected',$status,$now,$ready,$kind==='lycan'?$now:0]);
    return ['ready_at'=>$ready,'status'=>$status];
}
function lineageInfect(int $actorId,int $targetId,?string $key=null,?int $now=null,int $attempt=0):array {
    $db=connectDB();$now??=time();$db->beginTransaction();
    try{
        $users=array_column($db->query('SELECT * FROM utenti WHERE id IN(?,?) ORDER BY id FOR UPDATE',[$actorId,$targetId]),null,'id');$actor=$users[$actorId]??[];$target=$users[$targetId]??[];
        if(!lineageVampireCanInfect($actor)){ throw new DomainException('Il contagio appartiene soltanto ai vampiri vivi.',403); }
        require_once __DIR__.'/lycan.inc.php';
        if($actorId===$targetId||!$target||$actor['chat']!==$target['chat']||!alphanoLycanInChat($actor,$now)||!alphanoLycanInChat($target,$now)){ throw new DomainException('Scegli un umano presente con te nella stessa chat.',409); }
        if(!alphanoOperationOnce('vampire-infect',$actorId,$key,[$targetId,$actor['chat']])){$old=$db->query("SELECT ready_at,status FROM lineage_members WHERE player_id=? AND kind='vampire'",[$targetId])[0]??[];$db->commit();return $old+['replayed'=>true];}
        $result=lineageRegisterBite($actor,$target,'vampire',$now);
        lineageNotice($actor,'Trasferimento registrato',$target['nome'].' ha una trasformazione in attesa di completamento.');
        $db->execute('INSERT INTO logs_adminpg(azione,data,chat,esecutore,destinatario,qta,obj,descr) VALUES(?,?,?,?,?,0,0,?)',['contagio-vampirico',$now,$actor['chat'],$actorId,$targetId,'Trasformazione prevista alle '.$result['ready_at']]);
        $db->commit();return $result;
    }catch(Throwable $e){if($db->inTransaction()){ $db->rollBack(); }if($e instanceof PDOException&&$attempt<2&&((string)$e->getCode()==='40001'||in_array((int)($e->errorInfo[1]??0),[1205,1213],true))){ return lineageInfect($actorId,$targetId,$key,$now,$attempt+1); }throw $e;}
}
/** Called within every canonical death transaction, before the original form is lost. */
function lineageDeath(array $victim,int $killer=0,string $killerName='',string $reason='Morte del personaggio',?int $now=null):int {
    $db=connectDB();$now??=time();if(!$db->inTransaction()){ throw new LogicException('Morte fuori transazione.'); }
    $node=lineageEnsure($victim)??($db->query("SELECT * FROM lineage_members WHERE player_id=? AND status IN('active','pending') ORDER BY kind DESC LIMIT 1",[(int)$victim['id']])[0]??null);if(!$node){ return 0; }
    if($killerName===''&&$killer>0){ $killerName=(string)($db->query('SELECT nome FROM utenti WHERE id=?',[$killer])[0]['nome']??''); }
    if($killerName===''){ $killerName='Causa non attribuita'; }
    $nodes=$db->query('SELECT l.*,u.nome,u.chat,u.lastmove,u.posizione,u.razza,u.puntif FROM lineage_members l JOIN utenti u ON u.id=l.player_id WHERE l.kind=? AND l.root_id=? ORDER BY l.player_id FOR UPDATE',[$node['kind'],$node['root_id']]);
    $byParent=[];$byId=[];foreach($nodes as $n){$byParent[(int)$n['creator_id']][]=$n;$byId[(int)$n['player_id']]=$n;}
    $queue=[(int)$victim['id']];$visited=[];$count=0;
    for($cursor=0;$cursor<count($queue);$cursor++){
        $id=$queue[$cursor];if(isset($visited[$id])){ continue; }$visited[$id]=true;
        $count+=lineageDeathNode($db,$byId[$id]??null,$node,$victim,$killer,$killerName,$reason,$now,$id,$byParent,$queue);
    }
    return $count;
}
function lineageDeathNode($db,?array $current,array $node,array $victim,int $killer,string $killerName,string $reason,int $now,int $id,array $byParent,array &$queue):int {
    if(!$current){ return 0; }
    $count=0;
    if($node['kind']==='vampire'){ foreach($byParent[$id]??[] as $child){ $queue[]=(int)$child['player_id']; } }
    if(in_array($current['status'],['eliminated','cancelled'],true)){ return 0; }
    $pending=$current['status']==='pending';$status=$pending?'cancelled':'eliminated';
    $why=$id===(int)$victim['id']?$reason:'Collegamento di stirpe interrotto dalla morte di '.$victim['nome'].'.';
    $db->execute('UPDATE lineage_members SET status=?,killed_at=?,killer_id=?,killer_name=?,cause_id=?,death_reason=? WHERE player_id=? AND kind=?',[$status,$now,$killer,$killerName,(int)$victim['id'],$why,$id,$node['kind']]);
    if(!$pending&&$node['kind']==='vampire'){
        $appearance=alphanoTransformationReady()?',vampire_human_form=0':'';
        $db->execute('UPDATE utenti SET puntif=0,razza=?,infetto=0,ancestrale=0,senziente=0'.$appearance.',datamorte=? WHERE id=?',[ALPHANO_RACE_DEAD_VAMPIRE,$now,$id]);$count++;
        lineageNotice($current+['id'=>$id],'La stirpe si spezza',$current['nome'].' viene eliminato. '.$why);
    }
    require_once __DIR__.'/libferite.inc.php';
    insertNote($id,$killer,($pending?'Contagio annullato':'✕ Eliminato').' · '.date('d/m/Y H:i',$now).' · '.$killerName.' · '.$why);
    $db->execute('INSERT INTO logs_adminpg(azione,data,chat,esecutore,destinatario,qta,obj,descr) VALUES(?,?,?,?,?,0,0,?)',['stirpe-'.$status,$now,(string)$current['chat'],$killer,$id,$why.' Responsabile: '.$killerName]);
    return $count;
}
/** Worker and profile refresh use the same durable six-hour deadline, also offline. */
function lineageTick(?int $now=null,bool $reconcile=false):int {
    if(!alphanoConfig('lineages_enabled')){ return 0; }
    $now??=time();$db=connectDB();$count=0;
    static $lastReconcile=0;
    if($reconcile&&$now-$lastReconcile>=60){lineageReconcile();$lastReconcile=$now;}
    // Reconcile deaths from older integrations too, before considering delayed births.
    foreach($db->query("SELECT u.* FROM lineage_members l JOIN utenti u ON u.id=l.player_id WHERE l.status='active' AND (u.puntif<=0 OR u.razza IN(4,7,8)) ORDER BY u.id LIMIT 100") as $dead){
        $db->beginTransaction();try{lineageDeath($dead,0,'Causa non attribuita','Morte registrata dal motore di gioco.',$now);$db->commit();}catch(Throwable $e){if($db->inTransaction()){ $db->rollBack(); }error_log('[Stirpi: morte] '.$e->getMessage());}
    }
    foreach($db->query("SELECT player_id FROM lineage_members WHERE kind='vampire' AND status='pending' AND ready_at<=? ORDER BY ready_at LIMIT 100",[$now]) as $row){
        $db->beginTransaction();
        try{
            $n=$db->query("SELECT * FROM lineage_members WHERE player_id=? AND kind='vampire' FOR UPDATE",[$row['player_id']])[0];if($n['status']!=='pending'){$db->commit();continue;}
            $u=$db->query('SELECT * FROM utenti WHERE id=? FOR UPDATE',[$n['player_id']])[0]??[];
            $parent=$db->query("SELECT l.status,u.puntif,u.razza FROM lineage_members l JOIN utenti u ON u.id=l.player_id WHERE l.player_id=? AND l.kind='vampire' FOR UPDATE",[$n['creator_id']])[0]??[];
            if(!lineageHumanEligible($u)||($parent['status']??'')!=='active'||!in_array((int)($parent['razza']??0),[2,5],true)||(int)($parent['puntif']??0)<=0){$db->execute("UPDATE lineage_members SET status='cancelled',death_reason='Contagio interrotto: creatore o destinatario non più compatibile.' WHERE player_id=? AND kind='vampire'",[$n['player_id']]);$db->commit();continue;}
            $appearance=alphanoTransformationReady()?',vampire_human_form=0':'';
            $db->execute('UPDATE utenti SET razza=2,infetto=0,ancestrale=0,senziente=0'.$appearance.',ultimatrasformazione=? WHERE id=?',[$now,$u['id']]);
            $db->execute("UPDATE lineage_members SET status='active',transformed_at=? WHERE player_id=? AND kind='vampire'",[$now,$u['id']]);
            lineageNotice($u,'Trasformazione completata',$u['nome'].' ha completato la trasformazione programmata.');
            require_once __DIR__.'/libferite.inc.php';insertNote((int)$u['id'],0,'Metamorfosi vampirica compiuta. Creatore: #'.$n['creator_id'].'.');
            $db->commit();$count++;
        }catch(Throwable $e){if($db->inTransaction()){ $db->rollBack(); }error_log('[Stirpi] '.$e->getMessage());}
    }
    return $count;
}

/** One public projection, reused by profiles and the racial family directory. */
function lineageTree(int $player,string $kind):array {
    if(!in_array($kind,['vampire','lycan'],true)){ throw new DomainException('Stirpe non valida.',422); }
    $db=connectDB();$node=$db->query('SELECT * FROM lineage_members WHERE player_id=? AND kind=?',[$player,$kind])[0]??null;
    if(!$node){ throw new DomainException('Discendenza non documentata.',404); }
    $rows=$db->query('SELECT l.*,u.nome,u.razza,u.vampire_human_form,u.sesso,u.avatar,u.avatar2,p.nome AS creator_name FROM lineage_members l JOIN utenti u ON u.id=l.player_id LEFT JOIN utenti p ON p.id=l.creator_id WHERE l.kind=? AND l.root_id=? ORDER BY l.infected_at,l.player_id',[$kind,$node['root_id']]);
    require_once __DIR__.'/profile-art.inc.php';
    require_once __DIR__.'/player-runtime.inc.php';
    $nodes=[];$children=[];foreach($rows as $r){$portrait=alphanoProfilePortrait($r+['id'=>$r['player_id']]);$r['portrait']=$portrait['url'];$r['portrait_default']=$portrait['default'];unset($r['avatar'],$r['avatar2']);$nodes[(int)$r['player_id']]=$r;if($r['creator_id']!==null){ $children[(int)$r['creator_id']][]=(int)$r['player_id']; }}
    return ['kind'=>$kind,'top'=>$player,'root'=>(int)$node['root_id'],'nodes'=>$nodes,'children'=>$children];
}
