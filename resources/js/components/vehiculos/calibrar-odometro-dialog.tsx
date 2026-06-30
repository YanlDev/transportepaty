import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { calibrar } from '@/actions/App/Http/Controllers/Integraciones/TracksolidController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    vehiculoId: number;
    kilometrajeActual: number;
    trigger: React.ReactNode;
};

export function CalibrarOdometroDialog({
    vehiculoId,
    kilometrajeActual,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        kilometraje: String(kilometrajeActual),
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(calibrar(vehiculoId).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Calibrar odómetro</DialogTitle>
                        <DialogDescription>
                            Ingresa el kilometraje real que marca el tablero
                            ahora. El sistema recordará la lectura del GPS en
                            este momento y, de aquí en adelante, sólo sumará los
                            kilómetros que recorra.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="my-4 space-y-2">
                        <Label htmlFor="kilometraje">
                            Kilometraje real (km)
                        </Label>
                        <Input
                            id="kilometraje"
                            type="number"
                            min={0}
                            value={data.kilometraje}
                            onChange={(e) =>
                                setData('kilometraje', e.target.value)
                            }
                            placeholder="50000"
                            autoFocus
                        />
                        <InputError message={errors.kilometraje} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-emerald-800 hover:bg-emerald-900"
                        >
                            {processing && <Spinner />}
                            Calibrar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
