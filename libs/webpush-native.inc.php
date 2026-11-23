<?php
declare(strict_types=1);

/** Web Push (RFC 8030/8291) without third-party libraries: ext-openssl and ext-curl only. */

const ALPHANO_WEBPUSH_RECORD_SIZE = 4096;

function alphanoWebPushBase64UrlEncode(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function alphanoWebPushBase64UrlDecode(string $value): string
{
    if ($value === '' || strlen($value) > 256 || !preg_match('/^[A-Za-z0-9_-]+={0,2}$/D', $value)) {
        throw new InvalidArgumentException('Codifica Base64URL non valida.');
    }
    $padded = strtr(rtrim($value, '='), '-_', '+/');
    $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
    $decoded = base64_decode($padded, true);
    if ($decoded === false) {
        throw new InvalidArgumentException('Codifica Base64URL non valida.');
    }
    return $decoded;
}

function alphanoWebPushHkdf(string $salt, string $inputKey, string $info, int $length): string
{
    $prk = hash_hmac('sha256', $inputKey, $salt, true);
    $output = '';
    $block = '';
    $counter = 1;
    while (strlen($output) < $length) {
        $block = hash_hmac('sha256', $block.$info.chr($counter), $prk, true);
        $output .= $block;
        $counter++;
    }
    return substr($output, 0, $length);
}

function alphanoWebPushPrivateKey(string $bytes): OpenSSLAsymmetricKey
{
    if (strlen($bytes) !== 32) {
        throw new InvalidArgumentException('Chiave privata P-256 non valida.');
    }
    $der = hex2bin('30310201010420').$bytes.hex2bin('a00a06082a8648ce3d030107');
    $pem = "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    $key = openssl_pkey_get_private($pem);
    if ($key === false) {
        throw new RuntimeException('Impossibile caricare la chiave privata P-256.');
    }
    return $key;
}

function alphanoWebPushPublicKey(string $point): OpenSSLAsymmetricKey
{
    if (strlen($point) !== 65 || $point[0] !== "\x04") {
        throw new InvalidArgumentException('Chiave pubblica P-256 non valida.');
    }
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$point;
    $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    $key = openssl_pkey_get_public($pem);
    if ($key === false) {
        throw new InvalidArgumentException('Chiave pubblica P-256 non valida.');
    }
    return $key;
}

/** @return array{body:string,public:string} */
function alphanoWebPushEncrypt(string $payload, string $uaPublic, string $auth, ?string $salt = null, ?string $asPrivate = null): array
{
    if (strlen($auth) !== 16 || strlen($payload) > 3993) {
        throw new InvalidArgumentException('Parametri di cifratura non validi.');
    }
    $salt ??= random_bytes(16);
    if (strlen($salt) !== 16) {
        throw new InvalidArgumentException('Salt non valido.');
    }
    if ($asPrivate === null) {
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $candidate = random_bytes(32);
            try {
                alphanoWebPushPrivateKey($candidate);
                $asPrivate = $candidate;
                break;
            } catch (RuntimeException) {
                continue;
            }
        }
        if ($asPrivate === null) {
            throw new RuntimeException('Impossibile generare la chiave effimera.');
        }
    }
    $asKey = alphanoWebPushPrivateKey($asPrivate);
    $details = openssl_pkey_get_details($asKey);
    if ($details === false) {
        throw new RuntimeException('Impossibile leggere la chiave effimera.');
    }
    $asPublic = "\x04".$details['ec']['x'].$details['ec']['y'];
    $shared = openssl_pkey_derive(alphanoWebPushPublicKey($uaPublic), $asKey);
    if ($shared === false || strlen($shared) !== 32) {
        throw new RuntimeException('Scambio di chiavi ECDH non riuscito.');
    }
    $inputKey = alphanoWebPushHkdf($auth, $shared, "WebPush: info\x00".$uaPublic.$asPublic, 32);
    $cek = alphanoWebPushHkdf($salt, $inputKey, "Content-Encoding: aes128gcm\x00", 16);
    $nonce = alphanoWebPushHkdf($salt, $inputKey, "Content-Encoding: nonce\x00", 12);
    $ciphertext = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ciphertext === false || strlen($tag) !== 16) {
        throw new RuntimeException('Cifratura del messaggio non riuscita.');
    }
    $body = $salt.pack('N', ALPHANO_WEBPUSH_RECORD_SIZE).chr(strlen($asPublic)).$asPublic.$ciphertext.$tag;
    return ['body' => $body, 'public' => $asPublic];
}

function alphanoWebPushRawSignature(string $der): string
{
    $offset = 0;
    if (strlen($der) < 8 || ord($der[$offset++]) !== 0x30) {
        throw new RuntimeException('Firma ECDSA non valida.');
    }
    $sequenceLength = ord($der[$offset++]);
    if ($sequenceLength & 0x80 || $offset + $sequenceLength !== strlen($der)) {
        throw new RuntimeException('Firma ECDSA non valida.');
    }
    $parts = [];
    for ($part = 0; $part < 2; $part++) {
        if (ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Firma ECDSA non valida.');
        }
        $length = ord($der[$offset++]);
        if ($length === 0 || $length & 0x80 || $offset + $length > strlen($der)) {
            throw new RuntimeException('Firma ECDSA non valida.');
        }
        $parts[] = ltrim(substr($der, $offset, $length), "\x00");
        $offset += $length;
    }
    if ($offset !== strlen($der)) {
        throw new RuntimeException('Firma ECDSA non valida.');
    }
    return str_pad($parts[0], 32, "\x00", STR_PAD_LEFT).str_pad($parts[1], 32, "\x00", STR_PAD_LEFT);
}

/** @return array<string,string> */
function alphanoWebPushVapidHeaders(array $keys, string $audience): array
{
    if (!preg_match('~^https://[^/\s]+$~D', $audience)) {
        throw new InvalidArgumentException('Destinatario VAPID non valido.');
    }
    $header = alphanoWebPushBase64UrlEncode((string)json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
    $claim = alphanoWebPushBase64UrlEncode((string)json_encode(['aud' => $audience, 'exp' => time() + 5400, 'sub' => $keys['subject']], JSON_THROW_ON_ERROR));
    $input = $header.'.'.$claim;
    $signature = '';
    if (!openssl_sign($input, $signature, alphanoWebPushPrivateKey(alphanoWebPushBase64UrlDecode($keys['privateKey'])), OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Firma VAPID non riuscita.');
    }
    $token = $input.'.'.alphanoWebPushBase64UrlEncode(alphanoWebPushRawSignature($signature));
    return [
        'Authorization: vapid t='.$token.', k='.$keys['publicKey'],
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: 300',
        'Urgency: normal',
    ];
}

final class AlphanoWebPushResponse
{
    public function __construct(private readonly int $status)
    {
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }
}

final class AlphanoWebPushReport
{
    public function __construct(private readonly ?int $status)
    {
    }

    public function getResponse(): ?AlphanoWebPushResponse
    {
        return $this->status === null ? null : new AlphanoWebPushResponse($this->status);
    }
}

final class AlphanoWebPushTransport
{
    public function __construct(private readonly array $keys, private readonly array $clientOptions = [])
    {
    }

    public function sendOneNotification(array $subscription, string $payload, array $options = []): AlphanoWebPushReport
    {
        $endpoint = alphanoPushEndpoint($subscription['endpoint']);
        $parts = parse_url($endpoint);
        $audience = ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
        $encrypted = alphanoWebPushEncrypt(
            $payload,
            alphanoWebPushBase64UrlDecode($subscription['p256dh']),
            alphanoWebPushBase64UrlDecode($subscription['auth'])
        );
        $headers = alphanoWebPushVapidHeaders($this->keys, $audience);
        if (isset($options['topic']) && preg_match('/^[A-Za-z0-9_-]{1,32}$/D', (string)$options['topic'])) {
            $headers[] = 'Topic: '.$options['topic'];
        }
        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('Impossibile inizializzare cURL.');
        }
        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encrypted['body'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => (bool)($this->clientOptions['verify'] ?? true),
            CURLOPT_SSL_VERIFYHOST => (bool)($this->clientOptions['verify'] ?? true) ? 2 : 0,
            CURLOPT_CONNECTTIMEOUT => (int)($this->clientOptions['connect_timeout'] ?? 3),
            CURLOPT_TIMEOUT => (int)($this->clientOptions['timeout'] ?? 5),
        ]);
        curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($error !== '') {
            throw new RuntimeException('Richiesta push non riuscita.');
        }
        return new AlphanoWebPushReport($status > 0 ? $status : null);
    }
}
