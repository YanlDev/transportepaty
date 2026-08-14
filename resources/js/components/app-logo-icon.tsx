import type { SVGProps } from 'react';

/**
 * Monograma de marca: la «P» de Transportes Paty, geométrica y recta (sin
 * curvas), a tono con que el resto de la UI no redondea nada
 * (`--radius-*: 0px` en `app.css`). El logo real de Paty no tiene un
 * pictograma propio — es un wordmark—, así que este es el recorte que
 * funciona a tamaño de ícono cuadrado (sidebar, header, insignia de login).
 * Pinta con `currentColor`, así hereda el color del contenedor (usado con
 * `fill-current` sobre fondo claro y oscuro en varias pantallas).
 */
export default function AppLogoIcon(props: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="currentColor"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="Transportes Paty"
            {...props}
        >
            {/* Palo vertical */}
            <rect x="4" y="3" width="3.2" height="18" />
            {/* Panza de la P: marco cuadrado hueco, sin redondear */}
            <rect x="7.2" y="3" width="9.4" height="3.2" />
            <rect x="13.4" y="3" width="3.2" height="9.4" />
            <rect x="7.2" y="9.2" width="9.4" height="3.2" />
        </svg>
    );
}
