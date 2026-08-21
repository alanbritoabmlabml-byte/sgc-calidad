import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Grilla de mediciones de una inspeccion.
 *
 * Calcula el promedio por caracteristica y pinta en rojo cada muestra que cae
 * fuera de especificacion, mientras el inspector escribe. Los limites llegan
 * desde el servidor ya resueltos contra el producto del lote.
 */
Alpine.data('grillaMediciones', (config) => ({
    // parametros: [{ id, muestras, min, max, tipo, conformes: [] }]
    parametros: config.parametros ?? [],
    valores: config.valores ?? {},
    muestras: config.muestras ?? 8,
    muestrasMax: config.muestrasMax ?? 13,

    /**
     * Se crea la fila de cada parametro antes de que se enlacen los x-model.
     * Sin esto, valores[id][n] falla al indexar un parametro sin datos previos.
     */
    init() {
        this.parametros.forEach((p) => {
            if (!this.valores[p.id]) {
                this.valores[p.id] = {};
            }
        });
    },

    /** Limites de un parametro, o null cuando no tiene especificacion. */
    limites(id) {
        return this.parametros.find((p) => p.id === id) ?? null;
    },

    valor(id, muestra) {
        return this.valores[id]?.[muestra] ?? '';
    },

    setValor(id, muestra, valor) {
        if (!this.valores[id]) {
            this.valores[id] = {};
        }
        this.valores[id][muestra] = valor;
    },

    /** Lista de valores numericos cargados para un parametro. */
    numeros(id) {
        const fila = this.valores[id] ?? {};

        return Object.values(fila)
            .filter((v) => v !== '' && v !== null && v !== undefined && !isNaN(v))
            .map(Number);
    },

    promedio(id) {
        const nums = this.numeros(id);

        if (nums.length === 0) {
            return null;
        }

        const suma = nums.reduce((a, b) => a + b, 0);

        return Math.round((suma / nums.length) * 100) / 100;
    },

    promedioTexto(id) {
        const p = this.promedio(id);

        return p === null ? '' : p.toFixed(2);
    },

    /**
     * Veredicto de un valor suelto: true dentro, false fuera, null sin evaluar.
     * Replica la logica de TestParameter::evaluar() del servidor; el servidor
     * vuelve a evaluar al guardar, esto es solo para el aviso inmediato.
     */
    evaluar(id, valor) {
        if (valor === '' || valor === null || valor === undefined) {
            return null;
        }

        const p = this.limites(id);

        if (!p) {
            return null;
        }

        if (p.tipo === 'select') {
            return p.conformes?.length ? p.conformes.includes(String(valor)) : null;
        }

        if (isNaN(valor)) {
            return null;
        }

        if (p.min === null && p.max === null) {
            return null;
        }

        const n = Number(valor);

        if (p.min !== null && n < p.min) return false;
        if (p.max !== null && n > p.max) return false;

        return true;
    },

    /** Clase CSS de la celda segun el veredicto. */
    claseCelda(id, valor) {
        const v = this.evaluar(id, valor);

        if (v === false) return 'ring-2 ring-red-400 bg-red-50 text-red-800';
        if (v === true) return 'bg-emerald-50';

        return '';
    },

    /** Veredicto del promedio, que es el que define el estado del parametro. */
    clasePromedio(id) {
        const p = this.promedio(id);

        if (p === null) return 'text-slate-400';

        const v = this.evaluar(id, p);

        if (v === false) return 'text-red-700 font-bold';
        if (v === true) return 'text-emerald-700 font-semibold';

        return 'text-slate-700 font-semibold';
    },

    /** Cantidad de parametros con el promedio fuera de especificacion. */
    get desvios() {
        return this.parametros.filter((p) => {
            const prom = this.promedio(p.id);

            return prom !== null && this.evaluar(p.id, prom) === false;
        }).length;
    },

    /** Estado que el sistema sugiere, en vivo. */
    get estadoSugerido() {
        const fuera = this.parametros.filter((p) => {
            const prom = this.promedio(p.id);

            return prom !== null && this.evaluar(p.id, prom) === false;
        });

        if (fuera.length === 0) {
            return { code: 'P.C', texto: 'P.C - Pasa conforme', clase: 'bg-emerald-100 text-emerald-800' };
        }

        if (fuera.some((p) => p.requerido)) {
            return { code: 'RECHAZADO', texto: 'RECHAZADO - No conforme', clase: 'bg-red-100 text-red-800' };
        }

        return { code: 'P.OBS', texto: 'P.OBS - Pasa con observacion', clase: 'bg-amber-100 text-amber-800' };
    },

    agregarMuestra() {
        if (this.muestras < this.muestrasMax) {
            this.muestras++;
        }
    },

    quitarMuestra() {
        if (this.muestras > 1) {
            this.muestras--;
        }
    },

    get rangoMuestras() {
        return Array.from({ length: this.muestras }, (_, i) => i + 1);
    },
}));

Alpine.start();
