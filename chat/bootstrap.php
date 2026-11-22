<?php
declare(strict_types=1);

// 1) Core & sessione
require_once __DIR__ . '/libs/chat-core.php';
require_once __DIR__ . '/../libs/pvt/private-core.inc.php';
require_once __DIR__ . '/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/media-assets.inc.php';
initSession();

// 2) Utente
$idpg = GetIDPG(false);
if (!$idpg) {
    header('Location:/');
    exit;
}
$db      = db();
$utente  = $db->query("SELECT * FROM utenti WHERE id = ?", [$idpg])[0];
$master  = (bool)($utente['fatepower']||$utente['admin']);



// 3) Chiave chat da ?chat=
if (empty($_GET['chat'])) {
    alphanoFail('Seleziona un luogo dalla mappa.', 400);
}
if (!is_string($_GET['chat'])) { alphanoFail('Luogo non valido.',400); }
$chat = $_GET['chat'];

// 4) Carica da mappe
$row = $db->query(
    "SELECT chat, title, position, vampiredamage, sfondotop, sfondo, mappa, scene_image, scene_thumbnail, scene_icon, descrizione, rental_enabled, rental_hourly_cost, companion_enabled, companion_minutes, companion_price, chat AS isChatFlag
       FROM mappe WHERE chat_key = ? AND public_enabled=1 AND (area_id IS NULL OR area_id IN(SELECT id FROM world_areas WHERE enabled=1))",
    [$chat]
);
try{if (!$row && ($private = alphanoPrivateRoom($chat, $idpg))) { $row = [$private]; }}
catch(AlphanoRentalExpired $e){header('Location: /chat/index.php?chat='.rawurlencode($e->rental['host_key']).'&rental_expired=1',true,303);exit;}
if (!$row) { alphanoFail('Questo luogo non è disponibile.', 404); }
$config        = $row[0];
$isChat        = (bool)$config['isChatFlag'];
$privateChat   = isset($config['private_kind']);
$canNarrate    = $master || $privateChat;
$title         = $config['title'];
$posizione     = $config['position'];
$vampiredamage = (bool)$config['vampiredamage'];
// I vecchi record possono indicare asset rinominati: niente immagini rotte nella scheda.
$mapFallback='';
$sceneIcon=isset(ALPHANO_SCENE_ICONS[$config['scene_icon']??''])?$config['scene_icon']:'compass';
$imgs = [
    'scene_image'=>alphanoMediaFile($config['scene_image']??'')? $config['scene_image']:'',
    'scene_thumbnail'=>alphanoMediaFile($config['scene_thumbnail']??'')? $config['scene_thumbnail']:'',
    'sfondotop' => playerMediaUrl($config['sfondotop'] ?? '',$idpg),
    'sfondo' => playerMediaUrl($config['sfondo'] ?? '',$idpg,'/assets/placeholder.svg'),
    'mappa' => playerMediaUrl($config['mappa'] ?? '',$idpg,$mapFallback),
];
$datides = $config['descrizione'] ?? '';

setPosizione($posizione, $idpg, $chat);
if ($isChat) {
    require_once __DIR__.'/../libs/navigation-context.inc.php';
    alphanoNavigationRememberScene($idpg, $chat);
}

// 5) Salvataggio descrizione
if ($isChat
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['txtdescrizione'])
) {
    if (!$utente['admin'] && !$master) { alphanoFail('Privilegi insufficienti.', 403); }
    alphanoRequireCsrf();
    if (!is_string($_POST['txtdescrizione'])) { alphanoFail('Descrizione non valida.',400); }
    $txt = trim($_POST['txtdescrizione']);
    $db->execute(
        "UPDATE mappe SET descrizione = ? WHERE chat_key = ?",
        [$txt, $chat]
    );
    $datides = $txt;
}

// 6) Umori & azioni
$umori        = GetListaUmori();
$azioniLavoro = getAzioniLavoro($chat);
