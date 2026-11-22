<?php
declare(strict_types=1);
require_once __DIR__.'/../libs/player-runtime.inc.php';require_once __DIR__.'/../libs/chat-work.inc.php';require_once __DIR__.'/libs/http-chat.php';
playerApi(static function():array{
 $id=alphanoRequireLogin(true);if($_SERVER['REQUEST_METHOD']!=='GET'){ throw new DomainException('Usa il compositore della chat per confermare il lavoro.',405); }
 $chat=rpg_text($_GET['chat']??'',80,true);$room=httpChatRoom($chat,$id);if(isset($room['private_kind'])){ throw new DomainException('Le camere private non prevedono raccolta.',422); }
 return alphanoWorkState($id,$chat);
});
