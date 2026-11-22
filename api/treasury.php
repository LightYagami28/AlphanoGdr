<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/treasury.inc.php';
playerApi(static function():array{
    $actor=alphano_require_login(true);$input=playerInput();$scope=playerText($input,'scope',20,true);$id=alphano_treasury_int($input['id']??null,1);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        playerMutation();$action=playerText($input,'action',30,true);
        match($action){
            'save'=>alphano_treasury_save($scope,$id,$actor,$input),
            'payroll'=>alphano_treasury_payroll($scope,$id,$actor,playerText($input,'request_id',100,true)),
            'donate'=>alphano_treasury_donate($scope,$id,$actor,alphano_treasury_int($input['amount']??null,1),playerText($input,'request_id',100,true),playerText($input,'reason',300)?:'Donazione'),
            'mandate'=>alphano_treasury_donation_mandate($scope,$id,$actor,$input),
            default=>throw new DomainException('Operazione non valida.',422)
        };
    }
    return alphano_treasury_view($scope,$id,$actor)+['csrfToken'=>alphano_csrf_token()];
});
