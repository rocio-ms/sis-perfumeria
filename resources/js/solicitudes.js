import axios from "axios";
import Swal from "sweetalert2";

export function mostrarErrorSolicitud(status) {
    if (Swal.isVisible()) return;

    const mensaje = {
        419: "La sesión o el formulario venció. Actualiza la página e inténtalo nuevamente.",
        429: "Hay demasiadas solicitudes. Espera unos segundos e inténtalo nuevamente.",
        503: "El sistema no está disponible en este momento. Intenta nuevamente en unos minutos.",
    }[status] ?? "No se pudo completar la solicitud. Puedes intentarlo de nuevo o actualizar la página.";

    Swal.fire({
        icon: "error",
        title: "Ocurrió un error",
        text: mensaje,
        showCancelButton: true,
        confirmButtonText: "Actualizar página",
        cancelButtonText: "Cerrar",
        focusCancel: true,
    }).then(({ isConfirmed }) => {
        if (isConfirmed) window.location.reload();
    });
}

export function requiereAviso(status) {
    return status === 419 || status === 429 || status >= 500;
}

export async function solicitar(url, options) {
    const response = await fetch(url, options);
    if (requiereAviso(response.status)) mostrarErrorSolicitud(response.status);
    return response;
}

export const api = axios.create({ headers: { Accept: "application/json" } });
api.interceptors.response.use(undefined, (error) => {
    const status = error.response?.status;
    if (requiereAviso(status)) mostrarErrorSolicitud(status);
    return Promise.reject(error);
});
