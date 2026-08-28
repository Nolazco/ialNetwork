import 'bootstrap';
import "bootstrap/scss/bootstrap.scss";
import 'bootstrap-icons/font/bootstrap-icons.css';
import Swal from 'sweetalert2';
import { initMultiFileInput } from './multi_file_input.js';
import './styles/app.css';

// Las plantillas Twig invocan Swal desde <script> en linea, asi que tiene que
// estar en el ambito global. Antes venia del <script> del CDN.
window.Swal = Swal;

// Igual que Swal: las plantillas con selectores de archivos lo invocan desde
// <script> en linea.
window.initMultiFileInput = initMultiFileInput;

// Token CSRF en toda peticion fetch que modifique algo.
//
// Las plantillas llaman a los endpoints JSON con fetch() desde <script> en
// linea. En vez de repetir la cabecera en cada llamada —y arriesgar que la
// proxima se olvide— se envuelve fetch una sola vez: cualquier peticion mutante
// al mismo origen sale firmada. Las peticiones a otros dominios no la llevan,
// para no filtrar el token.
const csrfMeta = document.querySelector('meta[name="csrf-token"]');

if (csrfMeta) {
    const seguros = ['GET', 'HEAD', 'OPTIONS'];
    const fetchOriginal = window.fetch;

    window.fetch = function (input, init) {
        init = init || {};

        const url = typeof input === 'string'
            ? input
            : (input instanceof Request ? input.url : String(input));
        const metodo = (init.method
            || (input instanceof Request ? input.method : 'GET')).toUpperCase();
        const mismoOrigen = url.startsWith('/')
            || url.startsWith(window.location.origin);

        if (mismoOrigen && !seguros.includes(metodo)) {
            const headers = new Headers(
                init.headers || (input instanceof Request ? input.headers : undefined)
            );

            if (!headers.has('X-CSRF-Token')) {
                headers.set('X-CSRF-Token', csrfMeta.content);
            }

            init = Object.assign({}, init, { headers });
        }

        return fetchOriginal.call(this, input, init);
    };
}

// Mensajes flash. Los renderiza templates/_flashes.html.twig como JSON; aqui se
// muestran uno tras otro para que no se pisen entre si.
const FLASH_TITLES = {
    success: 'Éxito',
    warning: 'Aviso',
    info: 'Información',
    error: 'Error',
};

const FLASH_ICONS = ['success', 'error', 'warning', 'info', 'question'];

document.addEventListener('DOMContentLoaded', () => {
    const node = document.getElementById('app-flashes');

    if (!node) {
        return;
    }

    let messages;

    try {
        messages = JSON.parse(node.textContent);
    } catch (e) {
        console.error('No se pudieron leer los mensajes flash', e);

        return;
    }

    messages.reduce(
        (chain, { type, text }) => chain.then(() => Swal.fire({
            title: FLASH_TITLES[type] ?? FLASH_TITLES.error,
            text,
            icon: FLASH_ICONS.includes(type) ? type : 'info',
        })),
        Promise.resolve()
    );
});
