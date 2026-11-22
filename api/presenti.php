<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/presence.inc.php';
playerApi(static function(): array {
    alphanoRequireLogin(true);
    alphanoPresenceTouch((int)alphanoUser()['id']);
    return alphanoPresence();
});
