import { Copiable } from '@/components/copiable';

/**
 * La GR-transportista puede referir más de una GR-remitente (varias cargas
 * de un mismo cliente en un solo viaje). Cada una va en su propia línea con
 * guion —no todas juntas en una sola cadena— porque cada número ya trae su
 * propio guion interno (ej. «T954 - 273462») y concatenarlas sin separar por
 * línea las vuelve ilegibles. Cada línea usa `Copiable`, igual que placa/TUC
 * en el listado de vehículos, para copiarla y conciliar contra SUNAT.
 */
export function GuiasRemitenteCelda({
    guias,
}: {
    guias: { numero: string; ruc: string }[] | null;
}) {
    if (!guias || guias.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex flex-col gap-0.5">
            {guias.map((guia, indice) => (
                <Copiable
                    key={indice}
                    valor={guia.numero}
                    etiqueta="GR remitente"
                >
                    - {guia.numero}
                </Copiable>
            ))}
        </div>
    );
}
