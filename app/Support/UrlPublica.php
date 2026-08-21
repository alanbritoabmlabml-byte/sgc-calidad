<?php

namespace App\Support;

/**
 * Decide si la direccion configurada en APP_URL sirve para imprimir codigos QR.
 *
 * El QR de una etiqueta lo escanea un cliente desde su celular, con datos
 * moviles, fuera de la red de la empresa. Si APP_URL apunta al equipo local o a
 * una direccion de red interna, la etiqueta impresa queda inservible y hay que
 * reimprimir todo. Esta clase existe para avisarlo antes de imprimir.
 */
class UrlPublica
{
    /** Direcciones que solo resuelven en el propio equipo. */
    private const LOCALES = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];

    public static function url(): string
    {
        return (string) config('app.url');
    }

    public static function host(): string
    {
        return (string) (parse_url(self::url(), PHP_URL_HOST) ?: '');
    }

    /** El QR solo funcionaria en este mismo equipo. */
    public static function esLocal(): bool
    {
        $host = mb_strtolower(self::host());

        return in_array($host, self::LOCALES, true) || str_ends_with($host, '.localhost');
    }

    /**
     * El QR solo funcionaria dentro de la red de la empresa: una IP privada
     * (10.x, 172.16-31.x, 192.168.x, 169.254.x) o un nombre de red interna.
     */
    public static function esRedInterna(): bool
    {
        $host = mb_strtolower(self::host());

        if (self::esLocal()) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Una IP que no es publica ni reservada global es privada.
            return ! filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        // Nombres de red interna que no existen en el DNS publico.
        foreach (['.local', '.lan', '.internal', '.intranet', '.home'] as $sufijo) {
            if (str_ends_with($host, $sufijo)) {
                return true;
            }
        }

        // Un nombre sin punto es un nombre de maquina de la red, no un dominio.
        return $host !== '' && ! str_contains($host, '.');
    }

    /** Sin HTTPS, el celular del cliente muestra advertencias de seguridad. */
    public static function esSinCifrado(): bool
    {
        return parse_url(self::url(), PHP_URL_SCHEME) === 'http';
    }

    /** Si no se puede imprimir con seguridad, devuelve el motivo. */
    public static function motivoParaNoImprimir(): ?string
    {
        if (self::esLocal()) {
            return 'apunta a este mismo equipo, asi que la etiqueta no se puede abrir '
                .'desde ninguna otra computadora ni desde el celular de un cliente';
        }

        if (self::esRedInterna()) {
            return 'apunta a una direccion de la red interna, asi que la etiqueta solo '
                .'funciona dentro de la empresa: un cliente con datos moviles no va a poder abrirla';
        }

        return null;
    }

    public static function aptaParaImprimir(): bool
    {
        return self::motivoParaNoImprimir() === null;
    }
}
