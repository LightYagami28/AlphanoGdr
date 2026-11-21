<?php
declare(strict_types=1);

/**
 * Controlli sul sorgente frontend: dichiarazioni esplicite e valori CSS validi.
 *
 * Usati dai test di regressione per evitare la reintroduzione di globali
 * impliciti e di valori geometrici senza unita'.
 */
function alphano_js_explicit_declarations(string $source): bool
{
    // Le dichiarazioni esplicite vengono rimosse prima di cercare le assegnazioni implicite.
    $declared = (string)preg_replace(
        '/\b(?:var|let|const)\s+(?:riga|tmp|sel|c|i)\b/',
        '',
        $source
    );
    $implicit = [
        '/\briga\s*=\s*righe\.pop\(\)/',
        '/\btmp\s*=\s*obj\.scrollTop/',
        '/\bsel\s*=\s*document\.selection\.createRange\(\)/',
        '/\bfor\s*\(\s*(?:c|i)\s*(?:in|=)/',
    ];
    foreach ($implicit as $pattern) {
        if (preg_match($pattern, $declared) === 1) {
            return false;
        }
    }
    return true;
}

/**
 * Il raggio SVG in CSS deve avere un'unita' di misura.
 */
function alphano_css_radius_has_unit(string $css): bool
{
    return preg_match('/\br\s*:\s*6(?![a-z%])/i', $css) !== 1;
}
