/** Un dato con su título, para las cabeceras de los documentos. */
export function Dato({
    titulo,
    children,
}: {
    titulo: string;
    children: React.ReactNode;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{titulo}</dt>
            <dd className="mt-0.5 break-words">{children}</dd>
        </div>
    );
}
