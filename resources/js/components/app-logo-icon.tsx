import { SVGAttributes } from 'react';

/**
 * Marca del sistema: un escudo (el resguardo de los bienes del Estado) con la
 * cruz de salud y, debajo, las cajas del inventario. Es un simbolo propio del
 * sistema, no una reproduccion del escudo nacional ni del logotipo del MSPAS.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M24 3 6 9.6v13.2c0 11.3 7.4 19.5 18 22.2 10.6-2.7 18-10.9 18-22.2V9.6L24 3Z" fill="currentColor" />
            <path d="M21 12h6v5h5v6h-5v5h-6v-5h-5v-6h5v-5Z" className="fill-white" />
            <rect x="15" y="31.5" width="7" height="7" rx="1.2" className="fill-white/85" />
            <rect x="26" y="31.5" width="7" height="7" rx="1.2" className="fill-white/55" />
        </svg>
    );
}
