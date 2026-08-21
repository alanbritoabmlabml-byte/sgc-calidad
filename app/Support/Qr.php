<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Genera codigos QR como SVG en linea.
 *
 * Se usa SVG y no PNG a proposito: el vector no pierde definicion al imprimir
 * las etiquetas y no depende de la extension GD del servidor.
 */
class Qr
{
    /**
     * Devuelve el markup SVG del QR, listo para incrustar en la etiqueta.
     *
     * @param  int  $tamano  Lado del QR en pixeles.
     * @param  int  $margen  Modulos de zona silenciosa. El minimo del estandar es 4;
     *                       se usa 1 para aprovechar la etiqueta impresa, que ya
     *                       tiene su propio margen blanco alrededor.
     */
    public static function svg(string $contenido, int $tamano = 160, int $margen = 1): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($tamano, $margen),
                new SvgImageBackEnd()
            )
        );

        // Nivel de correccion M: tolera ~15% de dano, suficiente para una
        // etiqueta que va pegada a un rollo o a un fardo en planta.
        $svg = $writer->writeString($contenido, 'utf-8', ErrorCorrectionLevel::M());

        // Se quita la declaracion XML: al incrustar el SVG dentro del HTML,
        // el navegador la rechaza si no esta al inicio del documento.
        return preg_replace('/<\?xml[^>]*\?>\s*/', '', $svg);
    }
}
