import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
    // Relative base keeps lazily imported Inertia page chunks resolvable both
    // when the app is served from the domain root and from a sub-directory
    // such as /gwosevo-cx/public. An absolute base (the default) makes the
    // generated preload helper request /build/assets/* from the domain root,
    // which 404s and leaves the page blank.
    base: "./",
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.ts"],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: { watch: { ignored: ["**/storage/framework/views/**"] } },
});
