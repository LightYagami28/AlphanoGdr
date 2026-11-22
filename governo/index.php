<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/treasury.inc.php';
$viewer=alphanoRequireLogin();$input=$_GET;$scope=playerText($input,'scope',20)?:'citta';
try{$id=alphanoTreasuryInt($input['id']??0);if(!$id){$s=alphanoTreasuryScope($scope);$u=alphanoUser();$id=$scope==='citta'?(int)(connectDB()->query('SELECT city FROM araldiche WHERE id=?',[(int)$u['araldica']])[0]['city']??0):(int)$u[$s['user_scope']];if(!$id&&!empty($u['admin'])){ $first=match($scope){'citta'=>connectDB()->query('SELECT id FROM city ORDER BY id LIMIT 1'),'organizzazioni'=>connectDB()->query('SELECT id FROM organizzazioni ORDER BY id LIMIT 1'),'corporazioni'=>connectDB()->query('SELECT id FROM corporazioni ORDER BY id LIMIT 1')}; $id=(int)($first[0]['id']??0); }}
    $view=alphanoTreasuryView($scope,$id,$viewer);
}catch(DomainException $e){alphanoFail($e->getMessage(),$e->getCode());}
$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$manage=$view['can_manage'];$s=alphanoTreasuryScope($scope);
if($scope==='citta'){ $dest='/admin/city.php?city='.$id; }elseif($scope==='corporazioni'){ $dest='/corporazioni/admin.php?corp='.$id; }else{ $dest='/organizzazioni/admin.php?org='.$id; }
$anchor=$scope==='corporazioni'?'#iscritti':'#tab-iscritti';
if($scope==='citta'){ $rolesUrl='/admin/cariche.php'; }else{ $rolesUrl=$dest.$anchor; }
if($scope==='citta'){ $memberUrl='/admin/city.php?city='.$id.'#richieste-cittadinanza'; }else{ $memberUrl=$dest.$anchor; }
$requestCount=$manage?count(match($scope){'citta'=>GetRichiesteCity($id),'corporazioni'=>GetRichiesteCorp($id),default=>GetRichiesteOrg($id)}):0;
require_once __DIR__.'/../templates/admin/governance.php';
