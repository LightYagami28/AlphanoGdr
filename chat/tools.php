<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/ai.inc.php';require_once __DIR__.'/libs/http-chat.php';require_once __DIR__.'/../libs/chat-presentation.inc.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');$db=aiDb();
try{
 $uid=alphanoRequireLogin(true);$chat=$_GET['chat']??'';if(!is_string($chat)){ throw new DomainException('Luogo non valido.',400); }httpChatRoom($chat,$uid);$u=alphanoUser();if($u['chat']!==$chat||$u['bloccato']){ throw new DomainException('Raggiungi questo luogo per usare i comandi della scena.',403); }
 if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['action']??'')==='presence'){$characters=[];foreach($db->query('SELECT id FROM utenti WHERE chat=? AND bloccato=0 ORDER BY nome',[$chat]) as $p){ $characters[]=alphanoChatCharacter('pg',(int)$p['id']); }foreach($db->query('SELECT id FROM ai_npcs WHERE chat_key=? AND active=1 ORDER BY name',[$chat]) as $n){ $characters[]=alphanoChatCharacter('npc',(int)$n['id']); }echo aiQuestJson(['characters'=>$characters]);exit;}
 if($_SERVER['REQUEST_METHOD']!=='POST'){ throw new DomainException('Metodo non consentito.',405); }alphanoRequireCsrf(true);$in=alphanoRequestJson();if(($in['action']??'')!=='dice'){ throw new DomainException('Comando non valido.',422); }$key=aiField($in,'request_id',80,true);if(!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$key)){ throw new DomainException('Identificativo non valido.',422); }
 $db->beginTransaction();$db->query('SELECT id FROM utenti WHERE id=? FOR UPDATE',[$uid]);$old=$db->query('SELECT result FROM chat_dice_rolls WHERE user_id=? AND request_key=?',[$uid,$key])[0]??null;
 if($old){$db->commit();echo aiQuestJson(['result'=>(int)$old['result']]);exit;}
 $s=ai_game_session($chat);$sid=(int)($s['session']['id']??0);$round=(int)($s['guidance']['round_number']??0);
 if($sid&&empty($s['session']['dadi'])){ throw new DomainException('I dadi non sono abilitati in questa quest. Il Master può attivarli dal Session Manager.',409); }
 $q=aiQuestRow($chat);if($q){$w=ai_turn_window($q,ai_game_guidance((int)$q['session_id']),$uid);if(!$w['mine']){ throw new DomainException('Il tiro è riservato al personaggio di turno.',409); }aiQuestCheckTurn($chat,$uid,'+ tiro di dado');}
 if($sid&&$db->query('SELECT id FROM chat_dice_rolls WHERE session_id=? AND round_number=? AND user_id=?',[$sid,$round,$uid])){ throw new DomainException('Hai già un tiro registrato per questo giro: il risultato non può essere rilanciato.',409); }
 if($db->query('SELECT id FROM chat_dice_rolls WHERE user_id=? AND created_at>?',[$uid,time()-10])){ throw new DomainException('Attendi qualche secondo prima di un altro tiro.',429); }
 $roll=random_int(1,20);$db->execute('INSERT INTO chat_dice_rolls(user_id,chat_key,session_id,round_number,request_key,result,created_at) VALUES(?,?,?,?,?,?,?)',[$uid,$chat,$sid,$round,$key,$roll,time()]);
 $receipt=(int)$db->lastInsertId();
 $text=htmlspecialchars(aiQuestName('pg',$uid),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').' tira un dado a venti facce: <strong>'.$roll.'</strong>.';
 $html=ai_dice_public_html(aiQuestName('pg',$uid),$roll,$receipt);if($sid){ aiQuestPublish($sid,'dice:'.$uid.':'.$key,'',$html); }else {require_once __DIR__.'/../libs/libchat.inc.php';insertIntoChat($chat,$html);}
 if($sid){ aiQuestAudit($sid,'dice',['player'=>$uid,'round'=>$round,'roll'=>$roll]); }$db->commit();echo aiQuestJson(['result'=>$roll]);
}catch(Throwable $e){if($db->inTransaction()){ $db->rollBack(); }$code=$e instanceof DomainException?$e->getCode():500;if($code<400||$code>499){ $code=500; }http_response_code($code);if($code===500){ error_log('[Comandi chat] '.$e->getMessage()); }echo aiQuestJson(['error'=>$code===500?'Comando non eseguito. Riprova.':$e->getMessage()]);}
