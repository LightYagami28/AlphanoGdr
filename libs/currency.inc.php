<?php
declare(strict_types=1);
/** Display terminology only: persisted money amounts and protocol keys stay unchanged. */
function alphanoCurrencyName(): string
{
    $value=function_exists('alphanoConfig')?alphanoConfig('currency_name'):(require_once dirname(__DIR__).'/config/app.php')['currency_name'];
    return is_string($value)&&trim($value)!==''?$value:'Corone';
}
function alphanoCurrencyText(string $text): string
{
    return preg_replace_callback('/\bcorone\b/iu',static fn()=>alphanoCurrencyName(),$text);
}
/** Replace only the authored label, without escaping surrounding trusted markup. */
function alphanoCurrencyHtml(string $text='Corone'): string
{
    return preg_replace_callback('/\bcorone\b/iu',static fn()=>htmlspecialchars(alphanoCurrencyName(),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),$text);
}
