<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/libs/kernel.inc.php';

use PHPUnit\Framework\TestCase;

final class KernelFunctionsTest extends TestCase
{
    public function testFiltraIdentificativiInteriEStringheValide(): void
    {
        self::assertSame(
            [1, 2, 100],
            alphano_sql_ids([1, '2', '012', 0, -3, '0', 'x12', 12.5, null, true, false, '', '100', 100, 2])
        );
    }

    public function testValoriNonArrayRestituisconoListaVuota(): void
    {
        self::assertSame([], alphano_sql_ids('1,2,3'));
        self::assertSame([], alphano_sql_ids(null));
        self::assertSame([], alphano_sql_ids(7));
    }

    public function testGeneraSegnapostoPerConteggioRichiesto(): void
    {
        self::assertSame('?,?,?', alphano_sql_placeholders(3));
        self::assertSame('?', alphano_sql_placeholders(1));
        self::assertSame('', alphano_sql_placeholders(0));
        self::assertSame('', alphano_sql_placeholders(-2));
    }
}
