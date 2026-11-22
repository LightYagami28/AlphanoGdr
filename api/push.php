<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/libs/exceptions.inc.php';
require_once __DIR__.'/../libs/player-runtime.inc.php';
require_once __DIR__.'/../libs/web-push.inc.php';
playerApi(static function (): array {
    $user = alphanoRequireLogin(true);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $ready = alphanoPushReady();
        return ['configured'=>$ready,'public_key'=>$ready ? alphanoPushKeys()['publicKey'] : null,'csrfToken'=>alphanoCsrfToken()];
    }
    playerMutation(); $input = playerInput();
    $action = playerText($input,'action',32,true);
    if ($action === 'subscribe') {
        if (!is_array($input['subscription'] ?? null)) { throw new AlphanoPushException('Dispositivo non valido.',400); }
        alphanoPushSubscribe($user,$input['subscription']);
    } elseif ($action === 'unsubscribe') {
        $hash = hash('sha256',alphanoPushEndpoint($input['endpoint'] ?? null));
        alphanoPushDisable($user,$hash);
        if (($_SESSION['push_endpoint_hash'] ?? '') === $hash) { unset($_SESSION['push_endpoint_hash']); }
    } elseif ($action === 'status') {
        $hash = hash('sha256',alphanoPushEndpoint($input['endpoint'] ?? null));
        $enabled = (bool)connectDB()->query("SHOW TABLES LIKE 'web_push_subscriptions'") && (bool)connectDB()->query('SELECT id FROM web_push_subscriptions WHERE user_id=? AND endpoint_hash=? AND enabled=1',[$user,$hash]);
        // Remember the current device after a new login, without silently re-enabling it.
        if ($enabled) { $_SESSION['push_endpoint_hash'] = $hash; }
        return ['success'=>true,'enabled'=>$enabled];
    } else { throw new AlphanoPushException('Operazione non valida.',400); }
    return ['success'=>true];
});
