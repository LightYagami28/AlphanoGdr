<?php
declare(strict_types=1);
const ALPHANO_PRIVACY_VERSION='1';
function alphanoExternalMediaAllowed(): bool {
 return preg_match('/^v1\.media\.[a-f0-9]{32}$/D',(string)($_COOKIE['alphano_privacy']??''))===1;
}
function alphanoPrivacyHeaders(): void {
 if(headers_sent()){ return; }
 $sources=alphanoExternalMediaAllowed()?"'self' data: blob: https:":"'self' data: blob:";
 // Enforced by the browser before requests, including resources added by chat JS.
 header("Content-Security-Policy: img-src $sources; media-src $sources; frame-src ".(alphanoExternalMediaAllowed()?"'self' https:":"'self'")."; object-src 'none'; base-uri 'self'");
 header('Referrer-Policy: same-origin');
 header('X-Content-Type-Options: nosniff');
}
