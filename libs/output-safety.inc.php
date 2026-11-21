<?php
declare(strict_types=1);

/**
 * Escape HTML condiviso per tutti i valori provenienti da richieste HTTP.
 *
 * Le pagine che stampano parametri usano htmlspecialchars con questi stessi
 * flag; questa funzione fissa lo standard e viene verificata dai test.
 */
function alphano_html_escape(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Legge un parametro da una lista ordinata di sorgenti, senza variabili dinamiche.
 *
 * @param array<int,array<string,mixed>> $sources Sorgenti in ordine di priorita'
 */
function alphano_request_parameter(array $sources, string $name): string
{
    foreach ($sources as $source) {
        if (!is_array($source) || !array_key_exists($name, $source)) {
            continue;
        }
        $value = $source[$name];
        return is_scalar($value) ? (string)$value : '';
    }
    return '';
}
