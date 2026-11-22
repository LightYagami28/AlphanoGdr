<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/session-participants.inc.php';
playerApi(static function():array{
    $actor=alphano_require_login(true);$method=$_SERVER['REQUEST_METHOD']??'GET';
    if(!in_array($method,['GET','POST'],true)){ throw new DomainException('Metodo non consentito.',405); }
    $input=$method==='POST'?playerInput():$_GET;$chat=playerText($input,'chat',100,true);
    if($method==='POST'){playerMutation();return alphanoParticipantsMutate($actor,$chat,$input);}
    return match(playerText($input,'action',20)?:'state'){
        'state'=>alphanoParticipantsState($actor,$chat),
        'catalog'=>alphanoParticipantsCatalog($actor,$chat,$input),
        'inventory'=>alphanoParticipantsInventory($actor,$chat,alphanoParticipantsInt($input,'target',1,PHP_INT_MAX)),
        default=>throw new DomainException('Sezione non disponibile.',422),
    };
});
