<?php
declare(strict_types=1);
require_once __DIR__ . '/libs/http-chat.php';
try {
    $idpg = alphanoRequireLogin(true);
    $chat = $_GET['chat'] ?? '';
    if (!is_string($chat)) { alphanoFail('Chat non valida.',400,true); }
    httpChatRoom($chat, $idpg);
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        echo json_encode(['recording' => (bool)$db->query('SELECT id FROM registrazioni WHERE pg=? AND chat=?', [$idpg,$chat])]);
        exit;
    }
    if ($method !== 'POST') { alphanoFail('Metodo non consentito.',405,true); }
    alphanoRequireCsrf(true);
    $input = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ? alphanoRequestJson() : $_POST;
    $action = $input['action'] ?? $input['azione'] ?? '';
    $db->beginTransaction();
    $db->query('SELECT id FROM utenti WHERE id=? FOR UPDATE',[$idpg]);
    $record = $db->query('SELECT id FROM registrazioni WHERE pg=? AND chat=? ORDER BY id LIMIT 1',[$idpg,$chat])[0]??null;
    if (in_array($action,['start','registra'],true)) {
        if (!$record) { $db->execute('INSERT INTO registrazioni(pg,chat) VALUES(?,?)',[$idpg,$chat]); }
        $db->commit();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['recording'=>true]);
    } elseif (in_array($action,['save','salva'],true)) {
        if (!$record) { $db->rollBack(); alphanoFail('Nessuna registrazione attiva.',404,true); }
        $messages = array_column($db->query('SELECT testo FROM registrazionichat WHERE registrazione=? ORDER BY id',[$record['id']]),'testo');
        $db->execute('DELETE FROM registrazionichat WHERE registrazione=?',[$record['id']]);
        $db->execute('DELETE FROM registrazioni WHERE id=? AND pg=?',[$record['id'],$idpg]);
        $db->commit();
        require_once __DIR__.'/../libs/libxml.inc.php';
        printBZXMLFromArray($messages,'giocata');
    } else { $db->rollBack(); alphanoFail('Azione non valida.',400,true); }
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    if ($e instanceof DomainException) { alphanoFail($e->getMessage(),$e->getCode()?:400,true); }
    error_log('[registrazioni] '.$e);
    alphanoFail('Operazione non riuscita.',500,true);
}
