<?php
declare(strict_types=1);
const PROFILE_ART_INC_ASSETS_PLACEHOLDER_SVG = '/assets/placeholder.svg';
require_once __DIR__.'/transformations.inc.php';
/** One artwork selection for sheets, editor and equipment; an empty override follows race and sex. */
function alphanoProfileDefaultBanner():string {
 return PROFILE_ART_INC_ASSETS_PLACEHOLDER_SVG;
}
/** Shared circular portrait source, including the transformed form and gender fallback. */
function alphanoProfileUsesAltArt(array $player):bool {
 $race=(int)($player['razza']??1);
 // Vampire portraits already represent the native form: the optional secondary portrait is their human disguise.
 return in_array($race,[3,6],true)||(in_array($race,[2,5],true)&&!empty($player['vampire_human_form']));
}
function alphanoProfilePortrait(array $player):array {
 $id=(int)$player['id'];$default='/assets/avatar.svg';
 $custom=(string)(alphanoProfileUsesAltArt($player)&&!empty($player['avatar2'])?$player['avatar2']:($player['avatar']??''));
 if(str_ends_with($custom,PROFILE_ART_INC_ASSETS_PLACEHOLDER_SVG)){ $custom=''; }
 return ['url'=>playerMediaUrl($custom,$id,$default),'default'=>$default];
}
function alphanoProfileBanner(array $player):array {
 $default=alphanoProfileDefaultBanner();
 $custom=(string)($player[alphanoProfileUsesAltArt($player)?'banner2':'banner']??'');
 // Both forms may share the main banner until a separate image is chosen.
 if($custom===''){ $custom=(string)($player['banner']??''); }
 $url=$custom!==''?playerMediaUrl($custom,(int)($player['id']??0),$default):$default;
 return ['url'=>$url,'default'=>$default];
}

/** A lineage survives the human disguise; dead/spirit forms retain their own emblem. */
function alphanoProfileLineageRace(array $player):int {
 $race=alphanoNativeRace($player);
 return $race===8?4:$race;
}
/** Generic, sex-specific emblem, independent of custom portraits and banners. */
function alphanoProfileRaceSeal():string {
 return PROFILE_ART_INC_ASSETS_PLACEHOLDER_SVG;
}
