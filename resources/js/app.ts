import "../css/app.css";
import { createApp, h, type DefineComponent } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";

createInertiaApp({
    title: (title) => (title ? `${title} · GWO SEVO` : "GWO SEVO"),
    resolve: async (name) => {
        const pages = import.meta.glob<{ default: DefineComponent }>(
            "./pages/**/*.vue",
        );
        return (await pages[`./pages/${name}.vue`]()).default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
