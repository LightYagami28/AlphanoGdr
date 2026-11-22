<?php
declare(strict_types=1);

// variabile globale per calcolare il tempo di esecuzione degli script
if (!defined('SCRIPT_START_TIMESTAMP')) { define('SCRIPT_START_TIMESTAMP', microtime(true)); }

// Configurazione e servizi comuni
require_once __DIR__ . '/app.inc.php';

// valori massimi ammessi
define('MAXMISSIVE', 50);
define('MAXPUNTIF', 1000);

/** Restituisce il percorso canonico solo se risolve dentro $base, altrimenti null. */
function alphanoCanonicalInside(string $base, string $path): ?string
{
    $root = realpath($base);
    if ($root === false) {
        return null;
    }
    $real = realpath($path);
    if ($real === false) {
        return null;
    }
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($real, $prefix) ? $real : null;
}

/** Normalizza un elenco di identificativi a interi positivi, scartando valori non validi. */
function alphanoSqlIds(mixed $values): array
{
    if (!is_array($values)) {
        return [];
    }
    $ids = [];
    foreach ($values as $value) {
        if (!is_int($value) && !(is_string($value) && preg_match('/^[1-9]\d*$/D', $value) === 1)) {
            continue;
        }
        $id = (int)$value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

/** Genera i segnaposto `?` per una lista di identificativi gia' validata. */
function alphanoSqlPlaceholders(int $count): string
{
    return $count > 0 ? implode(',', array_fill(0, $count, '?')) : '';
}
define('NUMERORIGHE', 20);
define('TEMPOMAX', 900);
define('MINTIMELICAN', 7200);
define('MINACTIONSLICAN', 20);
define('MAXOBJINACCADEMIA', 5);
define('MAXPOINTVALUE', 8);

// dimensione massima dei file da caricare
$size1 = returnBytes(ini_get('upload_max_filesize'));
$size2 = returnBytes(ini_get('post_max_size'));
define('MAX_UPLOAD_FILE_SIZE', min($size1, $size2));
unset($size1, $size2);

// titolo e percorsi
define('TITOLO_SITO', (string)alphanoConfig('game_name'));
define('HTMLROOT', alphanoConfig('storage_root') ?: dirname(__DIR__));
define('SITE_DOMAIN', alphanoConfig('base_url'));
define('ADMIN_PASSWD_LEGACY', '');
define('ADMIN_PASSWD', '');

/**
 * Connessione al database con PDO (PHP 8+).
 */
function connectDB(): \Alphano\Database\PDOMySQLDriver
{
    static $connection = null;
    if ($connection === null) {
        require_once __DIR__ . '/mysql.php';
        $connection = new \Alphano\Database\PDOMySQLDriver(
            host: alphanoConfig('db_host'), db: alphanoConfig('db_db'),
            user: alphanoConfig('db_user'), pass: alphanoConfig('db_pass'), port: alphanoConfig('db_port'), socket: alphanoConfig('db_socket')
        );
    }
    return $connection;
}

/**
 * Restituisce i byte corrispondenti a "2M", "512K"...
 */
function returnBytes(string $val): int
{
    $val  = trim($val);
    $last = strtolower(substr($val, -1));
    $num  = (int)$val;
    return match ($last) {
        'g' => $num * 1024 ** 3,
        'm' => $num * 1024 ** 2,
        'k' => $num * 1024,
        default => $num,
    };
}

/**
 * Stampa in pagina il tempo di esecuzione (solo debug).
 */
function closePage(): void
{
    if (str_contains(strtolower((string)($GLOBALS['alphano_response_type'] ?? '')), 'json')) { return; }
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type: application/json') === 0) { return; }
    }
    if (alphanoConfig('debug')) { echo '<!-- Script eseguito in ' . (microtime(true) - SCRIPT_START_TIMESTAMP) . ' secondi -->'; }
}

// fallback per mimeContentType
if (!function_exists('mimeContentType')) {
    function mimeContentType(string $f): string
    {
        return (new finfo(FILEINFO_MIME_TYPE))->file($f) ?: 'application/octet-stream';
    }
}

/**
 * Restituisce l’attributo type per gli <input> file, se significativo.
 */
function getMIME(string $file): string
{
    $no = ['text/plain','application/octet-stream','httpd/unix-directory'];
    if (file_exists(HTMLROOT . $file)) {
        $file = HTMLROOT . $file;
    }
    $mime = @mimeContentType($file) ?: '';
    return (strlen($mime) && !in_array($mime, $no, true))
        ? " type=\"{$mime}\""
        : '';
}

/**
 * Genera una password casuale usando il generatore crittografico.
 */
function generatePassword(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Timestamp di N giorni fa alle 00:00.
 */
function generatePastTimestamp(int $daysago): int
{
    return mktime(0,0,0,(int)date('n'),(int)date('j')-$daysago,(int)date('Y'));
}

/**
 * Formatta un timestamp in “gg-mm-YYYY hh:ii:ss”.
 */
function formatTimestamp(int $time, string $alt = ''): string
{
    if ($time <= 0 && $alt !== '') {
        return $alt;
    }
    $day = date('d-m-Y',$time);
    $hour = date('H:i:s',$time);
    return ($hour === '00:00:00') ? $day : "{$day} {$hour}";
}

/**
 * Converte "\n" in "<BR>".
 */
function gnl2br(string $str): string
{
    return str_replace("\n", '<BR>', str_replace("\r", '', $str));
}

/**
 * Converte "\n" in literal "\n".
 */
function gnl2n(string $str): string
{
    return str_replace("\n", '\n', str_replace("\r", '', $str));
}

/**
 * Diff testuale tra due stringhe, restituisce array strutturato.
 */
function alphanoDiff(string $text1, string $text2): array
{
    if($text1===$text2){ return []; }
    $encode=static fn(string $text)=>array_map(static fn($line)=>implode(';',array_values(unpack('C*',$line)?:[])),explode("\n",$text));
    return [[['da'=>1,'a'=>count(explode("\n",$text1)),'text'=>$encode($text1)],['da'=>1,'a'=>count(explode("\n",$text2)),'text'=>$encode($text2)]]];
}

// La cornice gestisce gli ingressi diretti; i controller nei frame eseguono una sola volta.
alphanoRouteDocument();
