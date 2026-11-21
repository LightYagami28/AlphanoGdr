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
        $filters = alphanoSearchFilters(
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
        $filters = alphanoSearchFilters(
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
        $filters = alphanoSearchFilters(['org'], ['org' => 12]);
        self::assertSame(['org' => '12'], $filters);
    }

    /**
     * Nomi di filtro non ammessi vengono rifiutati.
     */
    public function testNomiNonValidi(): void
    {
        foreach (['', 'nome-p rogue', '1nome', 'Nome', 'nome;x'] as $name) {
            try {
                alphanoSearchFilters([$name], []);
                self::fail('Il nome '.$name.' doveva essere rifiutato');
            } catch (InvalidArgumentException $e) {
                self::assertSame('Nome filtro non valido', $e->getMessage());
            }
        }
    }

    /**
     * I nomi dei filtri sono centralizzati e completi.
     */
    public function testNomiFiltri(): void
    {
        $names = alphanoSearchFilterNames();
        self::assertContains('nome', $names);
        self::assertContains('city', $names);
        self::assertNotContains('__proto__', $names);
        self::assertCount(14, $names);
    }

    /**
     * La lettura dalla richiesta unisce GET e POST senza valori dinamici.
     */
    public function testFiltriDaRichiesta(): void
    {
        $filters = alphanoSearchFiltersFromRequest(
            ['nome', 'razza'],
            ['nome' => 'Mario'],
            ['razza' => '3']
        );
        self::assertSame(['nome' => 'Mario', 'razza' => '3'], $filters);
        self::assertSame(['nome' => '', 'razza' => ''], alphanoSearchFiltersFromRequest(['nome', 'razza'], [], []));
    }
}
