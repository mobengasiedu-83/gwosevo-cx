import type { PageProps } from "@inertiajs/core";
export interface SharedProps extends PageProps {
    baseUrl: string;
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
