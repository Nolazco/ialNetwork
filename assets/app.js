import 'bootstrap';
import "bootstrap/scss/bootstrap.scss";
import 'bootstrap-icons/font/bootstrap-icons.css';
import Swal from 'sweetalert2';
import './styles/app.css';

// Las plantillas Twig invocan Swal desde <script> en linea, asi que tiene que
// estar en el ambito global. Antes venia del <script> del CDN.
window.Swal = Swal;

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
