<?php
declare(strict_types=1);
require_once __DIR__.'/lycan.inc.php';
/** Use the exact midnight phase boundaries consumed by the lunar synchronizer, including DST. */
function alphanoNextLunarBoundary(int $now,bool $full):?int {
 for($day=1;$day<=62;$day++){$at=strtotime('+'.$day.' days',strtotime('today',$now));if((faseLunare($at)==='Luna Piena')===$full){ return $at; }}
 return null;
}
function alphanoMetamorphosis(array $user,?int $now=null):?array {
 $now??=time();$result=null;
 if((int)$user['puntif']>0&&empty($user['bloccato'])){
  $pending=connectDB()->query("SELECT ready_at FROM lineage_members WHERE player_id=? AND kind='vampire' AND status='pending'",[(int)$user['id']])[0]??null;
  if($pending){ $result=['kind'=>'vampire','label'=>'Trasformazione programmata','detail'=>'Trasformazione in vampiro tra','deadline'=>(int)$pending['ready_at'],'server_time'=>$now,'due'=>'La trasformazione è imminente…']; }
  else{
   $state=alphanoLycanState($user,$now);
   if($state['eligible']&&!$state['ancestral']){
    $wolf=$state['fullMoon'];$deadline=alphanoNextLunarBoundary($now,!$wolf);
    if($wolf){ $detail='Ritorno alla forma umana tra'; }else{ $detail='Trasformazione alla Luna Piena tra'; }
    $result=['kind'=>'lycan','label'=>'Trasformazione periodica','detail'=>$detail,'deadline'=>$deadline,'server_time'=>$now,'due'=>'La metamorfosi è imminente…'];
   }
  }
 }
 return $result;
}
