<?php
declare(strict_types=1);
require_once __DIR__ . '/../kernel.inc.php';
require_once __DIR__ . '/../libpg.inc.php';
alphanoRequireLogin();
$requestedCity = $_GET['nomecity'] ?? '';
if (!is_string($requestedCity)) { alphanoFail('Città non valida.', 400); }
$cityName = trim($requestedCity);
if ($cityName === '') {
    $cityId=filter_var($_GET['city']??$_GET['id']??0,FILTER_VALIDATE_INT);
    $cityName=$cityId?(connectDB()->query('SELECT city FROM city WHERE id=?',[$cityId])[0]['city']??''):basename(dirname($_SERVER['SCRIPT_NAME']??''));
    if($cityName==='citta'){ $cityName=connectDB()->query('SELECT c.city FROM city c JOIN araldiche a ON a.city=c.id JOIN utenti u ON u.araldica=a.id WHERE u.id=?',[alphanoUser()['id']])[0]['city']??(connectDB()->query('SELECT city FROM city ORDER BY id LIMIT 1')[0]['city']??''); }
}
$cityRecord = connectDB()->query('SELECT id,city FROM city WHERE LOWER(city)=LOWER(?) LIMIT 1', [$cityName])[0] ?? null;
if (!$cityRecord) { alphanoFail('Città non trovata.', 404); }
$cityName = (string)$cityRecord['city'];
require __DIR__ . '/../../templates/cities/assembly.php';
closePage();
