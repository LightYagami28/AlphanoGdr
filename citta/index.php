<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/city-directory.inc.php';
alphanoRequireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    alphanoFail('Metodo non consentito.', 405);
}
$rawId = $_GET['id'] ?? '0';
$section = $_GET['sezione'] ?? 'araldiche';
if (!is_scalar($rawId) || !is_string($section)
    || filter_var((string)$rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false
    || !in_array($section, ['statuto', 'araldiche', 'cittadini'], true)) {
    alphanoFail('Città o sezione non valida.', 400);
}
$cityDirectory = alphanoCityDirectory(alphanoUser(), (int)$rawId);
if ((int)$rawId > 0 && $cityDirectory['selected'] === null) { alphanoFail('Città non trovata.', 404); }
require_once __DIR__ . '/../templates/cities/index.php';
closePage();
