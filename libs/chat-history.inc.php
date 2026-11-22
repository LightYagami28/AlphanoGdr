<?php
declare(strict_types=1);
require_once __DIR__.'/kernel.inc.php';
/** Historical merge evidence stays durable without being replayed in the live room. */
function alphanoChatHasArchive():bool {
    static $ready=null;return $ready??=(bool)connectDB()->query("SHOW COLUMNS FROM chat LIKE 'history_only'");
}
function alphanoChatLiveFilter():string {return alphanoChatHasArchive()?' AND history_only=0':'';}
