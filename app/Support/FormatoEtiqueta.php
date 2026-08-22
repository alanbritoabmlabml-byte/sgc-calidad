<?php

namespace App\Support;

/**
 * Formatos de etiqueta para impresora termica Zebra.
 *
 * En una termica de rollo cada etiqueta es una pagina: el tamano de @page tiene
 * que coincidir con el de la etiqueta fisica, si no la impresora corta donde no
 * debe y se desperdicia el rollo. Por eso el formato define su propio CSS de
 * pagina en lugar de acomodar varias etiquetas en una A4.
 */
class FormatoEtiqueta
{
    public const ZEBRA_50X30 = '50x30';
    public const ZEBRA_40X25 = '40x25';
    public const A4 = 'a4';
    public const PERSONALIZADO = 'personalizado';

    /** Limites del formato personalizado, en milimetros. */
    public const MIN_MM = 20;

    public const MAX_MM = 210;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogo(): array
    {
        return [
            self::ZEBRA_50X30 => [
                'nombre' => 'Zebra 50 x 30 mm',
                'detalle' => 'Termica de rollo. Es el formato mas usado para rollos y fardos.',
                'ancho' => 50,
                'alto' => 30,
                'qr' => 22,          // lado del QR en mm
                'base' => 1.7,       // tamano de letra base en mm
                'termica' => true,
            ],
            self::ZEBRA_40X25 => [
                'nombre' => 'Zebra 40 x 25 mm',
                'detalle' => 'Termica de rollo, formato chico. Menos datos legibles.',
                'ancho' => 40,
                'alto' => 25,
                'qr' => 18,
                'base' => 1.5,
                'termica' => true,
            ],
            self::PERSONALIZADO => [
                'nombre' => 'Personalizado',
                'detalle' => 'Indica el ancho y el alto en milimetros.',
                'ancho' => 60,
                'alto' => 40,
                'qr' => 26,
                'base' => 1.9,
                'termica' => true,
            ],
            self::A4 => [
                'nombre' => 'Hoja A4 autoadhesiva',
                'detalle' => 'Varias etiquetas por hoja, para impresora comun de oficina.',
                'ancho' => null,
                'alto' => null,
                'qr' => 24,
                'base' => 2,
                'termica' => false,
            ],
        ];
    }

    /**
     * Resuelve un formato con sus medidas. Para el personalizado usa el ancho y
     * el alto recibidos, acotados a un rango razonable, y escala el QR y la
     * tipografia en proporcion.
     *
     * @return array<string, mixed>
     */
    public static function resolver(string $clave, ?float $ancho = null, ?float $alto = null): array
    {
        $catalogo = self::catalogo();
        $formato = $catalogo[$clave] ?? $catalogo[self::ZEBRA_50X30];
        $formato['clave'] = array_key_exists($clave, $catalogo) ? $clave : self::ZEBRA_50X30;

        if ($formato['clave'] !== self::PERSONALIZADO) {
            return $formato;
        }

        $formato['ancho'] = self::acotar($ancho ?? $formato['ancho']);
        $formato['alto'] = self::acotar($alto ?? $formato['alto']);

        // El QR ocupa el lado corto menos el margen, sin pasar de la mitad del
        // ancho: tiene que quedar espacio para los datos legibles.
        $lado = min($formato['alto'] - 6, $formato['ancho'] * 0.45);
        $formato['qr'] = max(12, round($lado, 1));
        $formato['base'] = max(1.2, round($formato['alto'] / 17, 2));

        return $formato;
    }

    private static function acotar(float $mm): float
    {
        return max(self::MIN_MM, min(self::MAX_MM, round($mm, 1)));
    }
}
