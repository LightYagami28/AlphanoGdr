<?php
declare(strict_types=1);
require_once __DIR__.'/kernel.inc.php';

class AlphanoPushException extends RuntimeException {}

const ALPHANO_PUSH_AUTOLOAD = '/vendor/autoload.php';
const ALPHANO_PUSH_INVALID_KEY = 'Chiave del dispositivo non valida.';

/** Web Push is optional. Private keys and capability URLs never leave this boundary. */
function alphanoPushKeys(): ?array
{
    $result = null;
    $path = (string)alphanoConfig('push_vapid_file');
    $real = $path !== '' ? realpath($path) : false;
    $validFile = $real && is_file($real) && is_readable($real) && filesize($real) <= 4096;
    $insideRoot = false;
    if ($validFile) {
        foreach (array_unique([dirname(__DIR__), htmlroot]) as $root) {
            $insideRoot = $insideRoot || str_starts_with($real, rtrim((string)realpath($root), '/').'/');
        }
    }
    if ($validFile && !$insideRoot && (fileperms($real) & 0077) === 0) {
        try {
            $keys = json_decode((string)file_get_contents($real), true, 8, JSON_THROW_ON_ERROR);
            $subjectOk = is_array($keys) && is_string($keys['subject'] ?? null)
                && preg_match('~^(mailto:[^\s@]+@[^\s@]+|https://[^\s]+)$~D', $keys['subject']);
            $publicOk = strlen(alphanoPushDecode($keys['publicKey'] ?? '')) === 65;
            $privateOk = strlen(alphanoPushDecode($keys['privateKey'] ?? '')) === 32;
            if ($subjectOk && $publicOk && $privateOk) {
                $result = array_intersect_key($keys, array_flip(['subject','publicKey','privateKey']));
            }
        } catch (Throwable) { $result = null; }
    }
    return $result;
}

function alphanoPushDecode(mixed $value): string
{
    if (!is_string($value) || strlen($value)>256 || !preg_match('/^[A-Za-z0-9_-]+={0,2}$/D', $value)) { throw new AlphanoPushException(ALPHANO_PUSH_INVALID_KEY, 400); }
    $bytes = base64_decode(strtr($value, '-_', '+/'), true);
    if ($bytes === false) { throw new AlphanoPushException(ALPHANO_PUSH_INVALID_KEY, 400); }
    return $bytes;
}

function alphanoPushReady(): bool
{
    $ready = true;
    foreach (['curl','mbstring','openssl'] as $extension) { $ready = $ready && extension_loaded($extension); }
    $ready = $ready && in_array('prime256v1', openssl_get_curve_names() ?: [], true);
    $ready = $ready && in_array('aes-128-gcm', openssl_get_cipher_methods(), true);
    $ready = $ready && (bool)alphanoPushKeys() && is_file(dirname(__DIR__).ALPHANO_PUSH_AUTOLOAD);
    if ($ready) {
        require_once dirname(__DIR__).ALPHANO_PUSH_AUTOLOAD;
        $ready = class_exists(\Minishlink\WebPush\WebPush::class)
            && (bool)ConnectDB()->query("SHOW TABLES LIKE 'web_push_subscriptions'");
    }
    return $ready;
}

/** Restrict outbound destinations to browser vendors, never arbitrary user URLs (SSRF). */
function alphanoPushEndpoint(mixed $value): string
{
    if (!is_string($value) || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f]/', $value)) { throw new AlphanoPushException('Dispositivo non valido.', 400); }
    $url = parse_url($value); $host = strtolower($url['host'] ?? '');
    $allowed = $host === 'fcm.googleapis.com' || $host === 'fcmregistrations.googleapis.com'
        || str_ends_with($host, '.push.services.mozilla.com')
        || str_ends_with($host, '.notify.windows.com') || str_ends_with($host, '.push.apple.com');
    if (!$allowed || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass'])
        || isset($url['fragment']) || (isset($url['port']) && $url['port'] !== 443) || empty($url['path'])) { throw new AlphanoPushException('Servizio push del browser non riconosciuto.', 400); }
    return $value;
}

function alphanoPushSubscription(array $input): array
{
    $endpoint = alphanoPushEndpoint($input['endpoint'] ?? null);
    if (!is_array($input['keys'] ?? null)) { throw new AlphanoPushException('Chiavi del dispositivo non valide.', 400); }
    $p256dh = $input['keys']['p256dh'] ?? null; $auth = $input['keys']['auth'] ?? null;
    $point = alphanoPushDecode($p256dh);
    if (strlen($point) !== 65 || $point[0] !== "\x04" || strlen(alphanoPushDecode($auth)) !== 16) { throw new AlphanoPushException('Chiavi del dispositivo non valide.', 400); }
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$point;
    if (!@openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n")) { throw new AlphanoPushException(ALPHANO_PUSH_INVALID_KEY, 400); }
    return compact('endpoint','p256dh','auth');
}

function alphanoPushSubscribe(int $user, array $input): void
{
    if (!alphanoPushReady()) { throw new AlphanoPushException('Le notifiche del browser non sono ancora disponibili.', 503); }
    $sub = alphanoPushSubscription($input); $hash = hash('sha256', $sub['endpoint']); $now = time(); $db = ConnectDB();
    $db->beginTransaction();
    try {
        $account = $db->query('SELECT id,bloccato FROM utenti WHERE id=? FOR UPDATE', [$user])[0] ?? null;
        if (!$account || !empty($account['bloccato'])) { throw new AlphanoPushException('Accesso non consentito.', 403); }
        $existing = $db->query('SELECT * FROM web_push_subscriptions WHERE endpoint_hash=? FOR UPDATE', [$hash])[0] ?? null;
        if ((!$existing || (int)$existing['user_id']!==$user || !$existing['enabled']) && (int)$db->query('SELECT COUNT(*) AS n FROM web_push_subscriptions WHERE user_id=? AND enabled=1', [$user])[0]['n'] >= 10) { throw new AlphanoPushException('Hai già dieci dispositivi attivi. Disattivane uno prima di aggiungerne un altro.', 429); }
        $same = $existing && (int)$existing['user_id']===$user && $existing['enabled'] && hash_equals(hash('sha256',alphanoPushKeys()['publicKey']),$existing['vapid_hash']);
        $latest = $same ? (int)$existing['last_mail_id'] : (int)$db->query('SELECT COALESCE(MAX(id),0) AS id FROM mail WHERE iddestinatario=?', [$user])[0]['id'];
        $db->execute('INSERT INTO web_push_subscriptions(user_id,endpoint_hash,endpoint,p256dh,auth,vapid_hash,last_mail_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),endpoint=VALUES(endpoint),p256dh=VALUES(p256dh),auth=VALUES(auth),vapid_hash=VALUES(vapid_hash),enabled=1,last_mail_id=VALUES(last_mail_id),failures=?,available_at=?,updated_at=VALUES(updated_at)', [$user,$hash,$sub['endpoint'],$sub['p256dh'],$sub['auth'],hash('sha256',alphanoPushKeys()['publicKey']),$latest,$now,$now,$same ? (int)$existing['failures'] : 0,$same ? (int)$existing['available_at'] : 0]);
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
    alphanoSession(); $_SESSION['push_endpoint_hash'] = $hash;
}

function alphanoPushDisable(int $user, string $hash): void
{
    if (preg_match('/^[a-f0-9]{64}$/D', $hash) && ConnectDB()->query("SHOW TABLES LIKE 'web_push_subscriptions'")) {
        ConnectDB()->execute('UPDATE web_push_subscriptions SET enabled=0,updated_at=? WHERE user_id=? AND endpoint_hash=?', [time(),$user,$hash]);
    }
}

/** Factory is injectable only from trusted PHP tests, never from HTTP input. */
function alphanoPushTransport(array $keys, array $clientOptions = []): \Minishlink\WebPush\WebPush
{
    require_once dirname(__DIR__).ALPHANO_PUSH_AUTOLOAD;
    $client = new \GuzzleHttp\Client(array_replace(['timeout'=>5,'allow_redirects'=>false,'connect_timeout'=>3,'verify'=>true], $clientOptions));
    return new \Minishlink\WebPush\WebPush(['VAPID'=>$keys], ['TTL'=>300,'urgency'=>'normal'], $client);
}

function alphanoPushValidSubscription(object $db, array $sub, array $keys): bool
{
    $account = $db->query('SELECT bloccato FROM utenti WHERE id=?', [$sub['user_id']])[0] ?? null;
    return (bool)$account && !$account['bloccato'] && hash_equals(hash('sha256',$keys['publicKey']),$sub['vapid_hash']);
}

function alphanoPushSend(array $sub, array $keys, ?callable $sender, ?\Minishlink\WebPush\WebPush $transport, int $latest): int
{
    try {
        alphanoPushEndpoint($sub['endpoint']);
        require_once __DIR__.'/experience.inc.php';
        $audio = alphanoExperienceSettings((int)$sub['user_id']);
        $payload = json_encode(['type'=>'mail','event'=>'mail:'.$latest,'url'=>'/posta/','silent'=>!$audio['notification_sound'] || !$audio['notification_volume']],JSON_THROW_ON_ERROR);
        if ($sender) { return (int)$sender($sub,$payload); }
        $report = $transport->sendOneNotification(\Minishlink\WebPush\Subscription::create(['contentEncoding'=>'aes128gcm','endpoint'=>$sub['endpoint'],'keys'=>['p256dh'=>$sub['p256dh'],'auth'=>$sub['auth']]]), $payload, ['topic'=>'alphano-mail']);
        return $report->getResponse()?->getStatusCode() ?? 0;
    } catch (Throwable) { return 0; } // No exception details: they can contain capability URLs.
}

function alphanoPushRecord(object $db, array $sub, int $status, int $latest, int $now, array &$result): void
{
    if ($status>=200 && $status<300) {
        $db->execute('UPDATE web_push_subscriptions SET last_mail_id=?,failures=0,available_at=?,updated_at=? WHERE id=?', [$latest,$now+15,$now,$sub['id']]); $result['sent']++;
    } elseif (in_array($status,[404,410],true)) {
        $db->execute('UPDATE web_push_subscriptions SET enabled=0,updated_at=? WHERE id=?', [$now,$sub['id']]); $result['expired']++;
    } else {
        $failures = min(12,(int)$sub['failures']+1); $delay = min(3600,15*(2**$failures));
        $db->execute('UPDATE web_push_subscriptions SET failures=?,available_at=?,updated_at=? WHERE id=?', [$failures,$now+$delay,$now,$sub['id']]); $result['retry']++;
    }
}

/** Poll all mail writers, coalescing a burst once per device. No private content in payload. */
function alphanoPushPublish(?callable $sender = null): array
{
    $result = ['sent'=>0,'expired'=>0,'retry'=>0,'skipped'=>0];
    if (!alphanoPushReady()) { return $result; }
    $db = ConnectDB(); $lock = 'alphano_push_'.substr(hash('sha256',(string)alphanoConfig('db_db')),0,32);
    if (!(int)$db->query('SELECT GET_LOCK(?,0) AS acquired', [$lock])[0]['acquired']) { return $result; }
    try {
        $keys = alphanoPushKeys(); $transport = $sender ? null : alphanoPushTransport($keys); $now = time();
        $ids = $db->query('SELECT s.id FROM web_push_subscriptions s JOIN utenti u ON u.id=s.user_id WHERE s.enabled=1 AND s.available_at<=? AND (u.bloccato<>0 OR s.vapid_hash<>? OR EXISTS(SELECT 1 FROM mail m WHERE m.iddestinatario=s.user_id AND m.id>s.last_mail_id AND m.letto=0 AND m.deldest=0 AND m.baule=0)) ORDER BY s.available_at,s.id LIMIT 25', [$now,hash('sha256',$keys['publicKey'])]);
        foreach ($ids as $entry) {
            $db->beginTransaction();
            try {
                // Never lock the player row while waiting on the remote push service.
                $sub = $db->query('SELECT * FROM web_push_subscriptions WHERE id=? FOR UPDATE', [$entry['id']])[0] ?? null;
                if (!$sub || !$sub['enabled'] || (int)$sub['available_at']>$now) { $db->commit(); continue; }
                if (!alphanoPushValidSubscription($db, $sub, $keys)) {
                    $db->execute('UPDATE web_push_subscriptions SET enabled=0 WHERE id=?', [$sub['id']]); $db->commit(); $result['skipped']++; continue;
                }
                $latest = (int)$db->query('SELECT COALESCE(MAX(id),0) AS id FROM mail WHERE iddestinatario=? AND id>? AND letto=0 AND deldest=0 AND baule=0', [$sub['user_id'],$sub['last_mail_id']])[0]['id'];
                if (!$latest) { $db->execute('UPDATE web_push_subscriptions SET available_at=? WHERE id=?', [$now+15,$sub['id']]); $db->commit(); continue; }
                $status = alphanoPushSend($sub, $keys, $sender, $transport, $latest);
                alphanoPushRecord($db, $sub, $status, $latest, $now, $result);
                $db->commit();
            } catch (Throwable $e) { $db->rollBack(); throw $e; }
        }
    } finally { $db->query('SELECT RELEASE_LOCK(?)', [$lock]); }
    return $result;
}
