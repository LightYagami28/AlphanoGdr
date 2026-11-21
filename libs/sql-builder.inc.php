<?php
declare(strict_types=1);

/**
 * Costruttore di INSERT con colonne validate e soli segnaposto PDO.
 *
 * Le colonne ammesse sono passate esplicitamente dal chiamante: la SQL non
 * contiene mai valori provenienti da $_GET/$_POST, che finiscono solo nei
 * parametri binds.
 *
 * @param array<int,string>        $columns Colonne ammesse, in ordine
 * @param array<string,mixed>      $values  Valori indicizzati per colonna
 * @return array{0:string,1:array<int,mixed>}
 */
function alphano_sql_insert(string $table, array $columns, array $values): array
{
    $tables = ['equip' => true, 'newequip' => true];
    if (!isset($tables[$table])) {
        throw new InvalidArgumentException('Tabella non consentita');
    }

    $quoted = [];
    $params = [];
    foreach ($columns as $column) {
        if (!is_string($column) || preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $column) !== 1) {
            throw new InvalidArgumentException('Colonna non consentita');
        }
        if (!array_key_exists($column, $values)) {
            throw new InvalidArgumentException('Valore mancante per '.$column);
        }
        $quoted[] = '`'.$column.'`';
        $params[] = $values[$column];
    }
    if ($quoted === []) {
        throw new InvalidArgumentException('Nessuna colonna valida');
    }

    $sql = 'INSERT INTO `'.$table.'` ('.implode(',', $quoted).') VALUES ('
         .implode(',', array_fill(0, count($quoted), '?')).')';
    return [$sql, $params];
}
