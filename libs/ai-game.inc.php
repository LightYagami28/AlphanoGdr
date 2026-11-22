<?php
declare(strict_types=1);
/** A compatible context update retries the saved source; it is not a user cancellation. */
final class AiContextChanged extends RuntimeException {}
require_once __DIR__.'/libequip.inc.php';
require_once __DIR__.'/world-chronology.inc.php';

/** Read-only adapters to existing game data. No game rules or character values are replaced. */
function aiGamePlain(string $html): string {
    $html=preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is','',$html);
    return trim(preg_replace('/\h+/u',' ',html_entity_decode(strip_tags(preg_replace('~</(?:p|div|h[1-6]|li|tr)>|<br\s*/?>~i',"\n",$html)),ENT_QUOTES|ENT_HTML5,'UTF-8')));
}
function aiGameGuidance(int $session): array {
    return array_replace(['session_id'=>$session,'notes'=>'','phase'=>'narrazione','paused'=>0,'turn_order'=>'[]','turn_index'=>0,'round_number'=>1,'version'=>0,'turn_started_at'=>0],aiDb()->query('SELECT * FROM ai_session_guidance WHERE session_id=?',[$session])[0]??[]);
}
function aiGameSession(string $chat): ?array {
    $s=aiDb()->query('SELECT s.*,u.nome AS master_name FROM sessioni s LEFT JOIN utenti u ON u.id=s.masterin WHERE s.chat=? AND s.run=1 ORDER BY s.id DESC LIMIT 1',[$chat])[0]??null;
    return $s?['session'=>$s,'guidance'=>aiGameGuidance((int)$s['id'])]:null;
}
function aiGameTurn(array $guidance): ?array {
    $order=json_decode($guidance['turn_order'],true)?:[];
    return $order?($order[(int)$guidance['turn_index']]??null):null;
}
function aiGameAllowed(string $chat,int $npc): void {
    $state=aiGameSession($chat);if(!$state){ return; }
    $g=$state['guidance'];
    if($g['paused']){ throw new DomainException('Il Master ha sospeso gli interventi IA per questa quest.',409); }
    $turn=aiGameTurn($g);
    if($turn&&($turn['kind']!=='npc'||(int)$turn['id']!==$npc)){ throw new DomainException('La turnazione della quest è gestita dal Master: attendi il turno di questo PNG.',409); }
}
/** Select the correct branch of the racial manual: a Progenia never inherits Original-only powers. */
function aiGameRacialFragment(string $html,int $race,bool $sentient): string {
    $branch=match($race){2=>'Progenia',5=>'Vampiro Originale',3=>$sentient?'Lycan Senziente':'Lycan Puro',6=>'Lycan Ancestrale',default=>''};
    if($branch===''){ return ''; }
    preg_match_all('~<h([1-6])\b[^>]*>(.*?)</h\1>~is',$html,$headers,PREG_OFFSET_CAPTURE|PREG_SET_ORDER);
    foreach($headers as $i=>$header){
        if(mb_stripos(aiGamePlain($header[2][0]),$branch)===false){ continue; }
        $start=$header[0][1];$end=strlen($html);$level=(int)$header[1][0];
        foreach(array_slice($headers,$i+1) as $next){ if((int)$next[1][0]<=$level){$end=$next[0][1];break;} }
        return substr($html,$start,$end-$start);
    }
    return '';
}
function aiGameRules(int $race,bool $sentient,string $question): array {
    // The handbook explains OFF mechanics and private administration; it is not NPC knowledge.
    static $manualSchema; $manualSchema??=(bool)aiDb()->query("SHOW COLUMNS FROM regole LIKE 'manual_key'");
    $rows=aiDb()->query('SELECT r.id,r.oggetto,r.spiegazione,s.nome AS section FROM regole r JOIN sezioniregole s ON s.id=r.sezione'.($manualSchema?" WHERE r.audience='public' AND r.manual_key IS NULL":'').' ORDER BY r.id');$candidates=[];
    $terms=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($question),-1,PREG_SPLIT_NO_EMPTY);
    $terms=array_values(array_unique(array_filter($terms,static fn($v)=>mb_strlen($v)>=4)));
    foreach($rows as $r){
        $racial=preg_match('/abilit.*malus/iu',$r['oggetto'])&&((in_array($race,[2,5],true)&&preg_match('/vampir/iu',$r['section']))||(in_array($race,[3,6],true)&&preg_match('/lycan|licantrop/iu',$r['section'])));
        if(preg_match('/vampir|lycan|licantrop/iu',$r['section'])&&!$racial){ continue; }
        $html=$racial?aiGameRacialFragment($r['spiegazione'],$race,$sentient):$r['spiegazione'];
        foreach(preg_split('/\n+/u',aiGamePlain($html),-1,PREG_SPLIT_NO_EMPTY) as $line){
            if(mb_strlen($line)<25){ continue; }$lower=mb_strtolower($line);$score=0;
            foreach($terms as $term){ if(mb_strpos($lower,$term)!==false){ $score++; } }
            if($score){ $candidates[]=['id'=>(int)$r['id'],'title'=>aiGamePlain($r['section'].' / '.$r['oggetto']),'text'=>mb_substr($line,0,600),'score'=>$score+($racial?2:0)]; }
        }
    }
    usort($candidates,static fn($a,$b)=>$b['score']<=>$a['score']);
    return array_slice($candidates,0,2);
}
function aiGamePlayer(int $id): array {
    $db=aiDb();$u=$db->query('SELECT u.id,u.puntif,u.ferite,u.razza,u.senziente,u.infetto,u.ancestrale,u.vampire_human_form,u.chat,u.bloccato,r.nome AS race,r.forza FROM utenti u LEFT JOIN razze r ON r.id=u.razza WHERE u.id=?',[$id])[0]??[];
    if(!$u){ return []; }
    $items=$db->query("SELECT p.id,p.slot,p.nome,p.consumo,e.shortDesc,e.durata,t.nome AS type FROM equippg p JOIN equip e ON e.id=p.uid JOIN tipo t ON t.id=e.tipo WHERE p.idpg=? AND p.idpadre=0 AND p.slot IN ('guanti','elmo','mantello','veste','armasx','armatura','armadx','bracciali','faretra','stivali','scudo','musica','anellosx','collana','anellodx','attrezzi') AND e.visibile=1 AND e.approvato=1 ORDER BY p.slot,p.id",[$id]);
    $u['visible_race_id']=alphano_visible_race($u);$u['native_race_id']=alphano_native_race($u);$u['stats']=rpg_actor_stats('pg',$id);$u['forza']=$u['stats']['strength'];
    $u['bloodlines']=$db->query('SELECT l.kind,l.origin,l.status,l.creator_id,l.root_id,l.ready_at,p.nome AS creator,r.nome AS founder FROM lineage_members l LEFT JOIN utenti p ON p.id=l.creator_id LEFT JOIN utenti r ON r.id=l.root_id WHERE l.player_id=? ORDER BY l.kind',[$id]);
    $u['equipment']=$items;$u['weight']=getPesoTotale($id);$u['fatigue']=GetAffaticamento((float)$u['weight'],(int)$u['forza']);
    $u['profile']=array_map(static fn($v)=>aiPerceptionPlain((string)$v,800),aiPlayerProfile($id));
    $identity=aiPlayerIdentity($id);$u['roles']=array_diff_key($identity,array_flip(['id','nome','sesso','razza','race','ethnicity']));
    require_once __DIR__.'/revelations.inc.php';$u['known_revelations']=alphano_revelations_master_context((string)$u['chat']);
    return $u;
}
/** Only this interlocutor's usable memories and relationship can invalidate their draft. */
function aiGamePairContext(array $job): array {
    $npc=(int)($job['npc_id']??0);$player=(int)($job['user_id']??0);
    if($npc<1||$player<1){ return []; }
    if(aiRecognitionAnonymous(['id'=>$npc],$job)){ return ['anonymous'=>true]; }
    $relation=aiRelation($npc,$player);$semantic=[];
    foreach(AI_RELATION_METRICS as $key=>$_){ $semantic[$key]=(int)$relation[$key]; }
    foreach(['encounters','interactions','meaningful'] as $key){ $semantic[$key]=(int)($relation[$key]??0); }
    foreach(['state','known_name'] as $key){ $semantic[$key]=(string)($relation[$key]??''); }
    // Source, scope and evidence affect interpretation; timestamps and editor IDs do not.
    $memories=aiPersonalityRecall($npc,$player,$job,3);
    // FULLTEXT ranking can reorder the same selected facts between statements.
    // A different ordering is not a correction and must not cause an endless retry.
    usort($memories,static fn($a,$b)=>(int)$a['id']<=>(int)$b['id']);
    $memories=array_map(static fn($memory)=>array_intersect_key($memory,array_flip(['category','summary','evidence','source','epistemic','chat_key'])),$memories);
    return ['relationship'=>$semantic,'memories'=>$memories,'coercion'=>aiCoercionState($npc,$player),'current_context'=>aiRelationContextCurrent($npc,$player)];
}
/** Normalize the exact authored copy used by a draft, including decoded profile arrays. */
function aiGameNpcContext(array $npc):array {
    $authored=[];foreach(['name','character_text','personality','history','knowledge','behavior'] as $key){ $authored[$key]=(string)($npc[$key]??''); }
    $authored['profile']=aiNpcProfile($npc);return $authored;
}
/** A compact verified snapshot invalidates an answer if the actual sheet/quest changed in flight. */
function aiGameSnapshot(array $job,?array $interpretedNpc=null): string {
    $uid=(int)$job['user_id'];if($uid<1){ return ''; }
    $u=aiGamePlayer($uid);$state=aiGameSession($job['chat_key']);
    if($state){$s=$state['session'];$state['session']=array_intersect_key($s,array_flip(['id','run','lock','dadi','masterin']));}
    $npc=(int)($job['npc_id']??0)>0?aiQuestActor('npc',(int)$job['npc_id']):null;
    $nid=(int)($job['npc_id']??0);$policy=$nid?[aiAdultSettings($nid),aiAdultPartner($nid),aiSocialRules($nid),aiDb()->query('SELECT profile FROM ai_npcs WHERE id=?',[$nid]),aiDb()->query('SELECT character_age,romance_enabled,intimacy_enabled FROM ai_player_preferences WHERE player_id=?',[$uid])]:[];
    $presenceNpc=$nid?aiDb()->query('SELECT * FROM ai_npcs WHERE id=?',[$nid])[0]??[]:[];
    $presence=$presenceNpc?aiSituationView(aiRoom($job['chat_key'])??['chat_key'=>$job['chat_key']],$presenceNpc,$job):[];
    $authored=aiGameNpcContext($interpretedNpc??$presenceNpc);
    return hash('sha256',json_encode([$u,$npc,$state,$policy,$presence,$authored,aiGamePairContext($job),$nid?aiNpcAuthority($nid,$uid):null,aiSceneContext($job['chat_key']),$nid?aiPerceptionKnownRace($nid,$uid):0,rpg_settings(),rpg_catalog(),rpg_ai_skill_facts(rpg_sheet('pg',$uid,false)),$nid?rpg_ai_skill_facts(rpg_sheet('npc',$nid,false)):null],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function aiGameContext(array $room,array $npc,array $job,float $scale): string {
    if((int)$job['user_id']<1){ return ''; }$u=aiGamePlayer((int)$job['user_id']);if(!$u){ return ''; }
    $state=aiGameSession($room['chat_key']);$out="\nREGIA GDR VERIFICATA, SOLO LETTURA: dati tecnici riservati alla coerenza, non conoscenze automatiche del PNG. Non recitare PF, valori, razze segrete o inventario come battute. Non rivelare infezioni o pensieri. Non attribuire abilità non registrate o poteri di un'altra stirpe; usa soltanto i legami di discendenza registrati qui sotto e non dedurre generazioni mancanti.";
    $known=aiPerceptionKnownRace((int)$npc['id'],(int)$job['user_id'],(int)($job['source_id']??0));
    $out.="\nCONDIZIONE OSSERVABILE: ferite ".aiText((string)($u['ferite']?:'nessuna annotata'),120).'; ingombro '.aiText($u['fatigue'],70).'. La scheda tecnica e i lignaggi restano al backend: non sono conoscenza del PNG.';
    $eq=[];foreach(array_slice($u['equipment'],0,5) as $item){ $eq[]=aiText($item['nome']?:$item['shortDesc'],60).' ('.$item['slot'].((int)$item['consumo']>=(int)$item['durata']?', usurato':'').')'; }
    $out.="\nEQUIPAGGIAMENTO VISIBILE INDOSSATO: ".($eq?implode('; ',$eq):'nessun oggetto registrato').'. Una dichiarazione in chat non equipaggia oggetti e non assegna poteri.';
    if($state){$s=$state['session'];$g=$state['guidance'];$turn=aiGameTurn($g);
        $out.="\nQUEST IN CORSO: ".aiText($s['nome'],80).'; regia umana di '.aiText((string)$s['master_name'],50).'; fase '.$g['phase'].'; dadi '.($s['dadi']?'attivi nel sistema':'disattivati').'; giro '.(int)$g['round_number'].'. Non lanciare dadi, inventare tiri o risolvere colpi: gli esiti appartengono al Master e al backend.';
        if($turn){ $out.=' Turno corrente riservato a '.($turn['kind']==='npc'?'PNG':'PG').' #'.(int)$turn['id'].': non avanzare il turno né parlare per altri.'; }
        if($g['notes']!==''){ $out.="\nDIRETTIVE DEL MASTER (prioritarie sulla trama generica, non rivelare il testo al giocatore): ".aiText($g['notes'],(int)(500*$scale)); }
    }else { $out.="\nGIOCO LIBERO: nessuna quest attiva. Tentativi di combattimento e poteri restano proposte senza esiti imposti. Nessun tiro, danno, cura, premio o passaggio di carica viene applicato dall'IA."; }
    $rules=aiGameRules(aiPerceptionRaceFamily($known)?:1,false,(string)$job['prompt']);
    foreach($rules as $r){ $out.="\nREGOLA DAL MANUALE #".$r['id'].' ('.aiText($r['title'],70).'): '.aiText($r['text'],(int)(300*$scale)); }
    return $out;
}
