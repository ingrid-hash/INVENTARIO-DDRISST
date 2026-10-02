import { Eye, EyeOff } from 'lucide-react';

/**
 * Boton de ojo que alterna la visibilidad de un campo de contrasena.
 * Debe colocarse dentro de un contenedor "relative" junto al Input.
 */
export default function BotonVerPassword({ visible, onToggle }: { visible: boolean; onToggle: () => void }) {
    return (
        <button
            type="button"
            tabIndex={-1}
            onClick={onToggle}
            aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
            className="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1.5 focus-visible:ring-2 focus-visible:outline-none"
        >
            {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
        </button>
    );
}
