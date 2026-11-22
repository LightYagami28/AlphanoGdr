<?php
declare(strict_types=1);
require_once __DIR__ . '/regex-any.inc.php';
/** A single reading of existing pair scores; this creates no emotion or consent. */
function aiRelationFeelings(array $r):array {
    $romantic=in_array($r['state']??'',['innamoramento','relazione','crisi','riconciliazione'],true)&&(int)($r['love']??0)>=50;
    $secure=(int)($r['trust']??0)>=60&&(int)($r['respect']??0)>=20&&(int)($r['affection']??0)>=60&&(int)($r['fear']??0)<20&&(int)($r['hostility']??0)<20;
    return ['romantic'=>$romantic,'close'=>$romantic&&$secure&&($r['state']??'')!=='crisi','jealous'=>(int)($r['jealousy']??0)>=60];
}
/** Familiar address belongs to a verified, secure couple in a personal scene.
 * This changes conversational register, never consent, authority or physical outcomes.
 */
function aiRelationPersonalScene(array $npc,array $job):bool {
    $nid=(int)($npc['id']??0);$uid=(int)($job['user_id']??0);
    if($nid<1||$uid<1||!preg_match('/^(?:rent_|pvt_)/D',(string)($job['chat_key']??''))){ return false; }
    if(in_array($job['kind']??'',['demo','reaction','observe'],true)||!empty($job['episode']['observer'])||!empty($job['episode']['declined'])||in_array($job['episode']['category']??'',['minaccia','offesa'],true)){ return false; }
    if(($job['kind']??'')==='npc-turn'&&(($job['quest_decision']??'')!=='dialogo'||!empty($job['quest_threat']))){ return false; }
    $reading=aiNarrativeParse((string)($job['episode_input']??$job['prompt']??''));
    if(in_array($reading['intent'],['assalto','arresto','potere','furto'],true)||aiRecognitionAnonymous($npc,$job)){ return false; }
    if(!aiRelationFeelings(aiRelation($nid,$uid))['close']){ return false; }
    if(!isset($npc['profile'])){ $npc=aiDb()->query('SELECT * FROM ai_npcs WHERE id=?',[$nid])[0]??$npc; }
    if(function_exists('aiPerceptionSceneState')){
        $scene=aiPerceptionSceneState($npc,$job);
        if(!empty($scene['alert'])||!empty($scene['confirmed_hold'])){ return false; }
    }
    $player=aiPlayerIdentity($uid);
    return $player&&aiRomanceAllowed($npc,$player,aiSocialRules($nid));
}
/** The current state is authoritative; the old conversation remains historical evidence. */
function aiRelationFeelingsContext(array $npc,array $job,array $r):string {
    $f=aiRelationFeelings($r);$uid=(int)($job['user_id']??0);
    if(!$f['romantic']||$uid<1||in_array($job['kind']??'',['reaction','observe'],true)||!aiRomanceAllowed($npc,aiPlayerIdentity($uid),aiSocialRules((int)$npc['id']))){ return ''; }
    if(($job['kind']??'')==='npc-turn'){
        // Handing a peaceful conversation to the Master does not erase its
        // established bond. Urgent action still takes priority over romance.
        $quest=aiQuestRow((string)($job['chat_key']??''));
        if(($job['quest_decision']??'')!=='dialogo'||!empty($job['quest_threat'])||!$quest||aiSceneKind($quest)!=='narrazione'||!empty(aiPerceptionSceneState($npc,$job)['alert'])){ return ''; }
    }
    $out="\nSENTIMENTO ATTUALE: sei già innamorat".(aiNpcProfile($npc)['sex']==='Femmina'?'a':'o').' di questo interlocutore. ';
    $out.=$f['close']?'Esprimi calore, tenerezza e piacere di stare insieme: l’amore è già presente, non un corteggiamento da ricominciare. ':'L’amore convive con la crisi o i timori attuali: rendi questa ambivalenza senza negare il sentimento. ';
    $out.='Le vecchie battute ostili descrivono il passato e non annullano questo stato. ';
    if($f['jealous']){ $out.='La gelosia è intensa: può farti temere di perdere il legame, non possedere l’altra persona. '; }
    return $out.'Amore, disponibilità a una visita e consenso a un gesto restano distinti.';
}
/** Only audited pair corrections, never arbitrary memory edits, supersede an old refusal. */
function aiRelationLastCorrection(int $npc,int $player):int {
    return (int)(aiDb()->query("SELECT created_at FROM ai_relationship_events WHERE npc_id=? AND player_id=? AND actor_id>0 AND JSON_EXTRACT(after_state,'$.state') IS NOT NULL AND JSON_EXTRACT(after_state,'$.love') IS NOT NULL ORDER BY id DESC LIMIT 1",[$npc,$player])[0]['created_at']??0);
}
/** Reject explicit present-tense denial of a currently established, safe romantic bond.
 * Specific refusals, consent, past reports and uncertainty are not a denial of love.
 */
function aiRelationFeelingsReplyProblem(array $npc,array $job,string $text):?string {
    $uid=(int)($job['user_id']??0);if($uid<1||aiRecognitionAnonymous($npc,$job)||!aiRelationFeelings(aiRelation((int)$npc['id'],$uid))['close']){ return null; }
    if(!aiRomanceAllowed($npc,aiPlayerIdentity($uid),aiSocialRules((int)$npc['id']))){ return null; }
    $speech=aiNarrativeParse($text)['speech'];
    foreach(preg_split('/(?<=[.!?;])\s*/u',$speech,-1,PREG_SPLIT_NO_EMPTY)?:[] as $sentence){
        if(aiRegexAny(['/\b(?:prima)\b/iu','/\b(?:allora)\b/iu','/\b(?:un tempo)\b/iu','/\b(?:credevo)\b/iu','/\b(?:pensavo)\b/iu','/\b(?:dicevo)\b/iu','/\b(?:dicevi)\b/iu','/\b(?:dicevate)\b/iu','/\b(?:ho detto)\b/iu','/\b(?:avevo detto)\b/iu','/\b(?:se)\b/iu','/\b(?:forse)\b/iu','/\?$/iu'],$sentence)){ continue; }
        if(preg_match('/non\s+(?:ti|vi)\s+amo\s+(?:meno|solo|soltanto|perch[ée])/iu',$sentence)){ continue; }
        if(aiRegexAny(['/\bnon\s+(?:(?:ti|vi)\s+amo)\b/iu','/\bnon\s+(?:sono\s+(?:più\s+)?innamorat[oa](?!\s+di\s+(?!(?:te)\b)))\b/iu','/\bnon\s+(?:sono\s+(?:più\s+)?innamorat[oa](?!\s+di\s+(?!(?:voi)\b)))\b/iu','/\bnon\s+(?:provo\s+(?:alcun\s+)?(?:amore)(?!\s+per\s+(?!(?:te)\b)))\b/iu','/\bnon\s+(?:provo\s+(?:alcun\s+)?(?:amore)(?!\s+per\s+(?!(?:voi)\b)))\b/iu','/\bnon\s+(?:provo\s+(?:alcun\s+)?(?:sentimenti)(?!\s+per\s+(?!(?:te)\b)))\b/iu','/\bnon\s+(?:provo\s+(?:alcun\s+)?(?:sentimenti)(?!\s+per\s+(?!(?:voi)\b)))\b/iu','/\b(?:per\s+me\s+)?(?:sei)\s+soltanto\s+un[ao]?\s+amic[oa]\b/iu','/\b(?:per\s+me\s+)?(?:siete)\s+soltanto\s+un[ao]?\s+amic[oa]\b/iu','/\b(?:provo|sento)\s+soltanto\s+amicizia\b/iu'],$sentence)){ return 'Il legame attuale registra già amore e fiducia: non negare il sentimento presente ripetendo vecchi rifiuti. Puoi mantenere i tuoi limiti e rifiutare uno specifico gesto o invito.'; }
    }
    return null;
}
