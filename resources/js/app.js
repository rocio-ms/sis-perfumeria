import "../css/app.css";
import "sweetalert2/dist/sweetalert2.min.css";

import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";

import "vuetify/styles";
import "@mdi/font/css/materialdesignicons.css";
import { createVuetify } from "vuetify";

import "primeicons/primeicons.css";
import PrimeVue from "primevue/config";
import Aura from "@primeuix/themes/aura";

import "@fontsource/poppins";

import Chart from "primevue/chart";
import Tag from "primevue/tag";
import Button from "primevue/button";
import ProgressBar from "primevue/progressbar";
import Avatar from "primevue/avatar";
import SelectButton from "primevue/selectbutton";
import Dialog from "primevue/dialog";
import Select from "primevue/select";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import Message from "primevue/message";
import FloatLabel from "primevue/floatlabel";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import Divider from "primevue/divider";
import Breadcrumb from "primevue/breadcrumb";
import InputText from "primevue/inputtext";
import Checkbox from "primevue/checkbox";
import ToggleSwitch from "primevue/toggleswitch";
import AutoComplete from "primevue/autocomplete";
import DatePicker from "primevue/datepicker";
import FileUpload from "primevue/fileupload";
import Password from "primevue/password";

document.documentElement.classList.remove("dark", "dark-mode", "p-dark");

const appName = import.meta.env.VITE_APP_NAME || "Perfumería Belleza";

const vuetify = createVuetify({
    theme: {
        defaultTheme: "light",
    },
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: false,
                    },
                },
            })

            .component("Chart", Chart)
            .component("Tag", Tag)
            .component("Button", Button)
            .component("ProgressBar", ProgressBar)
            .component("Avatar", Avatar)
            .component("SelectButton", SelectButton)
            .component("Dialog", Dialog)
            .component("Select", Select)
            .component("InputNumber", InputNumber)
            .component("Textarea", Textarea)
            .component("Message", Message)
            .component("FloatLabel", FloatLabel)
            .component("IconField", IconField)
            .component("InputIcon", InputIcon)
            .component("Divider", Divider)
            .component("Breadcrumb", Breadcrumb)
            .component("InputText", InputText)
            .component("Checkbox", Checkbox)
            .component("ToggleSwitch", ToggleSwitch)
            .component("AutoComplete", AutoComplete)
            .component("DatePicker", DatePicker)
            .component("FileUpload", FileUpload)
            .component("Password", Password)
            .use(vuetify)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
