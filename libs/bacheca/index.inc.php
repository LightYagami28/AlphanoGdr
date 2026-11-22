<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__.'/runtime.inc.php';
require_once './local.php';
setChatPosizione($posizione,$idpg);
bachecaLegacyRender(location:$location);
