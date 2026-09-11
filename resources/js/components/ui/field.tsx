import { useId } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

type Props = {
    label: string;
    error?: string;
    required?: boolean;
    /** Nota bajo el control, para reglas que no entran en la etiqueta. */
    ayuda?: string;
    children: (id: string) => React.ReactNode;
};

/**
 * Un campo de formulario: etiqueta, control y error. El id se genera acá y se
 * pasa al hijo como argumento, así el `htmlFor` de la etiqueta y el control
 * nunca se desincronizan.
 */
export function Field({ label, error, required, ayuda, children }: Props) {
    const id = useId();

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>
                {label}
                {required && <span className="text-destructive"> *</span>}
            </Label>
            {children(id)}
            {ayuda && <p className="text-xs text-muted-foreground">{ayuda}</p>}
            <InputError message={error} />
        </div>
    );
}
