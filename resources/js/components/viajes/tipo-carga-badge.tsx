import { Layers, Mountain, Package, Sparkles, Tag } from 'lucide-react';
import { cn } from '@/lib/utils';

const CLASE_ICONO = 'size-3.5';

/**
 * Un ícono por tipo de carga — separado del color de cliente a propósito
 * (ver `ClienteChip`): esta etiqueta usa tonos neutros/`secondary` en vez de
 * color propio para no competir por atención con el chip de cliente, que es
 * la señal principal de la fila.
 *
 * Renderiza el ícono directo por `case` (en vez de guardar el componente en
 * una variable y hacer `<Icono />`) porque esto último dispara la regla
 * `react-hooks/static-components` del linter.
 */
function IconoTipoCarga({ valor }: { valor: string }) {
    switch (valor) {
        case 'concentrado':
            return <Mountain className={CLASE_ICONO} />;
        case 'metalico':
            return <Layers className={CLASE_ICONO} />;
        case 'escoria':
            return <Sparkles className={CLASE_ICONO} />;
        case 'materiales':
            return <Package className={CLASE_ICONO} />;
        default:
            return <Tag className={CLASE_ICONO} />;
    }
}

export function TipoCargaBadge({
    valor,
    label,
    className,
}: {
    valor: string;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md bg-secondary px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-secondary-foreground',
                className,
            )}
        >
            <IconoTipoCarga valor={valor} />
            {label}
        </span>
    );
}
