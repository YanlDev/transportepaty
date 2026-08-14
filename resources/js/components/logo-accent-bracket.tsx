import type { SVGProps } from 'react';

/**
 * El doble trazo en L del logo real de Transportes Paty: dos ángulos rectos
 * anidados bajo el wordmark, como un marco abierto arriba a la derecha. Es
 * geometría de líneas rectas a propósito (sin curvas) — se pinta con
 * `currentColor` así hereda el gris grafito de marca desde quien lo use.
 */
export default function LogoAccentBracket(props: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 64 34"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            role="presentation"
            aria-hidden="true"
            {...props}
        >
            <path
                d="M3 0 V27 H64"
                stroke="currentColor"
                strokeWidth="5"
                strokeLinecap="square"
                strokeLinejoin="miter"
            />
            <path
                d="M15 10 V19 H52"
                stroke="currentColor"
                strokeWidth="3.2"
                strokeLinecap="square"
                strokeLinejoin="miter"
            />
        </svg>
    );
}
