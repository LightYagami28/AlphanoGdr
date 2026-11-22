<?php
declare(strict_types=1);
/** Shared, versioned illustrations: no account writes and no per-icon SQL queries. */
function alphanoProfileIllustrations():array {
 static $index=null;
 if($index!==null){ return $index; }
 $catalog=json_decode(file_get_contents(__DIR__.'/../config/profile-symbols.json'),true,64,JSON_THROW_ON_ERROR);$index=['symbols'=>[],'ethnicities'=>[]];
 foreach($catalog['assets'] as $asset){
  if($asset['group']==='profile'){ $index['symbols'][substr($asset['key'],8)]=$asset['path']; }
  elseif($asset['group']==='ethnicity'){ $index['ethnicities'][mb_strtolower(trim($asset['label']))]=$asset['path']; }
 }
 return $index;
}
function alphanoProfileSymbol(string $key):string {
 $catalog=alphanoProfileIllustrations();return $catalog['symbols'][$key]??($catalog['symbols']['identity']??'/assets/placeholder.svg');
}
function alphanoEthnicityArt(string $name):string {
 $catalog=alphanoProfileIllustrations();return $catalog['ethnicities'][mb_strtolower(trim($name))]??($catalog['ethnicities']['etnia ignota']??'/assets/placeholder.svg');
}
function alphanoProfileTabSymbol(string $template):string {
 return alphanoProfileSymbol(match($template){'board-games'=>'knowledge','progression'=>'abilities','identity'=>'identity','history'=>'story','victims'=>'victims','revelations'=>'relationships','equipment'=>'equipment','lineage'=>'lineage','npc-knowledge'=>'knowledge',default=>'identity'});
}

function alphanoProfileChapterSymbol(string $label):string {
 return alphanoProfileSymbol(match($label){'Storia'=>'story','Personalità'=>'personality','Stato sociale'=>'social-standing','Amicizie'=>'friendship','Affetti'=>'affection','Animali'=>'animals','Note pubbliche'=>'public-notes','Note Master','Note del Master'=>'master-notes','Condizione'=>'health','Informazioni'=>'knowledge','Contatti offgame'=>'relationships',default=>'public-notes'});
}
