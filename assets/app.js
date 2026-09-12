import 'bootstrap';
import "bootstrap/scss/bootstrap.scss";
import 'bootstrap-icons/font/bootstrap-icons.css';
import Swal from 'sweetalert2';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import { initMultiFileInput } from './multi_file_input.js';
import './styles/app.css';

// Las plantillas Twig invocan Swal desde <script> en linea, asi que tiene que
// estar en el ambito global. Antes venia del <script> del CDN.
window.Swal = Swal;

// Editor de texto enriquecido para pegar la justificacion que manda el
// clasificador (ver DashboardClassifications::confirmTariffFraction()) —
// mismo motivo que Swal: lo usa un <script> en linea, no un modulo.
window.Quill = Quill;

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

// Boton de "mostrar contraseña" (ojo) en login, registro y cambio de
// contraseña. El boton siempre es el hermano inmediato del input en el
// markup (ver templates/login.html.twig, register.html.twig y
// reset_password/reset.html.twig), asi que no hace falta un id por campo.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-toggle-password').forEach((boton) => {
        boton.addEventListener('click', () => {
            const input = boton.previousElementSibling;
            const icono = boton.querySelector('i');
            const mostrando = input.type === 'text';

            input.type = mostrando ? 'password' : 'text';
            icono.classList.toggle('bi-eye', mostrando);
            icono.classList.toggle('bi-eye-slash', !mostrando);
            boton.setAttribute('aria-label', mostrando ? 'Mostrar contraseña' : 'Ocultar contraseña');
        });
    });
});

// Tablas ordenables por columna: clic en el encabezado ordena por esa
// columna, otro clic invierte el orden. Generico via clase/atributo (marca
// la tabla con .js-sortable-table y cada <th> ordenable con .js-sortable) en
// vez de un script por pantalla, porque el mismo listado de pedimentos se
// repite en mas de una plantilla (ver templates/dashboard/imports.html.twig
// y companyImports.html.twig). El texto visible de la celda es lo que se
// compara por default; si hace falta un criterio distinto (ej. ETA: el
// texto "04/09/2026" no ordena bien como texto, pero un ISO "2026-09-04" si),
// el <td> puede traer data-sort con ese valor.
//
// La misma clase .js-sortable-table tambien activa el filtro de texto libre
// (ver mas abajo): una tabla puede traer solo orden, solo filtro (sin ningun
// th.js-sortable) o ambos.
document.addEventListener('DOMContentLoaded', () => {
    // Icono de reposo (aun sin ordenar por esa columna): flechas en ambos
    // sentidos, para que se note que la columna es clickeable antes de que
    // el usuario le dé clic siquiera.
    const ICONO_INACTIVO = 'bi-arrow-down-up';

    document.querySelectorAll('.js-sortable-table').forEach((tabla) => {
        const cuerpo = tabla.tBodies[0];
        const encabezados = Array.from(tabla.querySelectorAll('thead th.js-sortable'));

        if (!cuerpo || encabezados.length === 0) {
            return;
        }

        // La fila de "sin resultados" que inyecta el filtro no es una fila de
        // datos: no tiene una celda por columna, asi que no debe entrar al
        // ordenamiento (tronaria al leer fila.cells[indice] en columnas
        // distintas de la primera). Lo mismo para [data-table-ignore] (ver
        // templates/dashboard/deliveries.html.twig): son formularios ocultos
        // que se despliegan bajo la fila del despacho, no datos de otra fila
        // -- reordenarlas las dejaria pegadas a un despacho que no es el suyo.
        const filasOrdenables = () => Array.from(cuerpo.rows)
            .filter((fila) => !fila.classList.contains('js-table-filter-empty') && !fila.hasAttribute('data-table-ignore'));

        encabezados.forEach((th) => {
            const indice = Array.from(th.parentElement.children).indexOf(th);
            const icono = document.createElement('i');
            icono.className = `bi ${ICONO_INACTIVO} ms-1 text-body-secondary small`;

            th.appendChild(icono);
            th.classList.add('user-select-none');
            th.style.cursor = 'pointer';
            th.dataset.sortDir = '';

            th.addEventListener('click', () => {
                const dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';

                encabezados.forEach((otro) => {
                    otro.dataset.sortDir = '';
                    otro.querySelector('i').className = `bi ${ICONO_INACTIVO} ms-1 text-body-secondary small`;
                });

                th.dataset.sortDir = dir;
                icono.className = `bi ms-1 ${dir === 'asc' ? 'bi-sort-alpha-down' : 'bi-sort-alpha-up'}`;

                const valorDe = (fila) => {
                    const celda = fila.cells[indice];

                    return (celda.dataset.sort ?? celda.textContent.trim()).toLowerCase();
                };

                filasOrdenables()
                    .sort((a, b) => {
                        const va = valorDe(a);
                        const vb = valorDe(b);

                        if (va === vb) return 0;

                        const cmp = va < vb ? -1 : 1;

                        return dir === 'asc' ? cmp : -cmp;
                    })
                    .forEach((fila) => cuerpo.appendChild(fila));
            });
        });
    });
});

// Filtro de texto libre para las mismas tablas .js-sortable-table: un cuadro
// de busqueda que oculta las filas que no contengan el texto escrito, en
// cualquier columna. Se inyecta solo -- no hace falta tocar cada plantilla
// para agregarlo -- justo antes de la tabla (o de su .table-responsive, para
// no quedar atrapado dentro del contenedor con scroll horizontal).
document.addEventListener('DOMContentLoaded', () => {
    // Quita acentos para que "aduana" encuentre "Aduana" y "México" sin que
    // el usuario tenga que teclear la tilde.
    const normalizar = (texto) => texto
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '');

    document.querySelectorAll('.js-sortable-table').forEach((tabla) => {
        const cuerpo = tabla.tBodies[0];

        // data-no-filter: la pantalla ya trae su propio buscador a la medida
        // (ej. classifications.html.twig busca en el servidor por mercancia,
        // quimico, CAS o fraccion) -- agregar el generico encima solo
        // confundiria con dos cuadros de busqueda que no buscan lo mismo. El
        // orden por columna (mas arriba) no se ve afectado por este atributo.
        if (!cuerpo || cuerpo.rows.length === 0 || tabla.hasAttribute('data-no-filter')) {
            return;
        }

        const contenedor = tabla.closest('.table-responsive') ?? tabla;

        const envoltura = document.createElement('div');
        envoltura.className = 'input-group input-group-sm mb-2 js-table-filter-wrapper';
        envoltura.style.maxWidth = '320px';
        envoltura.innerHTML = '<span class="input-group-text bg-transparent border-end-0">'
            + '<i class="bi bi-search"></i></span>'
            + '<input type="search" class="form-control border-start-0 js-table-filter-input" '
            + 'placeholder="Buscar en la tabla…" aria-label="Buscar en la tabla">';

        contenedor.parentNode.insertBefore(envoltura, contenedor);

        // [data-table-ignore] son filas que no son un registro propio (ej. el
        // formulario oculto de "traspasar"/"no se pudo cargar" en despachos,
        // ver deliveries.html.twig): el filtro no las cuenta ni las
        // muestra/oculta, para no revelar un formulario a medio llenar solo
        // porque su texto (una opcion de un <select>, un placeholder) hizo
        // match con la busqueda.
        const filas = Array.from(cuerpo.rows).filter((fila) => !fila.hasAttribute('data-table-ignore'));
        const numColumnas = tabla.tHead?.rows[0]?.cells.length ?? 1;

        const filaSinResultados = document.createElement('tr');
        filaSinResultados.className = 'js-table-filter-empty d-none';
        filaSinResultados.innerHTML = `<td colspan="${numColumnas}" class="text-center text-body-secondary py-3">`
            + 'Sin resultados para esa búsqueda.</td>';
        cuerpo.appendChild(filaSinResultados);

        const input = envoltura.querySelector('.js-table-filter-input');

        input.addEventListener('input', () => {
            const termino = normalizar(input.value.trim());
            let visibles = 0;

            filas.forEach((fila) => {
                const coincide = termino === '' || normalizar(fila.textContent).includes(termino);

                fila.classList.toggle('d-none', !coincide);

                if (coincide) {
                    visibles++;
                }
            });

            filaSinResultados.classList.toggle('d-none', termino === '' || visibles > 0);
        });
    });
});

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
