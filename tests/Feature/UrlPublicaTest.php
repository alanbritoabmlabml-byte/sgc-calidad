<?php

namespace Tests\Feature;

use App\Support\UrlPublica;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La direccion de APP_URL queda impresa dentro de cada codigo QR. Si apunta a
 * un lugar que un cliente no puede alcanzar desde su celular, las etiquetas
 * impresas quedan inservibles y hay que reimprimir todo. Estas pruebas cubren
 * la deteccion que avisa antes de imprimir.
 */
class UrlPublicaTest extends TestCase
{
    private function conUrl(string $url): void
    {
        config(['app.url' => $url]);
    }

    /** @return array<string, array{0: string}> */
    public static function urlsLocales(): array
    {
        return [
            'localhost con puerto' => ['http://localhost:8000'],
            'loopback' => ['http://127.0.0.1:8000'],
            'todas las interfaces' => ['http://0.0.0.0:8000'],
            'subdominio .localhost' => ['http://calidad.localhost'],
        ];
    }

    #[DataProvider('urlsLocales')]
    public function test_detecta_direcciones_que_solo_resuelven_en_el_equipo(string $url): void
    {
        $this->conUrl($url);

        $this->assertTrue(UrlPublica::esLocal(), "{$url} deberia detectarse como local");
        $this->assertFalse(UrlPublica::aptaParaImprimir());
        $this->assertStringContainsString('este mismo equipo', UrlPublica::motivoParaNoImprimir());
    }

    /** @return array<string, array{0: string}> */
    public static function urlsDeRedInterna(): array
    {
        return [
            'IP privada 192.168' => ['http://192.168.0.229:8000'],
            'IP privada 10.x' => ['http://10.0.5.12'],
            'IP privada 172.16' => ['http://172.16.4.9:8000'],
            'link-local' => ['http://169.254.10.10'],
            'nombre .local' => ['http://servidor-calidad.local'],
            'nombre .lan' => ['http://calidad.lan'],
            'nombre de maquina sin dominio' => ['http://srv-calidad'],
        ];
    }

    #[DataProvider('urlsDeRedInterna')]
    public function test_detecta_direcciones_que_solo_funcionan_dentro_de_la_empresa(string $url): void
    {
        $this->conUrl($url);

        $this->assertTrue(UrlPublica::esRedInterna(), "{$url} deberia detectarse como red interna");
        $this->assertFalse(UrlPublica::aptaParaImprimir());
        $this->assertStringContainsString('red interna', UrlPublica::motivoParaNoImprimir());
    }

    /** @return array<string, array{0: string}> */
    public static function urlsPublicas(): array
    {
        return [
            'dominio propio' => ['https://calidad.plasticoscarmen.com'],
            'subdominio de tunel' => ['https://sgc-calidad.trycloudflare.com'],
            'IP publica' => ['https://186.121.10.5'],
        ];
    }

    #[DataProvider('urlsPublicas')]
    public function test_acepta_direcciones_alcanzables_desde_internet(string $url): void
    {
        $this->conUrl($url);

        $this->assertFalse(UrlPublica::esLocal());
        $this->assertFalse(UrlPublica::esRedInterna());
        $this->assertTrue(UrlPublica::aptaParaImprimir(), "{$url} deberia aceptarse");
        $this->assertNull(UrlPublica::motivoParaNoImprimir());
    }

    public function test_avisa_cuando_la_direccion_publica_no_usa_https(): void
    {
        $this->conUrl('http://calidad.plasticoscarmen.com');

        // Es alcanzable, pero el celular del cliente va a advertir por el http.
        $this->assertTrue(UrlPublica::aptaParaImprimir());
        $this->assertTrue(UrlPublica::esSinCifrado());

        $this->conUrl('https://calidad.plasticoscarmen.com');
        $this->assertFalse(UrlPublica::esSinCifrado());
    }
}
