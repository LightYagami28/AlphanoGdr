<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/welcome.inc.php';
$actor=alphanoRequireLogin();
if (!alphanoCan('admin')) { alphanoFail('Accesso riservato agli amministratori.',403); }
$error=''; $notice='';
$message=alphanoWelcomeMessage(); $form=$message;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    alphanoRequireCsrf();
    try {
        $message=alphanoWelcomeSave($_POST,$actor); $form=$message;
        $notice=$message['enabled']?'Benvenuto salvato. La nuova versione sarà mostrata a tutti gli utenti.':'Benvenuto disattivato.';
    } catch (Throwable $exception) {
        http_response_code($exception instanceof DomainException ? (int)$exception->getCode() : 500);
        $error=$exception instanceof DomainException ? $exception->getMessage() : 'Impossibile salvare il messaggio.';
        if (!($exception instanceof DomainException)) { error_log('[Benvenuto] '.$exception); }
        foreach (['title','body','revision'] as $key) { if (isset($_POST[$key]) && is_string($_POST[$key])) { $form[$key]=$_POST[$key]; } }
        $form['enabled']=($_POST['enabled']??'0')==='1';
    }
}
require __DIR__.'/../templates/admin/welcome.php';
