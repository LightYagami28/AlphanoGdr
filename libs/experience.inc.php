<?php
declare(strict_types=1);
require_once __DIR__.'/email-theme.inc.php';
/** Per-player presentation settings. Gameplay, permissions and weather remain authoritative. */
function alphanoExperienceDefaults():array {
 return ['map_effects'=>true,'map_lighting'=>true,'ambient_sound'=>false,'notification_sound'=>true,'ambient_volume'=>80,'notification_volume'=>80,'sound_theme'=>'castle','relationship_effects'=>'private','chess_skin'=>'classic']+array_fill_keys(array_map(static fn($k)=>'email_'.$k,array_keys(alphanoEmailCategories())),false);
}
function alphanoExperienceCast($default,$value) {
 if(is_bool($default)){ return (bool)$value; }
 if(is_int($default)){ return (int)$value; }
 return (string)$value;
}
function alphanoExperienceSettings(int $user):array {
 $row=connectDB()->query('SELECT * FROM user_experience_settings WHERE user_id=?',[$user])[0]??[];
 $settings=alphanoExperienceDefaults();$email=json_decode($row['email_preferences']??'{}',true)??[];foreach($email as $key=>$value){ if(array_key_exists($key,$settings)&&str_starts_with($key,'email_')){ $row[$key]=$value; } }foreach($settings as $key=>$default){ if(array_key_exists($key,$row)){ $settings[$key]=alphanoExperienceCast($default,$row[$key]); } }return $settings;
}
function alphanoExperienceValidateValue(string $key,$value):void {
 if(str_starts_with($key,'email_')||in_array($key,['map_effects','map_lighting','ambient_sound','notification_sound'],true)){
  if(!is_bool($value)){ throw new DomainException('Valore della preferenza non valido.',400); }
 }elseif(in_array($key,['ambient_volume','notification_volume'],true)){
  if(!is_int($value)||$value<0||$value>100){ throw new DomainException('Il volume deve essere compreso tra 0 e 100.',400); }
 }elseif($key==='chess_skin'){
  if(!is_string($value)||!in_array($value,['classic'],true)){ throw new DomainException('Skin degli scacchi non valida.',400); }
 }elseif($key==='relationship_effects'){
  if(!is_string($value)||!in_array($value,['private','public','off'],true)){ throw new DomainException('Visibilità delle emozioni non valida.',400); }
 }elseif(!is_string($value)||!in_array($value,['castle','bell','chime','soft'],true)){ throw new DomainException('Suono non valido.',400); }
}
function alphanoExperienceValidate(array $input,array $current):array {
 $allowed=array_keys(alphanoExperienceDefaults());
 foreach($input as $key=>$value){
  if(!in_array($key,$allowed,true)){ throw new DomainException('Preferenza sconosciuta.',400); }
  alphanoExperienceValidateValue((string)$key,$value);
  $current[$key]=$value;
 }return $current;
}
function alphanoExperienceConfirmed(int $user):bool {
 $row=connectDB()->query('SELECT * FROM user_experience_settings WHERE user_id=?',[$user])[0]??[];return (int)($row['confirmed_at']??0)>0;
}
function alphanoExperienceSave(int $user,array $patch,bool $confirm=false):array {
 $db=connectDB();$own=!$db->inTransaction();if($own){ $db->beginTransaction(); }
 try{
  $db->query('SELECT id FROM utenti WHERE id=? FOR UPDATE',[$user]);
  $previous=alphanoExperienceSettings($user);$settings=alphanoExperienceValidate($patch,$previous);
  $db->execute('INSERT INTO user_experience_settings(user_id,map_effects,map_lighting,ambient_sound,notification_sound,ambient_volume,notification_volume,sound_theme,relationship_effects,chess_skin,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE map_effects=VALUES(map_effects),map_lighting=VALUES(map_lighting),ambient_sound=VALUES(ambient_sound),notification_sound=VALUES(notification_sound),ambient_volume=VALUES(ambient_volume),notification_volume=VALUES(notification_volume),sound_theme=VALUES(sound_theme),relationship_effects=VALUES(relationship_effects),chess_skin=VALUES(chess_skin),updated_at=VALUES(updated_at)',[$user,(int)$settings['map_effects'],(int)$settings['map_lighting'],(int)$settings['ambient_sound'],(int)$settings['notification_sound'],$settings['ambient_volume'],$settings['notification_volume'],$settings['sound_theme'],$settings['relationship_effects'],$settings['chess_skin'],time()]);
  $emails=array_filter($settings,static fn($k)=>str_starts_with($k,'email_'),ARRAY_FILTER_USE_KEY);
  $oldRow=$db->query('SELECT email_preferences,email_enabled_at FROM user_experience_settings WHERE user_id=?',[$user])[0]??[];
  $oldJson=json_decode($oldRow['email_preferences']??'{}',true)??[];$since=$oldJson['_since']??[];
  foreach($emails as $key=>$enabled){if($enabled&&!$previous[$key]){ $since[$key]=time(); }elseif($enabled&&!isset($since[$key])){ $since[$key]=(int)($oldRow['email_enabled_at']??time()); }}
  $db->execute('UPDATE user_experience_settings SET email_preferences=?,email_enabled_at=CASE WHEN email_enabled_at=0 AND ? THEN ? ELSE email_enabled_at END WHERE user_id=?',[json_encode($emails+['_since'=>$since]),in_array(true,$emails,true)?1:0,time(),$user]);
  if($confirm){ $db->execute('UPDATE user_experience_settings SET confirmed_at=? WHERE user_id=?',[time(),$user]); }
  if($own){ $db->commit(); }return $settings;
 }catch(Throwable $e){if($own&&$db->inTransaction()){ $db->rollBack(); }throw $e;}
}
function alphanoMapEnvironment(?array $weather=null,?int $now=null,?array $sun=null):array {
 require_once __DIR__.'/astro.inc.php';require_once __DIR__.'/libmeteo.inc.php';
 $weather??=getMeteo();$now??=time();[$dawn,$dusk]=$sun??sorgeCala();
 $night=$now<$dawn||$now>=$dusk;$twilight=min(abs($now-$dawn),abs($now-$dusk))<1800;
 if(abs((int)$weather['tempo'])>=2){ $precipitation=(int)$weather['temperatura']<=0?'snow':'rain'; }else{ $precipitation='none'; }
 if($twilight){ $period='twilight'; }elseif($night){ $period='night'; }else{ $period='day'; }
 return ['server_time'=>$now,'period'=>$period,'night'=>$night,'dawn'=>$dawn,'dusk'=>$dusk,'precipitation'=>$precipitation,'wind'=>abs((int)$weather['vento']),'fog'=>(int)$weather['vento']===0,'storm'=>abs((int)$weather['cielo'])>=2,'temperature'=>(int)$weather['temperatura']];
}
