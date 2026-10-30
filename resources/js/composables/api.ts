import axios from "axios";
import { usePage } from "@inertiajs/vue3";
import type { SharedProps } from "../types";
export function useApi() {
    const page = usePage<SharedProps>();
    const url = (path: string) =>
        `${page.props.baseUrl.replace(/\/$/, "")}/${path.replace(/^\//, "")}`;
    const request = async (path: string, method = "GET", data?: unknown) => {
        const result = await axios({
            url: url(path),
            method,
            data,
            headers: { Accept: "application/json" },
            withXSRFToken: true,
        });
        return result.data;
    };
    const message = (error: unknown): string => {
        if (axios.isAxiosError(error)) {
            if (
                error.response?.status === 401 ||
                error.response?.status === 419
            )
                return "Your session has expired. Sign in again.";
            return (
                Object.values(error.response?.data?.errors || {})
                    .flat()
                    .join("\n") ||
                error.response?.data?.message ||
                error.message
            );
        }
        return error instanceof Error ? error.message : "Request failed.";
    };
    return { request, url, message };
}
