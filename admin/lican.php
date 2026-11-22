<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/lycan.inc.php';
$id=alphanoRequireLogin();$state=alphanoLycanSync($id);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    alphanoRequireCsrf();
    try {
        if ($state['canChoose'] && $state['form']==='wolf') { $form='human'; } else { $form='wolf'; }
        alphanoLycanSync($id,$form);
        header('Location: /admin/lican.php',true,303);exit;
    } catch (DomainException $e) {alphanoFail($e->getMessage(),$e->getCode());}
}
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>La metamorfosi</title></head><body>
<header class="ui-page-heading" data-page-heading><span data-icon="moon" aria-hidden="true"></span><div><h1>La metamorfosi</h1><p>Il richiamo della stirpe.</p></div></header>
<section class="ui-surface"><h2><?= $state['form']==='wolf'?'Forma lupina':'Forma umana' ?></h2><p>Fase corrente: <strong><?=htmlspecialchars($state['phase'],ENT_QUOTES)?></strong></p>
<?php if(!$state['eligible']): ?><p>Questa metamorfosi è riservata ai licantropi viventi.</p>
<?php else: ?><p><?=htmlspecialchars($state['description'],ENT_QUOTES)?></p>
<?php if($state['canChoose']): ?><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(alphanoCsrfToken(),ENT_QUOTES)?>"><input type="hidden" name="azione" value="trasforma"><button type="submit" data-icon="moon"><?=htmlspecialchars($state['label'],ENT_QUOTES)?></button></form>
<?php elseif($state['fullMoon']): ?><p>La trasformazione automatica è attiva. Non puoi interromperla prima della fine della Luna Piena.</p>
<?php else: ?><p>La forma lupina tornerà automaticamente nei giorni di Luna Piena.</p><?php endif; ?>
<?php endif; ?></section></body></html>
