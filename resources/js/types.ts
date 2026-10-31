import type { PageProps } from "@inertiajs/core";
export interface SharedProps extends PageProps {
    baseUrl: string;
    home: string | null;
    portals: string[];
    mfaAvailable: boolean;
    auth: null | {
        name: string;
        email: string;
        permissions: string[];
        roles: string[];
        mfaEnabled: boolean;
    };
}
export interface RecordItem {
    id: string | number;
    name: string;
    [key: string]: any;
}

export interface NavigationItem {
    id: string;
    title: string;
    resource?: string;
    status: "available" | "planned";
    url: string;
}
export interface NavigationGroup {
    id: string;
    title: string;
    section: string;
    items: NavigationItem[];
}

export interface SearchRecord {
    type: string;
    label: string;
    id: string;
    title: string;
    subtitle: string | null;
    url: string;
}

/**
 * Declarative form control used by the extension editor. `options` maps a
 * stored value to its display label for select controls.
 */
export interface FieldSpec {
    key: string;
    label: string;
    type?: "text" | "password" | "email" | "number" | "tel" | "select";
    required?: boolean;
    hint?: string;
    inputmode?: "numeric" | "tel";
    options?: Record<string, string>;
    reveal?: boolean;
}

