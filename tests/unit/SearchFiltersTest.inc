<?php

/**
 * Test dell'estrazione esplicita dei filtri di ricerca.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Verifica che i filtri siano chiavi di array e non variabili dinamiche.
 */
final class SearchFiltersTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Carica le funzioni sotto test prima di ogni test.
     */
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/libs/search-filters.inc.php';
    }

    /**
     * Ogni nome ammesso produce una chiave con il valore della richiesta.
     */
    public function testFiltriEspliciti(): void
    {
        $filters = alphano_search_filters(
            ['nome', 'luogo', 'razza'],
            ['nome' => 'Mario', 'luogo' => 'Piazza', 'extra' => 'ignorato']
        );
        self::assertSame(['nome' => 'Mario', 'luogo' => 'Piazza', 'razza' => ''], $filters);
        self::assertSame('Mario', $filters['nome']);
        self::assertArrayNotHasKey('extra', $filters);
    }

    /**
     * Valori non scalari non possono diventare filtri.
     */
    public function testValoriNonScalari(): void
    {
        $filters = alphano_search_filters(
            ['nome', 'online'],
            ['nome' => ['x'], 'online' => new stdClass()]
        );
        self::assertSame(['nome' => '', 'online' => ''], $filters);
    }

    /**
     * I numeri vengono normalizzati a stringa come nel template.
     */
    public function testValoriScalari(): void
    {
        $filters = alphano_search_filters(['org'], ['org' => 12]);
        self::assertSame(['org' => '12'], $filters);
    }

    /**
     * Nomi di filtro non ammessi vengono rifiutati.
     */
    public function testNomiNonValidi(): void
    {
        foreach (['', 'nome-p rogue', '1nome', 'Nome', 'nome;x'] as $name) {
            try {
                alphano_search_filters([$name], []);
                self::fail('Il nome '.$name.' doveva essere rifiutato');
            } catch (InvalidArgumentException $e) {
                self::assertSame('Nome filtro non valido', $e->getMessage());
            }
        }
    }
}
