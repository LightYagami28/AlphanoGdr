<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/kernel.inc.php';
require_once __DIR__.'/../libs/libpg.inc.php';
require_once __DIR__.'/../libs/libtext.inc.php';
require_once __DIR__.'/../libs/libxml.inc.php';
$idpg=alphanoRequireLogin();
$db=getDB(); $messages=null; $file=''; $error=''; $sent=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    alphanoRequireCsrf();
    if (isset($_POST['xmldata'])) {
        $name=trim((string)($_POST['nome'] ?? ''));
        $file=base64_decode((string)$_POST['xmldata'],true);
        if ($name==='' || strlen($name)>255 || $file===false || strlen($file)>MAX_UPLOAD_FILE_SIZE) { alphanoFail('Nome o file giocata non valido.',400); }
        $messages=getArrayFromBZXML($file,'giocata');
        if (!is_array($messages)) { alphanoFail('File giocata non valido. I vecchi archivi compressi richiedono BZip2; le nuove registrazioni XML funzionano su tutti gli hosting supportati.',400); }
        $db->execute('INSERT INTO giocate(idpg,data,giocata,nome) VALUES(?,?,?,?)',[$idpg,time(),base64_encode($file),$name]);
        $sent=true;
    } else {
        $upload=$_FILES['giocata'] ?? [];
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($upload['size'] ?? 0)>MAX_UPLOAD_FILE_SIZE || !is_uploaded_file($upload['tmp_name'] ?? '')) { $error='Seleziona un file giocata valido.'; }
        else {
            $file=file_get_contents($upload['tmp_name']);
            $messages=getArrayFromBZXML($file,'giocata');
            if (!is_array($messages)) { $error='File giocata non valido.'; }
        }
    }
}
$escape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Visualizzatore Giocate – <?=$escape(TITOLO_SITO)?></title>
<body><h1>Visualizzatore Giocate</h1><p>Le registrazioni attuali usano il formato XML portabile .alphano.<?php if(!function_exists('bzopen')): ?> Questo hosting non supporta i vecchi archivi BZip2: convertirli prima su un ambiente con l’estensione disponibile.<?php endif; ?></p>
<?php if ($sent): ?><p>Giocata inviata all’accademia.</p>
<?php elseif (is_array($messages)): ?>
<div id="chatbox"><?php foreach ($messages as $message): ?><?=alphanoSanitizeHtml(is_scalar($message) ? (string)$message : '')?><br><?php endforeach; ?></div>
<form method="post"><label>Titolo per l’accademia <input name="nome" maxlength="255" required></label><input type="hidden" name="xmldata" value="<?=$escape(base64_encode($file))?>"><button>Invia la giocata all’accademia</button></form>
<?php else: ?><?php if ($error): ?><p role="alert"><?=$escape($error)?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data"><label>File giocata <input type="file" name="giocata" required></label><button>Visualizza</button></form>
<?php endif; ?></body></html>
