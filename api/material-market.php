<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/corporation-commerce.inc.php';
playerApi(static function():array{
 $id=alphano_require_login(true);$u=alphano_user();
 if(in_array((int)$u['razza'],[ALPHANO_RACE_SPIRIT,ALPHANO_RACE_DEAD_VAMPIRE,ALPHANO_RACE_LEGACY_SPIRIT],true)){ throw new DomainException('Il personaggio non può commerciare nella sua condizione attuale.',403); }
 if($_SERVER['REQUEST_METHOD']==='GET'){ return alphano_material_market($id); }
 playerMutation();$in=playerInput();$v=[];
 foreach(['instance','corp','buyer','quantity','total'] as $k){$v[$k]=commercioIntero($in[$k]??null,1,$k==='quantity'?10000:2147483647);if($v[$k]===null){ throw new DomainException(alphano_currency_text('Inserisci quantità e Corone intere valide.'),422); }}
 return ['success'=>alphano_offer_material($id,$v['instance'],$v['corp'],$v['buyer'],$v['quantity'],$v['total'],playerText($in,'request_key',80,true))];
});
