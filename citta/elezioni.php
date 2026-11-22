<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/elections.inc.php';
require_once __DIR__.'/../libs/profile-art.inc.php';
$viewer=alphanoRequireLogin();$raw=$_GET['city']??0;if(!is_scalar($raw)||filter_var($raw,FILTER_VALIDATE_INT)===false||(int)$raw<1){ alphanoFail('Città non valida.',400); }
try{$election=alphanoElectionsState((int)$raw,$viewer);}catch(DomainException $e){alphanoFail($e->getMessage(),(int)$e->getCode());}
require_once __DIR__.'/../templates/cities/elections.php';closePage();
