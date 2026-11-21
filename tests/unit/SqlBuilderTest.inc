<?php

/**
 * Test del costruttore INSERT parametrizzato usato dal catalogo corporazioni.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Verifica che la SQL sia composta solo da colonne whitelistate e segnaposto.
 */
final class SqlBuilderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Carica le funzioni sotto test prima di ogni test.
     */
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/libs/sql-builder.inc.php';
    }

    /**
     * La SQL contiene solo identificatori validati e un segnaposto per colonna.
     */
    public function testInsertParametrizzato(): void
    {
        [$sql, $params] = alphanoSqlInsert(
            'equip',
            ['shortDesc', 'desc'],
            ['shortDesc' => 'Spada', 'desc' => 'Lama lunga']
        );
        self::assertSame('INSERT INTO `equip` (`shortDesc`,`desc`) VALUES (?,?)', $sql);
        self::assertSame(['Spada', 'Lama lunga'], $params);
    }

    /**
     * L'ordine dei parametri segue la whitelist, non l'ordine dell'array valori.
     */
    public function testOrdineParametri(): void
    {
        [$sql, $params] = alphanoSqlInsert(
            'newequip',
            ['id', 'shortDesc', 'corp'],
            ['corp' => 7, 'shortDesc' => 'Elmo', 'id' => 42]
        );
        self::assertSame('INSERT INTO `newequip` (`id`,`shortDesc`,`corp`) VALUES (?,?,?)', $sql);
        self::assertSame([42, 'Elmo', 7], $params);
    }

    /**
     * Un valore malevolo resta esclusivamente nei parametri associati.
     */
    public function testInputUtenteSoloNeiBind(): void
    {
        $tainted = "0); DROP TABLE equip; --";
        [$sql, $params] = alphanoSqlInsert('equip', ['desc'], ['desc' => $tainted]);
        self::assertStringNotContainsString('DROP', $sql);
        self::assertStringNotContainsString($tainted, $sql);
        self::assertSame([$tainted], $params);
    }

    /**
     * Tabelle e colonne non whitelistate vengono rifiutate.
     */
    public function testWhitelistTabellaEColonne(): void
    {
        try {
            alphanoSqlInsert('utenti', ['desc'], ['desc' => 'x']);
            self::fail('La tabella non whitelistata doveva essere rifiutata');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Tabella non consentita', $e->getMessage());
        }

        foreach (['desc`; DROP TABLE equip; --', 'desc-name', '1desc', 'de sc', ''] as $column) {
            try {
                alphanoSqlInsert('equip', [$column], [$column => 'x']);
                self::fail('La colonna '.$column.' doveva essere rifiutata');
            } catch (InvalidArgumentException $e) {
                self::assertSame('Colonna non consentita', $e->getMessage());
            }
        }
    }

    /**
     * Colonne senza valore e liste vuote non generano query.
     */
    public function testValoriMancanti(): void
    {
        try {
            alphanoSqlInsert('equip', ['desc'], []);
            self::fail('Il valore mancante doveva essere rifiutato');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Valore mancante per desc', $e->getMessage());
        }

        try {
            alphanoSqlInsert('equip', [], []);
            self::fail('La lista vuota doveva essere rifiutata');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Nessuna colonna valida', $e->getMessage());
        }
    }

    /**
     * La whitelist del catalogo contiene tutte le colonne degli INSERT.
     */
    public function testColonneCatalogo(): void
    {
        $columns = alphanoEquipInsertColumns(['argento', 'oro']);
        self::assertContains('shortDesc', $columns);
        self::assertContains('game_rules', $columns);
        self::assertContains('department', $columns);
        self::assertSame(['argento', 'oro'], array_slice($columns, -2));
        self::assertNotContains('id', $columns);

        $withId = alphanoEquipInsertColumns(['argento'], true);
        self::assertSame('id', $withId[0]);
        self::assertContains('argento', $withId);
        self::assertSame('shortDesc', $withId[1]);
    }

    /**
     * Le materie prime sono validate prima di entrare nella whitelist.
     */
    public function testMateriePrimeNonValide(): void
    {
        foreach ([['oro` DROP', 'argento'], [12], [['nested']]] as $materials) {
            try {
                alphanoEquipInsertColumns($materials);
                self::fail('La materia prima non valida doveva essere rifiutata');
            } catch (InvalidArgumentException $e) {
                self::assertSame('Materia prima non valida', $e->getMessage());
            }
        }
    }
}
