<?php
declare(strict_types=1);

const BG_GAME_BY_ID_SQL = 'SELECT * FROM board_games WHERE id=?';
const BG_PLAYERS_LOCK_SQL = 'SELECT id FROM utenti WHERE id IN (?,?) ORDER BY id FOR UPDATE';
const BG_GAME_LOCK_SQL = 'SELECT * FROM board_games WHERE id=? FOR UPDATE';

final class BoardGameRuntimeException extends RuntimeException {}
final class BoardGameDomainException extends DomainException {}
require_once __DIR__.'/player-runtime.inc.php';require_once __DIR__.'/libeconomia.inc.php';require_once __DIR__.'/rpg.inc.php';require_once __DIR__.'/board-game-engine.inc.php';require_once __DIR__.'/board-game-computer.inc.php';
require_once __DIR__.'/board-game-invitations.inc.php';
require_once __DIR__.'/profile-art.inc.php';
function bgReady():bool {static $r=null;return $r??=(bool)connectDB()->query("SHOW TABLES LIKE 'board_games'");}
function bgNow():int{return (int)floor(microtime(true)*1000);}
function bgSettings():array{
 $db=connectDB();$row=$db->query('SELECT settings_json FROM board_game_settings WHERE id=1')[0]??null;
 if(!$row){$defaults=file_get_contents(__DIR__.'/../config/board-games.json');$db->execute('INSERT IGNORE INTO board_game_settings(id,settings_json,updated_at) VALUES(1,?,?)',[$defaults,time()]);$row=$db->query('SELECT settings_json FROM board_game_settings WHERE id=1')[0];}
 return json_decode($row['settings_json'],true,32,JSON_THROW_ON_ERROR);
}
function bgRoom(string $room):array{return connectDB()->query('SELECT * FROM board_game_rooms WHERE chat_key=?',[$room])[0]??['chat_key'=>$room,'scacchi'=>0,'dama'=>0,'coins'=>0,'items'=>0,'materials'=>0];}
function bgActor(int $uid,string $room):array{require_once __DIR__.'/../chat/libs/http-chat.php';httpChatRoom($room,$uid);$u=connectDB()->query('SELECT id,nome,chat,bloccato,denari FROM utenti WHERE id=?',[$uid])[0]??[];if(!$u||$u['chat']!==$room||$u['bloccato']){ throw new BoardGameDomainException('Devi essere presente in questo luogo.',403); }return $u;}
function bgAudit(int $id,int $uid,string $action,array $details=[]):void{connectDB()->execute('INSERT INTO board_game_audit(game_id,actor_id,action,details_json,created_at) VALUES(?,?,?,?,?)',[$id,$uid,$action,rpg_json($details),time()]);}
/** Same portraits as the profile, with media permissions and transformation applied. */
function bgPlayers(array $m):array {
 $players=[];foreach(connectDB()->query('SELECT id,nome,razza,vampire_human_form,sesso,avatar,avatar2 FROM utenti WHERE id IN (?,?)',[$m['a'],$m['b']]) as $u){$art=alphanoProfilePortrait($u);$players[]=['id'=>(int)$u['id'],'nome'=>$u['nome'],'portrait'=>$art['url'],'fallback'=>$art['default']];}
 if($c=bgComputerInfo($m)){ $players[]=['id'=>(int)$m['b'],'nome'=>$c['name'].' · PNG','portrait'=>$c['portrait']??'/assets/placeholder.svg','fallback'=>'/assets/placeholder.svg']; }return $players;
}
function bgNotify(array $m,string $text):void{
 // Read the committed state in the current transaction, including administrative cancellations.
 $m=connectDB()->query(BG_GAME_BY_ID_SQL,[$m['id']])[0]??$m;
 $audiences=array_map(static fn($id)=>['scope'=>'personale','id'=>$id,'section'=>'giochi','role'=>'member'],array_values(array_filter([(int)$m['a'],(int)$m['b']],static fn($id)=>$id>0)));
 $terms=rpg_decode($m['terms_json']);$result=rpg_decode($m['result_json']);
 $presentation=['id'=>(int)$m['id'],'a'=>(int)$m['a'],'b'=>(int)$m['b'],'game'=>$m['game_key'],'status'=>$m['status'],'version'=>(int)$m['version'],'proposer'=>(int)$m['proposer'],'draw_by'=>(int)$m['draw_by'],'players'=>bgPlayers($m),'expires_at'=>(int)$m['created_at']+bgInviteLifetime($m),'terms'=>array_intersect_key($terms,array_flip(['total','per_move','increment'])),'result'=>$result];
 alphanoRecordActivity('board-game',0,$audiences,'Tavolo di '.($m['game_key']==='dama'?'Dama italiana':'Scacchi'),$text,['game_id'=>(int)$m['id'],'chat'=>$m['chat_key'],'presentation'=>$presentation]);
}
function bgPublicNotice(array $m,string $text):void{require_once __DIR__.'/libchat.inc.php';insertIntoChat($m['chat_key'],'<div class="board-game-notice"><span aria-hidden="true">♟</span> '.htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</div>');}
function bgTerms(array $input,array $s,array $room,int $a,int $b,?array $previous=null,int $editor=0):array {
 if(isset($input['stakes'])&&!is_array($input['stakes'])){ throw new BoardGameDomainException('Poste non valide.',422); }
 $t=['total'=>rpg_int($input['total']??$s['default_total'],$s['min_total'],$s['max_total'],'Tempo totale'),'per_move'=>rpg_int($input['per_move']??$s['default_move'],0,$s['max_move'],'Tempo per mossa'),'increment'=>rpg_int($input['increment']??$s['default_increment'],0,$s['max_increment'],'Incremento'),'color'=>(string)($input['color']??'random'),'rated'=>rpg_bool($input['rated']??true),'draw'=>rpg_bool($input['draw']??true),'stakes'=>[]];
 if(!in_array($t['color'],['white','black','random'],true)){ throw new BoardGameDomainException('Colore non valido.',422); }if(!$s['allow_rated']){ $t['rated']=false; }if(!$s['allow_draw']){ $t['draw']=false; }
 foreach([$a,$b] as $pid){$stake=$input['stakes'][(string)$pid]??['coins'=>0,'assets'=>[]];if(!is_array($stake)){ throw new BoardGameDomainException('Posta non valida.',422); }$coins=rpg_int($stake['coins']??0,0,$s['max_stake_coins'],alphanoCurrencyText('Posta in Corone'));$assets=$stake['assets']??[];if(!is_array($assets)||count($assets)>$s['max_stake_assets']){ throw new BoardGameDomainException('Troppi oggetti in palio.',422); }$assets=array_values(array_unique(array_map(static fn($x)=>rpg_int($x,1,PHP_INT_MAX,'Oggetto'),$assets)));if($coins&&(!$s['allow_coins']||!$room['coins'])){ throw new BoardGameDomainException(alphanoCurrencyText('Scommesse in Corone non consentite qui.'),422); }
  if($previous&&$pid!==$editor&&['coins'=>$coins,'assets'=>$assets]!==$previous['stakes'][$pid]){ throw new BoardGameDomainException('Puoi modificare solo la tua posta; l’altro giocatore deve formulare la propria controproposta.',422); }
  if(!$previous&&$pid!==$editor&&$assets){ throw new BoardGameDomainException('Solo il proprietario può proporre oggetti.',422); }
  if($pid===$editor&&$assets){$rows=connectDB()->query("SELECT p.id,t.tipoG FROM equippg p JOIN equip e ON e.id=p.uid JOIN tipo t ON t.id=e.tipo WHERE p.idpg=? AND p.id IN(".implode(',',array_fill(0,count($assets),'?')).") AND p.slot='' AND p.idpadre=0 AND NOT EXISTS(SELECT 1 FROM equippg child WHERE child.idpadre=p.id)",[$pid,...$assets]);if(count($rows)!==count($assets)){ throw new BoardGameDomainException('Proponi soltanto beni liberi del tuo inventario.',422); }foreach($rows as $r){ if($r['tipoG']==='risorsa'?(!$s['allow_materials']||!$room['materials']):(!$s['allow_items']||!$room['items'])){ throw new BoardGameDomainException('Categoria di posta non consentita nel luogo.',422); } }}
  $t['stakes'][$pid]=['coins'=>$coins,'assets'=>$assets];
 }
 return $t;
}
function bgInventory(int $uid):array{return connectDB()->query("SELECT p.id,e.shortDesc AS name,IF(t.tipoG='risorsa','material','item') AS kind FROM equippg p JOIN equip e ON e.id=p.uid JOIN tipo t ON t.id=e.tipo WHERE p.idpg=? AND p.slot='' AND p.idpadre=0 AND NOT EXISTS(SELECT 1 FROM equippg c WHERE c.idpadre=p.id) ORDER BY e.shortDesc,p.id LIMIT 500",[$uid]);}
function bgLockStakes(array $m,array $t,array $s,array $room):void{
 $d=connectDB();$users=$d->query('SELECT id,denari FROM utenti WHERE id IN (?,?) ORDER BY id FOR UPDATE',[$m['a'],$m['b']]);$by=array_column($users,null,'id');
 foreach([$m['a'],$m['b']] as $pid){$stake=$t['stakes'][$pid];if(!isset($by[$pid])||(int)$by[$pid]['denari']<$stake['coins']){ throw new BoardGameDomainException('Una delle poste non è più disponibile: riformulate l’invito.',409); }
  if($stake['coins']){$d->execute('UPDATE utenti SET denari=denari-? WHERE id=?',[$stake['coins'],$pid]);$d->execute('INSERT INTO board_game_escrow(game_id,owner_id,coins) VALUES(?,?,?)',[$m['id'],$pid,$stake['coins']]);}
  foreach($stake['assets'] as $asset){$r=$d->query("SELECT p.id,t.tipoG FROM equippg p JOIN equip e ON e.id=p.uid JOIN tipo t ON t.id=e.tipo WHERE p.id=? AND p.idpg=? AND p.slot='' AND p.idpadre=0 AND NOT EXISTS(SELECT 1 FROM equippg child WHERE child.idpadre=p.id) FOR UPDATE",[$asset,$pid])[0]??null;if(!$r){ throw new BoardGameDomainException('Oggetto non più libero nell’inventario.',409); }$material=$r['tipoG']==='risorsa';if($material?(!$s['allow_materials']||!$room['materials']):(!$s['allow_items']||!$room['items'])){ throw new BoardGameDomainException('Questa categoria di posta non è consentita nel luogo.',422); }
   $d->execute('UPDATE equippg SET idpg=0 WHERE id=? AND idpg=?',[$asset,$pid]);$d->execute('INSERT INTO board_game_escrow(game_id,owner_id,asset_id) VALUES(?,?,?)',[$m['id'],$pid,$asset]);
  }
 }
}
function bgComplete(array &$m,int $winner,string $reason,bool $void=false):void {
 if($m['status']!=='active'){ return; }if(bgComputerInfo($m)){bgComputerFinish($m,$winner,$reason,$void);return;}$d=connectDB();$s=bgSettings();$t=rpg_decode($m['terms_json']);$moves=rpg_decode($m['moves_json']);$count=count($moves);$now=time();$d->query(BG_PLAYERS_LOCK_SQL,[$m['a'],$m['b']]);
 $escrow=$d->query('SELECT * FROM board_game_escrow WHERE game_id=? AND released_at=0 FOR UPDATE',[$m['id']]);$prizes=[];
 foreach($escrow as $row){$to=$winner?: (int)$row['owner_id'];if($row['coins']){$d->execute('UPDATE utenti SET denari=denari+? WHERE id=?',[(int)$row['coins'],$to]);$prizes[]=['to'=>$to,'coins'=>(int)$row['coins']];}else{$owned=$d->query('SELECT p.idpg,e.shortDesc AS name FROM equippg p LEFT JOIN equip e ON e.id=p.uid WHERE p.id=? FOR UPDATE',[$row['asset_id']])[0]??null;if(!$owned||(int)$owned['idpg']!==0){ throw new BoardGameRuntimeException('Integrità della posta non valida: richiedere l’amministratore'); }$d->execute('UPDATE equippg SET idpg=? WHERE id=?',[$to,$row['asset_id']]);$prizes[]=['to'=>$to,'asset'=>(int)$row['asset_id'],'name'=>(string)($owned['name']??'Bene #'.$row['asset_id'])];}$d->execute('UPDATE board_game_escrow SET released_to=?,released_at=? WHERE id=?',[$to,$now,$row['id']]);}
 $sameNetwork=$m['a_net']!==''&&hash_equals($m['a_net'],$m['b_net']);$pairCount=(int)$d->query("SELECT COUNT(*) n FROM board_games WHERE status='finished' AND JSON_EXTRACT(result_json,'$.rated')=true AND ((a=? AND b=?) OR (a=? AND b=?)) AND finished_at>?",[$m['a'],$m['b'],$m['b'],$m['a'],$now-86400])[0]['n'];
 $rated=!$void&&$s['allow_rated']&&!empty($t['rated'])&&$count>=$s['min_rating_plies']&&($now-(int)$m['started_at'])>=$s['min_rating_seconds']&&$pairCount<$s['pair_rated_daily']&&(!$sameNetwork||$s['reward_shared_network']);
 $scores=[];$points=[];foreach([$m['a'],$m['b']] as $pid){ $d->execute('INSERT IGNORE INTO board_game_ratings(player_id,game_key) VALUES(?,?)',[$pid,$m['game_key']]); }$ratings=$d->query('SELECT * FROM board_game_ratings WHERE game_key=? AND player_id IN (?,?) ORDER BY player_id FOR UPDATE',[$m['game_key'],$m['a'],$m['b']]);$ratings=array_column($ratings,null,'player_id');
 foreach([$m['a'],$m['b']] as $pid){$other=$pid==$m['a']?$m['b']:$m['a'];$score=0; if($winner===0){ $score=.5; } elseif($winner===$pid){ $score=1; }$delta=$rated?(int)round($s['elo_k']*($score-1/(1+pow(10,($ratings[$other]['elo']-$ratings[$pid]['elo'])/400)))):0;$scores[$pid]=$delta;
  $reward=0;$rewardCount=(int)$d->query('SELECT COUNT(*) n FROM board_game_rewards WHERE player_id=? AND opponent_id=? AND points>0 AND created_at>?',[$pid,$other,$now-86400])[0]['n'];$daily=(int)$d->query('SELECT COALESCE(SUM(points),0) n FROM board_game_rewards WHERE player_id=? AND created_at>?',[$pid,$now-86400])[0]['n'];
  $unique=count(array_unique(array_column($moves,'position')));$each=min(count(array_filter($moves,static fn($v)=>$v['player']==$m['a'])),count(array_filter($moves,static fn($v)=>$v['player']==$m['b'])));
  if(!$void&&$s['reward_enabled']&&(!$sameNetwork||$s['reward_shared_network'])&&$each>=$s['reward_min_each']&&$unique>=max(1,$count*.6)&&$rewardCount<$s['reward_pair_daily']){ $reward=max(0,min(3,(int)floor($count/$s['reward_step_plies']),(int)floor($m['active_seconds']/$s['reward_step_active']),$s['reward_daily']-$daily)); }
  if($reward){$identity=rpg_identity('pg',(int)$pid);$p=rpg_progress('pg',(int)$pid,true);$stats=rpg_stat_values($identity,$p);$reward=min($reward,100-$stats['intelligence']);if($reward){$trained=rpg_decode($p['trained_stats']);$trained['intelligence']=(int)($trained['intelligence']??0)+$reward;$d->execute("UPDATE rpg_progress SET trained_stats=?,version=version+1 WHERE owner_kind='pg' AND owner_id=?",[rpg_json($trained),$pid]);}}
  $points[$pid]=$reward;$d->execute('INSERT INTO board_game_rewards VALUES(?,?,?,?,?)',[$m['id'],$pid,$other,$reward,$now]);
  $d->execute('UPDATE board_game_ratings SET elo=GREATEST(100,elo+?),wins=wins+?,losses=losses+?,draws=draws+?,games=games+?,intelligence=intelligence+? WHERE player_id=? AND game_key=?',[$delta,(int)(!$void&&$winner===$pid),(int)(!$void&&$winner>0&&$winner!==$pid),(int)(!$void&&$winner===0),(int)!$void,$reward,$pid,$m['game_key']]);
 }
 $clockState=rpg_decode($m['state_json']);$clockKey=$clockState['turn']===0?'clock_white':'clock_black';$m[$clockKey]=max(0,(int)$m[$clockKey]-max(0,bgNow()-(int)$m['turn_at']));
 $d->execute('UPDATE board_games SET clock_white=?,clock_black=? WHERE id=?',[$m['clock_white'],$m['clock_black'],$m['id']]);
 $result=['winner'=>$winner,'reason'=>$reason,'duration'=>max(0,$now-(int)$m['started_at']),'plies'=>$count,'prizes'=>$prizes,'intelligence'=>$points,'elo'=>$scores,'rated'=>$rated,'void'=>$void];
 $m['status']=$void?'cancelled':'finished';$m['winner']=$winner;$m['reason']=$reason;$m['finished_at']=$now;$m['result_json']=rpg_json($result);$m['version']++;
 $d->execute('UPDATE board_games SET status=?,winner=?,reason=?,finished_at=?,result_json=?,version=? WHERE id=?',[$m['status'],$winner,$reason,$now,$m['result_json'],$m['version'],$m['id']]);bgAudit((int)$m['id'],0,'settled',$result);bgNotify($m,'Partita conclusa: '.$reason.'. Poste assegnate o restituite.');bgPublicNotice($m,'La partita #'.$m['id'].' è conclusa: '.$reason.'.');
}
function bgExpire(array &$m):void{
 if($m['status']==='pending'&&time()>=(int)$m['created_at']+bgInviteLifetime($m)){connectDB()->execute("UPDATE board_games SET status='expired',version=version+1 WHERE id=?",[$m['id']]);$m['status']='expired';$m['version']++;bgNotify($m,'L’invito è scaduto. Nessuna posta è stata impegnata.');}
 if($m['status']!=='active'){ return; }$st=rpg_decode($m['state_json']);$t=rpg_decode($m['terms_json']);$elapsed=max(0,bgNow()-(int)$m['turn_at']);$clock=(int)$m[$st['turn']===0?'clock_white':'clock_black'];if($elapsed>=$clock||($t['per_move']>0&&$elapsed>=$t['per_move']*1000)){
  $loser=$st['turn']===0?(int)$m['white_id']:((int)$m['white_id']===(int)$m['a']?(int)$m['b']:(int)$m['a']);$winner=$loser===(int)$m['a']?(int)$m['b']:(int)$m['a'];
  if($m['game_key']==='scacchi'){$white=$winner===(int)$m['white_id'];$pieces=array_filter($st['board'],static fn($p)=>$p!==''&&(ctype_upper($p)===$white)&&strtolower($p)!=='k');$opp=array_filter($st['board'],static fn($p)=>$p!==''&&(ctype_upper($p)!==$white)&&strtolower($p)!=='k');if(!$pieces||(!$opp&&count($pieces)===1&&in_array(strtolower(array_values($pieces)[0]),['n','b'],true))){ $winner=0; }}
  bgComplete($m,$winner,$winner?'Tempo scaduto':'Tempo scaduto: materiale insufficiente');
 }
}
function bgTick():int{
 if(!bgReady()){ return 0; }$count=0;$d=connectDB();$now=bgNow();
 // Only due rows are examined, so pending invitations cannot starve expired clocks.
 $rows=$d->query("SELECT id FROM board_games WHERE (status='pending' AND created_at+IF(JSON_EXTRACT(terms_json,'$.presence_invite')=true,600,?)<=?) OR (status='active' AND (turn_at+IF(JSON_EXTRACT(state_json,'$.turn')=0,clock_white,clock_black)<=? OR (CAST(JSON_EXTRACT(terms_json,'$.per_move') AS UNSIGNED)>0 AND turn_at+CAST(JSON_EXTRACT(terms_json,'$.per_move') AS UNSIGNED)*1000<=?))) ORDER BY turn_at,id LIMIT 100",[bgSettings()['invite_seconds'],time(),$now,$now]);
 foreach($rows as $r){ economiaAtomicaResiliente(function()use($r,&$count){$m=connectDB()->query(BG_GAME_LOCK_SQL,[$r['id']])[0];$before=$m['status'];bgExpire($m);$count+=(int)($m['status']!==$before);}); }return $count;
}
function bgCanWatch(array $m,int $uid):bool{if(in_array($uid,[(int)$m['a'],(int)$m['b']],true)){ return true; }return (bool)connectDB()->query('SELECT source_id FROM board_game_interactions WHERE chat_key=? AND actor_id=? AND target_id IN (?,?) AND created_at>=? LIMIT 1',[$m['chat_key'],$uid,$m['a'],$m['b'],max((int)$m['created_at']-bgSettings()['spectator_hours']*3600,0)]);}
function bgNarrative(string $chat,int $actor,int $dest,string $raw,int $source):void{
 if(!bgReady()||$dest||preg_match('/^(?:\/\/|[$#&])/u',ltrim($raw))||mb_strlen(trim(strip_tags($raw)))<20){ return; }
 foreach(connectDB()->query('SELECT id,nome FROM utenti WHERE chat=? AND id<>? AND bloccato=0',[$chat,$actor]) as $p){ if(preg_match('/(?<![\pL\pN])'.preg_quote($p['nome'],'/').'(?![\pL\pN])/iu',$raw)){ connectDB()->execute('INSERT IGNORE INTO board_game_interactions VALUES(?,?,?,?,?)',[$chat,$actor,$p['id'],$source,time()]); } }
}
function bgAssets(array $m):array{
 $t=rpg_decode($m['terms_json']);$ids=[];foreach($t['stakes']??[] as $stake){ $ids=[...$ids,...$stake['assets']]; }$ids=array_values(array_unique(array_map('intval',$ids)));if(!$ids){ return []; }
 return connectDB()->query('SELECT p.id,e.shortDesc AS name FROM equippg p JOIN equip e ON e.id=p.uid WHERE p.id IN('.implode(',',array_fill(0,count($ids),'?')).') AND p.idpg IN(0,?,?)',[...$ids,$m['a'],$m['b']]);
}
function bgView(array $m,int $uid):array{
 bgAccess($m,$uid);if(!bgCanWatch($m,$uid)){ throw new BoardGameDomainException('Prima di osservare, interagisci narrativamente in questa chat nominando uno dei due giocatori.',403); }
 $computer=bgComputerInfo($m);$players=bgPlayers($m);$participant=in_array($uid,[(int)$m['a'],(int)$m['b']],true);$state=rpg_decode($m['state_json']);if(!$participant){ $state['legal']=[]; }
 // Older matches acquire presentation metadata without rewriting their moves or clocks.
 if($m['game_key']==='scacchi'&&!empty($state['fen'])&&!array_key_exists('check',$state)){ $state['check']=(new Onspli\Chess\FEN($state['fen']))->isCheck(); }
 $turnPlayer=0;
 if(isset($state['turn'])){
   if($state['turn']===0){ $turnPlayer=(int)$m['white_id']; }
   elseif((int)$m['a']===(int)$m['white_id']){ $turnPlayer=(int)$m['b']; }
   else{ $turnPlayer=(int)$m['a']; }
 }
 $moves=rpg_decode($m['moves_json']);return ['computer'=>$computer,'viewer'=>$uid,'turn_player'=>$turnPlayer,'can_move'=>$participant&&$m['status']==='active'&&$turnPlayer===$uid,'id'=>(int)$m['id'],'game'=>$m['game_key'],'chat'=>$m['chat_key'],'a'=>(int)$m['a'],'b'=>(int)$m['b'],'proposer'=>(int)$m['proposer'],'players'=>$players,'status'=>$m['status'],'version'=>(int)$m['version'],'terms'=>rpg_decode($m['terms_json']),'state'=>$state,'white_id'=>(int)$m['white_id'],'clock_white'=>(int)$m['clock_white'],'clock_black'=>(int)$m['clock_black'],'turn_at'=>(int)$m['turn_at'],'expires_at'=>(int)$m['created_at']+bgInviteLifetime($m),'server_now'=>bgNow(),'participant'=>$participant,'moves'=>$moves,'draw_by'=>(int)$m['draw_by'],'result'=>rpg_decode($m['result_json']),'assets'=>bgAssets($m),'stakes'=>connectDB()->query('SELECT owner_id,asset_id,coins,released_to,released_at FROM board_game_escrow WHERE game_id=?',[$m['id']])];
}
function bgAction(int $uid,array $in):array{
 $action=playerText($in,'action',30,true);if($action==='computer_start'){ return bgComputerStart($uid,$in); }if(isset($in['terms'])&&!is_array($in['terms'])){ throw new BoardGameDomainException('Condizioni non valide.',422); }$d=connectDB();$s=bgSettings();
 return economiaAtomicaResiliente(function()use($uid,$in,$action,$d,$s){
  if($action==='invite'){
   $chat=playerText($in,'chat',100,true);$presence=($in['source']??'')==='presence';if(!$presence){ bgActor($uid,$chat); }$room=bgRoom($chat);$game=playerText($in,'game',12,true);if(!$s['enabled']||!in_array($game,['scacchi','dama'],true)||!$s[$game]||!$room[$game]){ throw new BoardGameDomainException('Gioco non abilitato in questo luogo.',403); }
   $other=rpg_int($in['opponent']??0,1,PHP_INT_MAX);if($other===$uid){ throw new BoardGameDomainException('Scegli un altro personaggio.',422); }if($presence){ bgInviteOptions($uid,$other,$chat); }else { bgActor($other,$chat); }$d->query(BG_PLAYERS_LOCK_SQL,[$uid,$other]);$request=playerText($in,'request_key',80,true);if(!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$request)){ throw new BoardGameDomainException('Identificativo invito non valido.',422); }
   if($old=$d->query('SELECT * FROM board_games WHERE a=? AND request_key=?',[$uid,$request])[0]??null){$terms=bgTerms($in['terms']??[],$s,$room,$uid,$other,null,$uid);if($presence){ $terms['presence_invite']=true; }$original=$d->query("SELECT details_json FROM board_game_audit WHERE game_id=? AND action='invite' ORDER BY id LIMIT 1",[$old['id']])[0]??null;if($old['chat_key']!==$chat||$old['game_key']!==$game||(int)$old['b']!==$other||!$original||rpg_decode($original['details_json'])!=$terms){ throw new BoardGameDomainException('Identificativo invito già usato con altre condizioni.',409); }return bgView($old,$uid);}
   if((int)$d->query('SELECT COUNT(*) n FROM board_games WHERE a=? AND created_at>?',[$uid,time()-300])[0]['n']>=5){ throw new BoardGameDomainException('Troppi inviti: attendi qualche minuto.',429); }
   $terms=bgTerms($in['terms']??[],$s,$room,$uid,$other,null,$uid);if($presence){ $terms['presence_invite']=true; }$d->execute("INSERT INTO board_games(game_key,chat_key,a,b,proposer,status,terms_json,state_json,moves_json,created_at,result_json,a_net,request_key) VALUES(?,?,?,?,?,'pending',?,'{}','[]',?,'{}',?,?)",[$game,$chat,$uid,$other,$uid,rpg_json($terms),time(),hash('sha256',$_SERVER['REMOTE_ADDR']??''),$request]);$id=(int)$d->lastInsertId();$m=$d->query(BG_GAME_BY_ID_SQL,[$id])[0];bgAudit($id,$uid,'invite',$terms);bgNotify($m,($presence?'Invito dai presenti · valido 10 minuti. Raggiungi la chat scelta: ':'Nuova sfida: ').'accetta, rifiuta o proponi modifiche dal tavolo della chat. Nessuna posta è ancora impegnata.');return bgView($m,$uid);
  }
  $id=rpg_int($in['id']??0,1,PHP_INT_MAX);$m=$d->query(BG_GAME_LOCK_SQL,[$id])[0]??null;if(!$m){ throw new BoardGameDomainException('Partita non trovata.',404); }bgAccess($m,$uid);if(!in_array($uid,[(int)$m['a'],(int)$m['b']],true)){ throw new BoardGameDomainException('Solo i giocatori possono modificare la partita.',403); }
  bgExpire($m);if(in_array($m['status'],['finished','cancelled','expired','declined'],true)){ return bgView($m,$uid); }if($action==='refresh'){ return bgView($m,$uid); }if((int)($in['version']??0)!==(int)$m['version']){ throw new BoardGameDomainException('La partita è cambiata: aggiorna il tavolo.',409); }
  $t=rpg_decode($m['terms_json']);$room=bgRoom($m['chat_key']);
  if(in_array($action,['accept','counter','decline','withdraw'],true)){
   if($m['status']!=='pending'){ throw new BoardGameDomainException('Questo invito non è più aperto.',409); }
   if($action==='decline'||$action==='withdraw'){$m['status']='declined';$d->execute("UPDATE board_games SET status='declined',version=version+1 WHERE id=?",[$id]);$m['version']++;bgNotify($m,'L’invito è stato ritirato o rifiutato. Nessuna posta addebitata.');return bgView($m,$uid);}
   if(!$s['enabled']||!$s[$m['game_key']]||!$room[$m['game_key']]){ throw new BoardGameDomainException('Il gioco è stato disabilitato: ritira l’invito.',409); }
   if($action==='counter'){$presence=!empty($t['presence_invite']);$t=bgTerms($in['terms']??[],$s,$room,(int)$m['a'],(int)$m['b'],$t,$uid);if($presence){bgOnlinePlayer((int)$m['a']);bgOnlinePlayer((int)$m['b']);$t['presence_invite']=true;}$d->execute('UPDATE board_games SET terms_json=?,proposer=?,version=version+1 WHERE id=?',[rpg_json($t),$uid,$id]);$m['terms_json']=rpg_json($t);$m['proposer']=$uid;$m['version']++;$d->execute('UPDATE board_games SET '.($uid===(int)$m['a']?'a_net':'b_net').'=? WHERE id=?',[hash('sha256',$_SERVER['REMOTE_ADDR']??''),$id]);bgAudit($id,$uid,'counter',$t);bgNotify($m,'Le condizioni della sfida sono cambiate: serve una nuova accettazione.');return bgView($m,$uid);}
   if((int)$m['proposer']===$uid){ throw new BoardGameDomainException('Deve accettare l’altro giocatore.',409); }
   // Revalidate current global limits before atomically reserving both stakes.
   $presence=!empty($t['presence_invite']);$t=bgTerms($t,$s,$room,(int)$m['a'],(int)$m['b'],$t,$uid);if($presence){bgOnlinePlayer((int)$m['a']);bgOnlinePlayer((int)$m['b']);$t['presence_invite']=true;}bgActor((int)$m['a'],$m['chat_key']);bgActor((int)$m['b'],$m['chat_key']);$d->query(BG_PLAYERS_LOCK_SQL,[$m['a'],$m['b']]);
   if($d->query("SELECT id FROM board_games WHERE status='active' AND (a IN (?,?) OR b IN (?,?)) LIMIT 1 FOR UPDATE",[$m['a'],$m['b'],$m['a'],$m['b']])){ throw new BoardGameDomainException('Un giocatore ha già una partita attiva.',409); }
   bgLockStakes($m,$t,$s,$room);$white=match($t['color']){'white'=>(int)$m['a'],'black'=>(int)$m['b'],default=>random_int(0,1)?(int)$m['a']:(int)$m['b']};$state=bgEngine($m['game_key'],[]);
   $d->execute("UPDATE board_games SET status='active',version=version+1,white_id=?,state_json=?,clock_white=?,clock_black=?,turn_at=?,started_at=?,".($uid===(int)$m['a']?'a_net':'b_net')."=? WHERE id=?",[$white,rpg_json($state),$t['total']*1000,$t['total']*1000,bgNow(),time(),hash('sha256',$_SERVER['REMOTE_ADDR']??''),$id]);$m=$d->query(BG_GAME_BY_ID_SQL,[$id])[0];bgAudit($id,$uid,'accepted',$t);bgNotify($m,'La partita è iniziata. Le poste sono impegnate fino all’esito.');bgPublicNotice($m,'Inizia una partita di '.($m['game_key']==='dama'?'Dama italiana':'Scacchi').' al tavolo #'.$id.'. La scena narrativa continua.');return bgView($m,$uid);
  }
  if($m['status']!=='active'){ throw new BoardGameDomainException('La partita non è attiva.',409); }$st=rpg_decode($m['state_json']);
  if($action==='resign'){bgComplete($m,$uid==(int)$m['a']?(int)$m['b']:(int)$m['a'],'Abbandono');return bgView($m,$uid);}
  if($action==='draw'&&bgComputerInfo($m)){bgComplete($m,0,'Patta di allenamento');return bgView($m,$uid);}
  if($action==='draw') {if(!$t['draw']){ throw new BoardGameDomainException('Patta concordata non consentita.',422); }if((int)$m['draw_by']&&$uid!==(int)$m['draw_by']){bgComplete($m,0,'Patta concordata');return bgView($m,$uid);}if((int)$m['draw_by']===$uid){ return bgView($m,$uid); }$d->execute('UPDATE board_games SET draw_by=?,version=version+1 WHERE id=?',[$uid,$id]);$m['draw_by']=$uid;$m['version']++;bgNotify($m,'È stata proposta una patta: esaminala dal tavolo.');return bgView($m,$uid);}
  if($action!=='move'){ throw new BoardGameDomainException('Azione non valida.',422); }$turn=$st['turn']===0?(int)$m['white_id']:((int)$m['white_id']===(int)$m['a']?(int)$m['b']:(int)$m['a']);if($turn!==$uid){ throw new BoardGameDomainException('Non è il tuo turno.',409); }$move=playerText($in,'move',60,true);if(!in_array($move,array_column($st['legal'],'id'),true)){ throw new BoardGameDomainException('Mossa illegale.',422); }
  bgCommitMove($m,$uid,$move);bgComputerReply($m);return bgView($m,$uid);
 });
}
function bgCommitMove(array &$m,int $actor,string $move):void{
 $d=connectDB();$id=(int)$m['id'];$st=rpg_decode($m['state_json']);$t=rpg_decode($m['terms_json']);if(!in_array($move,array_column($st['legal'],'id'),true)){ throw new BoardGameDomainException('Mossa illegale.',422); }
  $moves=rpg_decode($m['moves_json']);$next=bgEngine($m['game_key'],[...array_column($moves,'move'),$move]);$elapsed=max(0,bgNow()-(int)$m['turn_at']);$clockKey=$st['turn']===0?'clock_white':'clock_black';$m[$clockKey]=max(0,(int)$m[$clockKey]-$elapsed)+$t['increment']*1000;$m['active_seconds']+=(int)min(30,floor($elapsed/1000));$moves[]=['move'=>$move,'player'=>$actor,'at'=>time(),'spent_ms'=>$elapsed,'position'=>$m['game_key']==='scacchi'?implode(' ',array_slice(explode(' ',$next['fen']),0,4)):$next['fen']];$m['state_json']=rpg_json($next);$m['moves_json']=rpg_json($moves);$m['version']++;
  $d->execute('UPDATE board_games SET state_json=?,moves_json=?,version=?,'.$clockKey.'=?,active_seconds=?,turn_at=?,draw_by=0 WHERE id=?',[$m['state_json'],$m['moves_json'],$m['version'],$m[$clockKey],$m['active_seconds'],bgNow(),$id]);$m['turn_at']=bgNow();$m['draw_by']=0;bgAudit($id,$actor,'move',['move'=>$move,'ply'=>count($moves)]);
  if($next['result']!==null){
   $winner=0;
   if($next['result']==='white'){
    if((int)$m['white_id']===(int)$m['a']){ $winner=(int)$m['b']; }
    else{ $winner=(int)$m['a']; }
   }
   bgComplete($m,$winner,$winner?'Vittoria sulla scacchiera':'Patta regolamentare');
  }elseif(count($moves)>=512){ bgComplete($m,0,'Limite di sicurezza delle mosse'); }
}
function bgLobby(int $uid,string $chat):array{
 bgActor($uid,$chat);$s=bgSettings();$room=bgRoom($chat);$rows=connectDB()->query("SELECT g.id,g.a,g.b,g.proposer,g.status,g.game_key AS game,(g.b<0) AS computer,g.version,g.created_at,u.nome AS name_a,COALESCE(v.nome,CONCAT(JSON_UNQUOTE(JSON_EXTRACT(g.terms_json,'$.computer.name')),' · PNG')) AS name_b FROM board_games g JOIN utenti u ON u.id=g.a LEFT JOIN utenti v ON v.id=g.b WHERE g.chat_key=? AND (g.status IN('pending','active') OR g.finished_at>?) ORDER BY g.id DESC LIMIT 60",[$chat,time()-3600]);return ['npcs'=>bgComputerNpcs($chat),'viewer'=>$uid,'settings'=>$s,'room'=>$room,'games'=>$rows,'players'=>connectDB()->query('SELECT id,nome FROM utenti WHERE chat=? AND id<>? AND bloccato=0 ORDER BY nome',[$chat,$uid]),'inventory'=>bgInventory($uid)];
}
function bgHistory(int $player,int $page=1):array{
 $page=max(1,min(10000,$page));$rows=connectDB()->query("SELECT g.id,g.chat_key,g.game_key,g.a,g.b,g.winner,g.reason,g.finished_at,g.result_json,COALESCE(u.nome,'Personaggio non più presente') AS name_a,COALESCE(v.nome,CONCAT(JSON_UNQUOTE(JSON_EXTRACT(g.terms_json,'$.computer.name')),' · PNG'),'Personaggio non più presente') AS name_b FROM board_games g LEFT JOIN utenti u ON u.id=g.a LEFT JOIN utenti v ON v.id=g.b WHERE g.status IN('finished','cancelled') AND (a=? OR b=?) ORDER BY g.id DESC LIMIT 101 OFFSET ".(($page-1)*100),[$player,$player]);$more=count($rows)>100;if($more){ array_pop($rows); }return ['ratings'=>connectDB()->query('SELECT * FROM board_game_ratings WHERE player_id=?',[$player]),'matches'=>$rows,'page'=>$page,'more'=>$more];
}
function bgRanking(string $game,int $page):array{if(!in_array($game,['scacchi','dama'],true)){ throw new BoardGameDomainException('Gioco non valido.',422); }if(!bgSettings()['allow_rated']){ return []; }return connectDB()->query('SELECT r.*,u.nome FROM board_game_ratings r JOIN utenti u ON u.id=r.player_id WHERE r.game_key=? AND r.games>0 AND u.bloccato=0 ORDER BY r.elo DESC,r.wins DESC,r.player_id LIMIT 50 OFFSET '.(max(0,$page-1)*50),[$game]);}
function bgAdminSave(int $uid,array $in):array{
 $u=alphanoUser();if(empty($u['admin'])){ throw new BoardGameDomainException('Riservato agli amministratori.',403); }$d=connectDB();
 return economiaAtomicaResiliente(function()use($uid,$in,$d){$action=$in['action'];
  if($action==='admin_void'){$id=rpg_int($in['id']??0,1,PHP_INT_MAX);$m=$d->query(BG_GAME_LOCK_SQL,[$id])[0]??null;if(!$m){ throw new BoardGameDomainException('Partita assente.',404); }$reason=playerText($in,'reason',250,true);if($m['status']==='active'){ bgComplete($m,0,'Annullata dallo staff: '.$reason,true); }elseif($m['status']==='pending'){$result=['winner'=>0,'reason'=>'Invito annullato dallo staff: '.$reason,'duration'=>0,'plies'=>0,'prizes'=>[],'intelligence'=>[],'elo'=>[],'rated'=>false,'void'=>true];$d->execute("UPDATE board_games SET status='cancelled',finished_at=?,reason=?,result_json=?,version=version+1 WHERE id=?",[time(),$result['reason'],rpg_json($result),$id]);bgNotify($m,'L’invito è stato annullato dallo staff: '.$reason.'. Nessuna posta impegnata.');}bgAudit($id,$uid,'admin_void',['reason'=>$reason]);return ['success'=>true];}
  $before=bgSettings();$values=$in['settings']??[];$bounds=['min_total'=>[30,7200],'max_total'=>[60,86400],'max_move'=>[0,86400],'max_increment'=>[0,600],'default_total'=>[30,86400],'default_move'=>[0,86400],'default_increment'=>[0,600],'invite_seconds'=>[30,86400],'max_stake_coins'=>[0,1000000],'max_stake_assets'=>[0,50],'elo_k'=>[1,64],'min_rating_plies'=>[2,200],'min_rating_seconds'=>[0,86400],'pair_rated_daily'=>[0,10],'reward_daily'=>[0,10],'reward_pair_daily'=>[0,3],'reward_step_plies'=>[10,100],'reward_step_active'=>[60,3600],'reward_min_each'=>[5,100],'spectator_hours'=>[1,168]];
  foreach($before as $k=>$v){if(!array_key_exists($k,$values)){ throw new BoardGameDomainException('Impostazione mancante: '.$k,422); }$before[$k]=is_bool($v)?rpg_bool($values[$k]):rpg_int($values[$k],$bounds[$k][0],$bounds[$k][1],$k);}
  if($before['min_total']>$before['default_total']||$before['default_total']>$before['max_total']||$before['default_move']>$before['max_move']||$before['default_increment']>$before['max_increment']){ throw new BoardGameDomainException('I tempi predefiniti devono rientrare nei limiti.',422); }
  $row=$d->query('SELECT revision FROM board_game_settings WHERE id=1 FOR UPDATE')[0];if((int)($in['revision']??0)!==(int)$row['revision']){ throw new BoardGameDomainException('Impostazioni cambiate: ricarica il pannello.',409); }
  $d->execute('UPDATE board_game_settings SET settings_json=?,revision=revision+1,updated_by=?,updated_at=? WHERE id=1',[rpg_json($before),$uid,time()]);
  $rooms=$in['rooms']??[];if(!is_array($rooms)||count($rooms)>500){ throw new BoardGameDomainException('Elenco luoghi non valido.',422); }foreach($rooms as $r){$key=rpg_text($r['chat_key']??'',100,true);if(!$d->query('SELECT id FROM mappe WHERE chat_key=? AND chat=1',[$key])){ throw new BoardGameDomainException('Luogo non valido.',422); }$flags=[];foreach(['scacchi','dama','coins','items','materials'] as $k){ $flags[]=(int)rpg_bool($r[$k]??false); }$d->execute('INSERT INTO board_game_rooms VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE scacchi=VALUES(scacchi),dama=VALUES(dama),coins=VALUES(coins),items=VALUES(items),materials=VALUES(materials)',[$key,...$flags]);}
  bgAudit(0,$uid,'settings',['settings'=>$before,'rooms'=>$rooms]);return ['success'=>true,'revision'=>(int)$row['revision']+1];
 });
}
function bgSaveRoom(int $uid,string $room,array $flags):void{
 if(empty(alphanoUser()['admin'])){ throw new BoardGameDomainException('Riservato alla gestione.',403); }if(!connectDB()->query('SELECT id FROM mappe WHERE chat_key=? AND chat=1',[$room])){ throw new BoardGameDomainException('Luogo non valido.',422); }$values=[];foreach(['scacchi','dama','coins','items','materials'] as $key){ $values[]=(int)rpg_bool($flags[$key]??false); }economiaAtomicaResiliente(function()use($uid,$room,$values){connectDB()->execute('INSERT INTO board_game_rooms VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE scacchi=VALUES(scacchi),dama=VALUES(dama),coins=VALUES(coins),items=VALUES(items),materials=VALUES(materials)',[$room,...$values]);bgAudit(0,$uid,'room',['chat'=>$room,'flags'=>$values]);});
}
