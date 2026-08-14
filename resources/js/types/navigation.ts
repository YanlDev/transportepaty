import type { InertiaLinkProps } from '@inertiajs/react';
import type { ElementType } from 'react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: ElementType | null;
    isActive?: boolean;
    badge?: number;
    /** Subitems que convierten la entrada en un desplegable. */
    items?: NavItem[];
};
