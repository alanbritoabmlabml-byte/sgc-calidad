<?php

namespace App\Support;

/**
 * Catalogo de permisos del sistema, agrupados por modulo.
 *
 * El rol de un usuario es solo una plantilla inicial: lo que decide si puede
 * hacer algo es su lista de permisos concedidos. Asi el administrador puede
 * armar combinaciones que no encajan en ningun rol, marcando casillas.
 *
 * Para agregar un permiso nuevo: sumarlo al catalogo y a los presets que
 * corresponda. No hace falta migracion.
 */
class Permisos
{
    // ---- Lotes ----
    public const LOTES_VER = 'lotes.ver';
    public const LOTES_CREAR = 'lotes.crear';
    public const LOTES_EDITAR = 'lotes.editar';

    // ---- Inspecciones ----
    public const INSPECCIONES_VER = 'inspecciones.ver';
    public const INSPECCIONES_CREAR = 'inspecciones.crear';
    public const INSPECCIONES_EDITAR = 'inspecciones.editar';
    public const INSPECCIONES_EMITIR = 'inspecciones.emitir';
    public const INSPECCIONES_ANULAR = 'inspecciones.anular';

    // ---- Etiquetas ----
    public const ETIQUETAS_IMPRIMIR = 'etiquetas.imprimir';

    // ---- Plantillas de ensayo ----
    public const PLANTILLAS_VER = 'plantillas.ver';
    public const PLANTILLAS_CREAR = 'plantillas.crear';
    public const PLANTILLAS_EDITAR = 'plantillas.editar';
    public const PLANTILLAS_ELIMINAR = 'plantillas.eliminar';
    public const PLANTILLAS_ACTIVAR = 'plantillas.activar';

    // ---- Catalogos ----
    public const CATALOGOS_VER = 'catalogos.ver';
    public const PRODUCTOS_CREAR = 'productos.crear';
    public const PRODUCTOS_EDITAR = 'productos.editar';
    public const MAQUINAS_GESTIONAR = 'maquinas.gestionar';
    public const SECTORES_GESTIONAR = 'sectores.gestionar';

    // ---- Tableros ----
    public const TABLERO_CALIDAD = 'tablero.calidad';
    public const TABLERO_GERENCIA = 'tablero.gerencia';

    // ---- Usuarios ----
    public const USUARIOS_GESTIONAR = 'usuarios.gestionar';

    /**
     * Catalogo para la pantalla de usuarios: modulo => [permiso => descripcion].
     *
     * @return array<string, array<string, string>>
     */
    public static function catalogo(): array
    {
        return [
            'Lotes de produccion' => [
                self::LOTES_VER => 'Consultar lotes y su trazabilidad',
                self::LOTES_CREAR => 'Crear lotes nuevos',
                self::LOTES_EDITAR => 'Editar los datos de un lote',
            ],
            'Inspecciones' => [
                self::INSPECCIONES_VER => 'Consultar boletas de inspeccion',
                self::INSPECCIONES_CREAR => 'Registrar inspecciones y cargar mediciones',
                self::INSPECCIONES_EDITAR => 'Corregir una inspeccion antes de emitirla',
                self::INSPECCIONES_EMITIR => 'Emitir la boleta y habilitar el certificado del QR',
                self::INSPECCIONES_ANULAR => 'Anular la emision de una boleta ya emitida',
            ],
            'Etiquetas' => [
                self::ETIQUETAS_IMPRIMIR => 'Generar e imprimir hojas de etiquetas QR',
            ],
            'Plantillas de ensayo' => [
                self::PLANTILLAS_VER => 'Consultar las plantillas y sus tolerancias',
                self::PLANTILLAS_CREAR => 'Crear plantillas y revisiones nuevas',
                self::PLANTILLAS_EDITAR => 'Modificar plantillas y parametros existentes',
                self::PLANTILLAS_ELIMINAR => 'Eliminar parametros de una plantilla',
                self::PLANTILLAS_ACTIVAR => 'Poner una revision como vigente',
            ],
            'Catalogos' => [
                self::CATALOGOS_VER => 'Consultar productos, maquinas y sectores',
                self::PRODUCTOS_CREAR => 'Dar de alta productos',
                self::PRODUCTOS_EDITAR => 'Editar productos y su ficha tecnica',
                self::MAQUINAS_GESTIONAR => 'Dar de alta y editar maquinas',
                self::SECTORES_GESTIONAR => 'Dar de alta y editar sectores y procesos',
            ],
            'Tableros' => [
                self::TABLERO_CALIDAD => 'Tablero operativo de Calidad',
                self::TABLERO_GERENCIA => 'Tablero gerencial con indicadores y graficos',
            ],
            'Administracion' => [
                self::USUARIOS_GESTIONAR => 'Crear usuarios y asignar permisos',
            ],
        ];
    }

    /** @return array<int, string> Todas las claves de permiso existentes. */
    public static function todos(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::catalogo())));
    }

    /**
     * Permisos que trae cada rol al crearse. El administrador puede ajustarlos
     * despues casilla por casilla.
     *
     * @return array<int, string>
     */
    public static function preset(string $rol): array
    {
        return match ($rol) {
            // Acceso total.
            'admin' => self::todos(),

            // Calidad: registros, emision de boletas y creacion de plantillas.
            // A proposito SIN plantillas.editar ni plantillas.eliminar: una
            // especificacion vigente no se retoca, se crea una revision nueva.
            'calidad' => [
                self::LOTES_VER, self::LOTES_CREAR, self::LOTES_EDITAR,
                self::INSPECCIONES_VER, self::INSPECCIONES_CREAR,
                self::INSPECCIONES_EDITAR, self::INSPECCIONES_EMITIR,
                self::ETIQUETAS_IMPRIMIR,
                self::PLANTILLAS_VER, self::PLANTILLAS_CREAR,
                self::CATALOGOS_VER, self::PRODUCTOS_CREAR, self::PRODUCTOS_EDITAR,
                self::TABLERO_CALIDAD,
            ],

            // Gerencia: solo mira. Tableros, ultimas inspecciones y boletas.
            'gerencia' => [
                self::TABLERO_GERENCIA, self::TABLERO_CALIDAD,
                self::LOTES_VER, self::INSPECCIONES_VER,
                self::CATALOGOS_VER,
            ],

            // Solo lectura de la operacion.
            'lectura' => [
                self::LOTES_VER, self::INSPECCIONES_VER,
                self::ETIQUETAS_IMPRIMIR, self::TABLERO_CALIDAD,
            ],

            default => [],
        };
    }
}
