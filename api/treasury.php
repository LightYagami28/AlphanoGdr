<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/treasury.inc.php';
playerApi(static function():array{
    $actor=alphanoRequireLogin(true);$input=playerInput();$scope=playerText($input,'scope',20,true);$id=alphanoTreasuryInt($input['id']??null,1);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        playerMutation();$action=playerText($input,'action',30,true);
        match($action){
            'save'=>alphanoTreasurySave($scope,$id,$actor,$input),
            'payroll'=>alphanoTreasuryPayroll($scope,$id,$actor,playerText($input,'request_id',100,true)),
            'donate'=>alphanoTreasuryDonate($scope,$id,$actor,alphanoTreasuryInt($input['amount']??null,1),playerText($input,'request_id',100,true),playerText($input,'reason',300)?:'Donazione'),
            'mandate'=>alphanoTreasuryDonationMandate($scope,$id,$actor,$input),
            default=>throw new DomainException('Operazione non valida.',422)
        };
    }
    return alphanoTreasuryView($scope,$id,$actor)+['csrfToken'=>alphanoCsrfToken()];
});
