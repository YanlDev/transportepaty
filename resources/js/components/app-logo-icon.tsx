import type { ImgHTMLAttributes } from 'react';

export default function AppLogoIcon({
    className,
    alt = 'Logo de la flota',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img {...props} src="/logoflota.png" alt={alt} className={className} />
    );
}
