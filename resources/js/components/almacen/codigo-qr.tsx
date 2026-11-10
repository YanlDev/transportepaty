/**
 * Un QR dibujado desde su cuadrícula de celdas (la arma el servidor): sin
 * imágenes ni librerías, y nítido a cualquier tamaño.
 */
export function CodigoQr({
    celdas,
    className,
    titulo = 'Código QR',
}: {
    celdas: boolean[][];
    className?: string;
    titulo?: string;
}) {
    const lado = celdas.length;
    const margen = 2;

    return (
        <svg
            viewBox={`0 0 ${lado + margen * 2} ${lado + margen * 2}`}
            className={className}
            shapeRendering="crispEdges"
            role="img"
            aria-label={titulo}
        >
            <rect
                width={lado + margen * 2}
                height={lado + margen * 2}
                fill="#fff"
            />
            {celdas.map((fila, y) =>
                fila.map(
                    (negra, x) =>
                        negra && (
                            <rect
                                key={`${x}-${y}`}
                                x={x + margen}
                                y={y + margen}
                                width={1}
                                height={1}
                                fill="#000"
                            />
                        ),
                ),
            )}
        </svg>
    );
}
