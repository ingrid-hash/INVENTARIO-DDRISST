import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';

/** Muestra el mensaje de exito que el backend envia en la sesion. */
export function AlertaEstado() {
    const { flash } = usePage<SharedData>().props;

    if (!flash?.status) {
        return null;
    }

    return (
        <div className="border-mspas-teal/30 bg-mspas-teal/10 text-mspas-teal flex items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium">
            <CheckCircle2 className="size-4 shrink-0" />
            {flash.status}
        </div>
    );
}
