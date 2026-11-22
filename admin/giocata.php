<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/player-runtime.inc.php';
require_once __DIR__ . '/../libs/libxml.inc.php';
require_once __DIR__ . '/../libs/libaccademia.inc.php';
require_once __DIR__ . '/../libs/libposta.inc.php';

$idpg = alphanoRequireLogin();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { alphanoRequireCsrf(); }
$viewer = alphanoUser();
if (empty($viewer['admin']) && empty($viewer['adminaccademia'])) { alphanoFail('Non hai i permessi per accedere.', 403); }
$rawId = $_POST['giocata'] ?? $_GET['giocata'] ?? '';
if (!is_string($rawId) || filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) { alphanoFail('Nessuna giocata selezionata.', 400); }
$id = (int)$rawId;
$row = connectDB()->query('SELECT id,nome,giocata FROM giocate WHERE id=?', [$id])[0] ?? null;
if (!$row) { alphanoFail('Giocata non trovata.', 404); }
$compressed = base64_decode((string)$row['giocata'], true);
$lines = $compressed !== false ? getArrayFromBZXML($compressed, 'giocata') : false;
if (!is_array($lines)) { alphanoFail('Errore nell’apertura della giocata.', 400); }
foreach ($lines as $line) { if (!is_scalar($line) && $line !== null) { alphanoFail('Formato della giocata non valido.', 400); } }
$recording = [
    'id' => $id, 'title' => (string)($row['nome'] ?: 'Giocata #' . $id),
    'html' => alphanoArchiveContent(implode("<br>\n", array_map(static fn(mixed $line): string => (string)$line, $lines))),
    'can_vote' => canVoteGiocata($idpg, $id),
];
require_once __DIR__ . '/../templates/academy/recording.php';
closePage();
