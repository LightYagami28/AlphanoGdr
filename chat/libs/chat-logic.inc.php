<?php
declare(strict_types=1);

use Alphano\Database\PDOMySQLDriver;
require_once __DIR__.'/../../libs/chat-format.inc.php';

function alphanoChatEarlyResult(PDOMySQLDriver $db,int $idpg,string $chat,string $rawMsg,string $location,string $position):?array {
 $str=trim($rawMsg);$blocked=$str==='';
 if(!$blocked){
  setPosizione($position!==''?$position:$chat,$idpg,$chat);
  $pg=$db->query("SELECT * FROM utenti WHERE id = ?",[$idpg])[0]??null;
  if(!$pg||(int)$pg['bloccato']===1){ $blocked=true; }
  elseif($pg['imprigionato']){
   $prig=$db->query("SELECT prigione FROM city WHERE id = ?",[(int)$pg['imprigionato']])[0]['prigione']??'';
   $blocked=strcasecmp($prig,$chat)!==0;
  }
  if(!$blocked){
   $run=$db->query("SELECT id,run,dadi,partecipanti,`lock` FROM sessioni WHERE chat = ? AND run = 1",[$chat])[0]??[];
   $blocked=!empty($run['lock'])&&empty($pg['fatepower']);
  }
 }
 return $blocked?['',0,$location,'0']:null;
}
function alphanoChatCommandSpeech(string $str,string $ora,string $razImg,string $name,string $fPar,string $fAzo,callable $render,bool $SessionIsRunning,bool $NeedsDadi,array $pg,string &$newLoc,string &$location):string {
 $out='';
 if(str_starts_with($str,'//')){ return '<span class="String">'.$ora.'</span> '.$razImg.$fPar.$name.'</B></SPAN> <span class="chat-ooc-text">'.htmlspecialchars(substr($str,2),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</span>'; }
 if(str_starts_with($str,'+')){
  $azione=substr($str,1);
  if($pg['imprigionato']){ $location='prigione'; $azione='[Prigione]'.$azione; }
  elseif(preg_match('/^\s*(\[[^\]]+\])/',$azione,$m)){ $newLoc=strtolower(trim($m[1])); }
  $out=$fAzo.$ora.'</SPAN>'.$fAzo.$razImg.$name.' '.$render($azione,true).'</SPAN>';
  if($SessionIsRunning&&$NeedsDadi){ $out.='&nbsp;<img height="10" width="10" src="/chatimg/dadod20.gif">&nbsp;'.rand(1,20); }
  return $out.'</SPAN>';
 }
 return '<span class="String">'.$ora.'</span> '.$razImg.$name.' '.$render(substr($str,1));
}
function alphanoChatCommandMaster(string $str,array $pg,bool $SessionIsRunning,string $location):array|string {
 $first=$str[0]??'';
 $out='';
 if($first==='#'){
  if(($pg['fatepower']||$pg['admin'])&&($SessionIsRunning||$pg['admin'])){
   $cmd=htmlentities(substr($str,1));
   return "<TABLE class=\"column-width-100\"><TR><TD class=\"MasterKey\">{$cmd}</TD></TR></TABLE>";
  }
  return $out;
 }
 if(!($pg['fatepower']||!empty($GLOBALS['private_chat']))){ return $out; }
 if($first==='%'){
  $txt=htmlentities(substr($str,1));
  return "<TABLE class=\"column-width-100\"><TR><TD class=\"DescrKey\">{$txt}</TD></TR></TABLE>";
 }
 $url=substr($str,1);
 if(!preg_match('~^(https?://|/(?!/))~i',$url)){ return ['',0,$location,'0']; }
 return "<DIV class=\"align-center\"><img src=\"".htmlspecialchars($url)."\" /></DIV>";
}
function alphanoChatCommandSocial(string $str,array $pg,PDOMySQLDriver $db,int $idpg,string $ora,string $razImg,string $name,string $fPar,string $fAzo,string $araldica,callable $render,bool $incog,int &$dest,string &$newLoc):string {
 $first=$str[0]??'';
 if($first==='$'){
  if(!str_contains($str,';')){ return ''; }
  [$dst,$msg2]=explode(';',substr($str,1),2);
  $dest=GetPGID(trim($dst))?:0;
  return $dest?'<EM>'.$razImg.$araldica.$fPar.$name.'</B></SPAN><SPAN class="String"><U>'.$render($msg2).'</U></SPAN></EM>':'';
 }
 if($first==='&'){
  if(!str_contains($str,';')){ return ''; }
  [$anim,$act]=explode(';',substr($str,1),2);
  $row=$db->query("SELECT ea.shortDesc,epg.nome,epgm.id FROM equip ea JOIN equippg epg ON epg.uid=ea.id JOIN equippg epgm ON epgm.idpg=epg.idpg WHERE epg.idpg=? AND epg.nome=? LIMIT 1",[$idpg,$anim])[0]??null;
  if(!$row){ return ''; }
  consuma($idpg,(int)$row['id']);
  return $fAzo.$ora.'&nbsp;'.htmlentities($row['nome']).', '.htmlentities($row['shortDesc']).' di '.$name.', '.htmlentities($act).'</SPAN>';
 }
 if($first==='|'){ return $incog?'<span class="StringN">'.$ora.' <strong>PNG</strong> '.htmlentities(substr($str,1),ENT_QUOTES|ENT_SUBSTITUTE).'</span>':''; }
 if(preg_match('/^\s*(\[[^\]]+\])/',$str,$m)){ $newLoc=strtolower(trim($m[1])); }
 return '<SPAN class="String">'.$ora.'</SPAN>'.$razImg.$araldica.$fPar.$name.'</B></SPAN> <SPAN class="String">'.$render($str).'</SPAN>';
}
function alphanoChatCommandOutput(
 string $str,array $pg,PDOMySQLDriver $db,int $idpg,string $ora,string $razImg,string $name,string $fPar,string $fAzo,string $araldica,
 callable $render,bool $SessionIsRunning,bool $NeedsDadi,bool $incog,int &$dest,string &$newLoc,string &$location,string &$l,bool &$OnGame
): array|string {
 $first=$str[0]??'';
 if(in_array($first,['/','+'],true)){ return alphanoChatCommandSpeech($str,$ora,$razImg,$name,$fPar,$fAzo,$render,$SessionIsRunning,$NeedsDadi,$pg,$newLoc,$location); }
 if(in_array($first,['#','%','@'],true)){ $result=alphanoChatCommandMaster($str,$pg,$SessionIsRunning,$location); }
 else{ $result=alphanoChatCommandSocial($str,$pg,$db,$idpg,$ora,$razImg,$name,$fPar,$fAzo,$araldica,$render,$incog,$dest,$newLoc); }
 if(is_array($result)){ return $result; }
 if($result!==''&&in_array($first,['#','%','@'],true)){ $OnGame=false; }
 return $result;
}
function alphanoChatVampireDamage(PDOMySQLDriver $db,int $idpg,array $pg,string $chat,string $sesso,string $ferite,int $razza,int $meteo,string $out,bool $vampiredamage):void {
 if((!function_exists('rpgCreationReady')||!rpgCreationReady())&&$vampiredamage&&$out!==''&&$razza===2&&!licanTime()&&$meteo===0){
  if(!str_contains($ferite,'Bruciature solari')){ $ferite.=' Bruciature solari'; }
  if((int)$pg['puntif']>MAXPUNTIF/10){
   $db->execute('UPDATE utenti SET puntif=puntif-?,ferite=? WHERE id=?',[MAXPUNTIF/10,$ferite,$idpg]);
   vampireDamage($chat,$pg['nome'],$sesso,false);
  }else{
   $db->execute('UPDATE utenti SET puntif=?,razza=7,ferite="" WHERE id=?',[MAXPUNTIF,$idpg]);
   vampireDamage($chat,$pg['nome'],$sesso,true);
  }
 }
}
function alphanoChatSessionLogs(PDOMySQLDriver $db,int $sessionName,string $chat,string $out,bool $scriviPart,string $partecipanti,int $idpg):void {
 if(!empty($GLOBALS['alphano_defer_chat_log'])){ $GLOBALS['alphano_deferred_chat_session']=$sessionName; }
 else{ file_put_contents(HTMLROOT."/sessione/log/{$sessionName}.html",$out."<br>\n",FILE_APPEND|LOCK_EX); }
 if($scriviPart){ $db->execute('UPDATE sessioni SET partecipanti=? WHERE id=?',[$partecipanti.$idpg.';',$sessionName]); }
 $ids=array_filter(explode(';',$partecipanti),static fn($v)=>$v!==''&&$v!=$idpg);
 if($ids&&!$db->query('SELECT session_id FROM ai_quests WHERE session_id=?',[$sessionName])){
  $ph=implode(',',array_fill(0,count($ids),'?'));
  $db->execute("UPDATE utenti SET denari=denari+10 WHERE id IN ($ph) AND chat=?",[...$ids,$chat]);
 }
}
function alphanoChatRecordings(PDOMySQLDriver $db,string $chat,string $out):void {
 foreach($db->query('SELECT id FROM registrazioni WHERE chat=?',[$chat]) as $r){
  $db->execute('INSERT INTO registrazionichat (registrazione,testo) VALUES (?,?)',[(int)$r['id'],$out]);
 }
}
/**
 * buildChatMessage() – logica pari‐pari di addLchat.inc.php
 * restituisce [html, dest, location, lican]
 */
function buildChatMessage(
 PDOMySQLDriver $db,int $idpg,string $chat,string $rawMsg,int $umore,string $location,bool $vampiredamage,string $position=''
): array {
 initSession();
 $early=alphanoChatEarlyResult($db,$idpg,$chat,$rawMsg,$location,$position);
 if($early!==null){ return $early; }
 $str=trim($rawMsg);
 $pg=$db->query("SELECT * FROM utenti WHERE id = ?",[$idpg])[0];
 $run=$db->query("SELECT id,run,dadi,partecipanti,`lock` FROM sessioni WHERE chat = ? AND run = 1",[$chat])[0]??[];
 $SessionIsRunning=(bool)($run['run']??false);
 $sessionName=(int)($run['id']??0);
 $NeedsDadi=(bool)($run['dadi']??false);
 $partecipanti=(string)($run['partecipanti']??'');
 if($partecipanti===''){ $partecipanti=';'; }
 $scriviPart=!str_contains($partecipanti,";{$idpg};");
 $meteo=(int)(getMeteo()['cielo']??0);
 $ora=date('H:i');
 $name=htmlentities($pg['nome'],ENT_QUOTES|ENT_SUBSTITUTE);
 if($umore){ $name.='&nbsp;'.GetUmore($umore); }
 $link="<a href=\"javascript:openProfileId('{$idpg}')\">";
 $name='<a class="chat-character-name '.alphanoChatGender((string)$pg['sesso']).'" href="javascript:openProfileId(\''.$idpg.'\')">'.$name.'</a>';
 if($db->query("SELECT 1 FROM equippg WHERE slot='musica' AND idpg=?",[$idpg])){ $name.='&nbsp;<img src=\"/chatimg/musica.gif\" border=\"0\">'; }
 $razza=(int)$pg['razza'];$sesso=(string)$pg['sesso'];$inf=(int)$pg['infetto'];$ferite=(string)$pg['ferite'];
 require_once __DIR__.'/../../libs/transformations.inc.php';
 $losesLanguage=alphanoLosesHumanLanguage($pg);$l='0';
 $render=function(string $text,bool $action=false)use($losesLanguage,$sesso,&$l):string{
  if(!$losesLanguage){ return alphanoChatText($text,$sesso,$action); }
  $formatted=alphanoChatLupineText($text,$sesso,$action);
  if($formatted['speech']){ $l='1'; }
  return $formatted['html'];
 };
 $razImg=$link.processRazza(alphanoVisibleRace($pg),(int)$pg['etnia']).'</a>&nbsp;';
 $incog=($str[0]??'')==='|'&&$pg['fatepower']&&$SessionIsRunning;
 $alline=$incog?'N':($pg['allineamento'][1]??'N');
 $araldica='';
 if(!$incog){ $araldica=alphanoChatHeraldry($pg,$idpg); }
 $fPar="<SPAN class=\"String{$alline}\"><B>";
 $fAzo="<SPAN class=\"Azione{$alline}\">";
 $dest=0;$newLoc=$location;$OnGame=true;
 $out=alphanoChatCommandOutput($str,$pg,$db,$idpg,$ora,$razImg,$name,$fPar,$fAzo,$araldica,$render,$SessionIsRunning,$NeedsDadi,$incog,$dest,$newLoc,$location,$l,$OnGame);
 if(is_array($out)){ return $out; }
 if(str_starts_with($str,'//')){ $type='ooc'; }
 else{ $type=match($str[0]??''){'+'=>'action','$'=>'whisper','&'=>'animal','#'=>'master',default=>'speech'}; }
 if($out!==''){ $out='<div data-message-type="'.$type.'">'.$out.'</div>'; }
 alphanoChatVampireDamage($db,$idpg,$pg,$chat,$sesso,$ferite,$razza,$meteo,$out,$vampiredamage);
 if($inf&&$OnGame){ $db->execute('UPDATE utenti SET azionilican=azionilican+1 WHERE id=?',[$idpg]); }
 alphanoChatRecordings($db,$chat,$out);
 if($SessionIsRunning){ alphanoChatSessionLogs($db,$sessionName,$chat,$out,$scriviPart,$partecipanti,$idpg); }
 return [$out,$dest,$newLoc,$l];
}
function alphanoChatHeraldry(array $pg,int $idpg):string {
 $araldica='';
 if($pg['organizzazione']&&$pg['caricaorganizzazione']){ $araldica=GetCaricaOrg((int)$pg['organizzazione'],(int)$pg['caricaorganizzazione'],false,"araldiche[{$idpg}]"); }
 elseif($pg['corporazione']&&$pg['caricacorporazione']){ $araldica=GetCaricaCorp((int)$pg['corporazione'],(int)$pg['caricacorporazione'],false,"araldiche[{$idpg}]"); }
 else{ $araldica=processAraldica((int)$pg['araldica'],"araldiche[{$idpg}]"); }
 return $araldica.'&nbsp;';
}
