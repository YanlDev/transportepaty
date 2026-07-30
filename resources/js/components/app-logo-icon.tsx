import type { SVGProps } from 'react';

/**
 * Silueta de tracto con carreta: la unidad de carga pesada que opera la flota.
 * Pinta con `currentColor`, así hereda el color del contenedor (las pantallas
 * de autenticación lo usan con `fill-current` sobre fondo claro y oscuro).
 */
export default function AppLogoIcon(props: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="currentColor"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="Transpaty"
            {...props}
        >
            {/* Carreta */}
            <path d="M1.75 4h11.5a.75.75 0 0 1 .75.75V14H1.75A.75.75 0 0 1 1 13.25V4.75A.75.75 0 0 1 1.75 4Z" />
            {/* Tracto: cabina y capó */}
            <path d="M15.5 7.5h2.9c.53 0 1.02.28 1.29.73l2.1 3.54c.14.23.21.49.21.76v1.72a.75.75 0 0 1-.75.75H15.5v-7.5Z" />
            {/* Ejes */}
            <circle cx="6" cy="17.75" r="2.25" />
            <circle cx="11.25" cy="17.75" r="2.25" />
            <circle cx="19" cy="17.75" r="2.25" />
        </svg>
    );
}
