export type DashboardKpis = {
    vehiculos_total: number;
    vehiculos_operativos: number;
    vehiculos_mantenimiento: number;
    conductores_activos: number;
    sucursales: number;
    combustible_mes: number;
    cargas_pendientes: number;
};

export type FlotaEstado = {
    clave: string;
    label: string;
    cantidad: number;
};

export type EstadoVencimiento = 'vencido' | 'critico' | 'por_vencer';

export type AlertaDocumento = {
    id: string;
    tipo: string;
    referencia: string;
    fecha_vencimiento: string;
    dias_restantes: number;
    estado: EstadoVencimiento;
};

export type AlertaMantenimiento = {
    id: string;
    vehiculo_id: number;
    placa: string;
    servicio: string;
    status: 'vencido' | 'proximo';
    restante_km: number | null;
    restante_dias: number | null;
};

export type CombustibleMes = {
    mes: string;
    total: number;
};

export type ActividadEvento = {
    id: string;
    tipo: 'combustible' | 'mantenimiento';
    placa: string;
    fecha: string;
    detalle: string;
};

export type DashboardData = {
    esGestor: boolean;
    kpis: DashboardKpis;
    flotaPorEstado: FlotaEstado[];
    alertasDocumentos: AlertaDocumento[];
    alertasMantenimiento: AlertaMantenimiento[];
    combustibleSerie: CombustibleMes[];
    actividad: ActividadEvento[];
};
