import type { InertiaLinkProps } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    ClipboardCheck,
    Fuel,
    IdCard,
    Receipt,
    Route,
    Truck,
    Warehouse,
    Wrench,
} from 'lucide-react';
import type { ElementType } from 'react';
import abastecimiento from '@/actions/App/Http/Controllers/AbastecimientoController';
import almacen from '@/actions/App/Http/Controllers/Almacen/AlmacenController';
import asistencia from '@/actions/App/Http/Controllers/AsistenciaController';
import conductores from '@/actions/App/Http/Controllers/ConductorController';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import taller from '@/actions/App/Http/Controllers/TallerController';
import vehiculos from '@/actions/App/Http/Controllers/VehiculoController';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import type { Permiso } from '@/types';

export type DestinoMovil = {
    clave: string;
    titulo: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icono: ElementType;
    /** De qué contador de `pendientesMenu` sale su número, si tiene. */
    pendientes?: 'programacion' | 'taller' | 'abastecimiento';
};

type Candidato = DestinoMovil & {
    /** Con cualquiera de estos permisos, el área es su trabajo. */
    propia: Permiso[];
    /** Con cualquiera de estos, la puede abrir aunque solo sea para mirar. */
    lectura: Permiso[];
};

/**
 * Las áreas que pueden ir en la barra de abajo del celular, en el orden del
 * circuito del ticket.
 *
 * `propia` son permisos de escribir: quien puede dar el apto trabaja en
 * Taller, quien solo tiene `taller.ver` lo consulta. La barra pone primero
 * las áreas propias y, si sobra lugar, las de lectura.
 */
const CANDIDATOS: Candidato[] = [
    {
        clave: 'programacion',
        titulo: 'Programación',
        href: vueltas.index(),
        icono: CalendarCheck,
        pendientes: 'programacion',
        propia: ['programacion.crear'],
        lectura: ['programacion.ver'],
    },
    {
        clave: 'taller',
        titulo: 'Taller',
        href: taller.index(),
        icono: ClipboardCheck,
        pendientes: 'taller',
        propia: ['taller.revisar'],
        lectura: [],
    },
    {
        clave: 'almacen',
        titulo: 'Almacén',
        href: almacen.index(),
        icono: Warehouse,
        propia: ['almacen.despachar', 'almacen.ingresar'],
        lectura: [],
    },
    {
        clave: 'mantenimiento',
        titulo: 'Mantenim.',
        href: mantenimiento.index(),
        icono: Wrench,
        propia: ['mantenimiento.gestionar'],
        lectura: [],
    },
    {
        clave: 'abastecimiento',
        titulo: 'Combustible',
        href: abastecimiento.index(),
        icono: Fuel,
        pendientes: 'abastecimiento',
        propia: ['abastecimiento.registrar'],
        lectura: [],
    },
    {
        clave: 'cobranza',
        titulo: 'Cobranza',
        href: contabilidad.index(),
        icono: Receipt,
        propia: ['cobranza.gestionar'],
        lectura: [],
    },
    {
        clave: 'guias',
        titulo: 'Guías',
        href: viajes.index(),
        icono: Route,
        propia: ['viajes.registrar'],
        lectura: ['viajes.ver'],
    },
    {
        clave: 'conductores',
        titulo: 'Conductores',
        href: conductores.index(),
        icono: IdCard,
        propia: ['conductores.crear'],
        lectura: [],
    },
    {
        clave: 'asistencia',
        titulo: 'Asistencia',
        href: asistencia.index(),
        icono: CalendarDays,
        propia: ['asistencia.marcar'],
        lectura: [],
    },
    {
        clave: 'unidades',
        titulo: 'Unidades',
        href: vehiculos.tractos(),
        icono: Truck,
        propia: [],
        lectura: ['vehiculos.ver'],
    },
];

/**
 * Los destinos de la barra de abajo para una persona, según lo que puede
 * hacer y no según cómo se llame su rol: así un rol armado a mano, o alguien
 * con taller y almacén a la vez, recibe igual su barra.
 *
 * Devuelve a lo más `cupo`: primero las áreas donde trabaja, después las que
 * solo consulta. Lo que no entra queda en «Más».
 */
export function destinosMoviles(
    puede: (permiso: Permiso) => boolean,
    cupo: number,
): DestinoMovil[] {
    const propias = CANDIDATOS.filter((c) => c.propia.some(puede));
    const deLectura = CANDIDATOS.filter(
        (c) => !propias.includes(c) && c.lectura.some(puede),
    );

    return [...propias, ...deLectura].slice(0, cupo);
}
