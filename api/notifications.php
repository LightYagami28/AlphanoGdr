<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/notifications.inc.php';
require_once __DIR__.'/../libs/commerce-thread.inc.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
$user=alphanoRequireLogin(true);
$method=$_SERVER['REQUEST_METHOD']??'GET';
try{
    if($method==='POST'){
        alphanoRequireCsrf(true);
        try{$input=alphanoRequestJson();}catch(Throwable){alphanoFail('Richiesta non valida',400,true);}
        if(!array_key_exists('ids',$input) && !array_key_exists('keys',$input) && !array_key_exists('target',$input))alphanoFail('Elenco notifiche mancante',400,true);
        $ids=$input['ids']??[];$keys=$input['keys']??[];
        if(!is_array($ids)||!array_is_list($ids)||count($ids)>500)alphanoFail('Elenco notifiche non valido',400,true);
        if(!is_array($keys)||!array_is_list($keys)||count($keys)>500)alphanoFail('Elenco attività non valido',400,true);
        foreach($ids as $id)if(!is_int($id)||$id<1)alphanoFail('Notifica non valida',400,true);
        foreach($keys as $key)if(!is_string($key)||strlen($key)>190||!preg_match('/^[a-zA-Z0-9:_-]+$/D',$key))alphanoFail('Attività non valida',400,true);
        if(array_key_exists('target',$input)){
            if(!is_array($input['target'])||array_is_list($input['target']))alphanoFail('Discussione non valida',400,true);
            alphanoReadNotificationThread($user,$input['target']);
        }
        alphanoReadNotifications($user,array_values(array_unique($ids)));
        alphanoReadActivityNotifications($user,array_values(array_unique($keys)));
    }elseif($method!=='GET'){
        header('Allow: GET, POST');alphanoFail('Metodo non consentito',405,true);
    }
    $allActivity=null;$result=alphanoUnreadNotifications($user,$allActivity);
    if($method==='GET'&&isset($_GET['box'])){
        if($_GET['box']!=='1')alphanoFail('Registro notifiche non valido',400,true);
        $view=$_GET['view']??'unread';$group=$_GET['group']??'';$section=$_GET['section']??'';$page=$_GET['page']??'1';
        if(!is_string($view)||!in_array($view,['unread','history'],true)||!is_string($group)||!in_array($group,['','gioco','organizzazioni','corporazioni','citta','accademia','bacheche','master','gestione'],true)
            ||!is_string($section)||($section!==''&&!preg_match('/^[a-z0-9_-]{1,64}$/D',$section))||!is_string($page)||!preg_match('/^[1-9]\d{0,5}$/D',$page))alphanoFail('Filtro notifiche non valido',400,true);
        $result['inbox']=alphanoNotificationInbox($user,$view,$group?:null,$section?:null,(int)$page,$allActivity);
    }
    $result['activity']=alphanoTradeNoticeSummaries($result['activity'],$user);
    if(isset($result['inbox']))$result['inbox']['items']=alphanoTradeNoticeSummaries($result['inbox']['items'],$user);
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
}catch(Throwable $error){
    $status=(int)$error->getCode();
    if($status<400||$status>499){error_log('[notifications] '.$error);$status=500;}
    alphanoFail($status===500?'Avvisi non disponibili. Riprova più tardi.':$error->getMessage(),$status,true);
}
