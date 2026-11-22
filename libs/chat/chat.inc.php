<?php
declare(strict_types=1);
require_once __DIR__ . '/legacy.inc.php';
$_GET['chat'] = alphanoLegacyChatKey($chat ?? null);
require_once dirname(__DIR__, 2) . '/chat/index.php';
