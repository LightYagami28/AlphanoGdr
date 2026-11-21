<?php
declare(strict_types=1);

/**
 * Nomi dei filtri ammessi nella ricerca personaggi.
 *
 * @return array<int,string>
 */
function alphanoSearchFilterNames(): array
{
    return [
        'nome', 'luogo', 'razza', 'online', 'araldica', 'caricacorporazione',
        'caricaorganizzazione', 'categoria', 'corporazione', 'organizzazione',
        'al', 'sesso', 'etnia', 'city',
    ];
}

/**
 * Estrae i filtri di ricerca in un array associativo esplicito.
 *
 * Sostituisce le variabili variabili: il nome di ogni filtro e' sempre una
 * chiave di array, mai il nome di una variabile PHP.
 *
 * @param array<int,string>        $names Nomi dei filtri ammessi
 * @param array<string,mixed>      $input Parametri della richiesta
 * @return array<string,string>
 */
function alphanoSearchFilters(array $names, array $input): array
{
    $filters = [];
    foreach ($names as $name) {
        if (!is_string($name) || preg_match('/^[a-z][a-z0-9]*$/D', $name) !== 1) {
            throw new InvalidArgumentException('Nome filtro non valido');
        }
        $value = $input[$name] ?? '';
        $filters[$name] = is_scalar($value) ? (string)$value : '';
    }
    return $filters;
}

/**
 * Legge i filtri dalla richiesta HTTP con priorita' esplicita.
 *
 * @param array<string,mixed> $get  Parametri GET
 * @param array<string,mixed> $post Parametri POST
 * @return array<string,string>
 */
function alphanoSearchFiltersFromRequest(array $names, array $get, array $post): array
{
    return alphanoSearchFilters($names, array_merge($get, $post));
}
