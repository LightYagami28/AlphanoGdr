<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/libs/http-context.inc.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpContextTest extends TestCase
{
    #[DataProvider('httpsContextProvider')]
    public function testRilevaContestoHttps(string $url, array $server, bool $expected): void
    {
        self::assertSame($expected, alphano_https_context($url, $server));
    }

    public static function httpsContextProvider(): array
    {
        return [
            'url canonica https' => ['https://game.example', [], true],
            'https diretto' => ['', ['HTTPS' => 'on'], true],
            'porta 443' => ['', ['SERVER_PORT' => 443], true],
            'proxy altervista' => ['', ['HTTP_HOST' => 'example.altervista.org', 'HTTP_X_FORWARDED_PROTO' => 'https'], true],
            'proxy generico non attendibile' => ['', ['HTTP_HOST' => 'game.example', 'HTTP_X_FORWARDED_PROTO' => 'https'], false],
            'http locale' => ['http://localhost', [], false],
            'https disattivato' => ['', ['HTTPS' => 'off'], false],
            'host altervista senza proxy https' => ['', ['HTTP_HOST' => 'example.altervista.org'], false],
        ];
    }

    #[DataProvider('canonicalOriginProvider')]
    public function testNormalizzaOrigine(string $url, string $expected): void
    {
        self::assertSame($expected, alphano_canonical_origin($url));
    }

    public static function canonicalOriginProvider(): array
    {
        return [
            'https porta predefinita' => ['https://GAME.example:443/', 'https://game.example'],
            'http porta predefinita' => ['http://GAME.example:80/', 'http://game.example'],
            'porta non predefinita' => ['http://localhost:8000/', 'http://localhost:8000'],
        ];
    }

    public function testRifiutaUrlSenzaSchemaValido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        alphano_canonical_origin('ftp://game.example');
    }
}
