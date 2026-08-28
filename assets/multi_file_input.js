/**
 * Selector de archivos que acumula lo elegido entre varias veces (clic o
 * arrastrar y soltar), a diferencia del <input type="file" multiple> nativo,
 * que reemplaza toda la selección cada vez que se vuelve a abrir el diálogo.
 *
 * El input real vive superpuesto (opacity: 0) sobre la zona con el borde
 * punteado, así que tanto el clic como el arrastrar-y-soltar los recibe el
 * propio input de forma nativa (los navegadores ya soportan soltar archivos
 * sobre un <input type="file">) — no hace falta manejar dragover/drop a mano.
 */
export function initMultiFileInput({ inputId, listId, maxFiles }) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);

    if (!input || !list) {
        return;
    }

    let files = [];

    function formatSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        }

        if (bytes < 1024 * 1024) {
            return Math.round(bytes / 1024) + ' KB';
        }

        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function render() {
        list.innerHTML = '';

        files.forEach(function (file, index) {
            const item = document.createElement('li');
            item.className = 'list-group-item d-flex justify-content-between align-items-center gap-2';

            const label = document.createElement('span');
            label.className = 'text-truncate';
            label.textContent = file.name + ' (' + formatSize(file.size) + ')';

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger flex-shrink-0';
            remove.title = 'Quitar';
            remove.innerHTML = '<i class="bi bi-x-lg"></i>';
            remove.addEventListener('click', function () {
                files.splice(index, 1);
                sync();
            });

            item.appendChild(label);
            item.appendChild(remove);
            list.appendChild(item);
        });
    }

    function sync() {
        const transfer = new DataTransfer();
        files.forEach(function (file) {
            transfer.items.add(file);
        });
        input.files = transfer.files;
        render();
    }

    input.addEventListener('change', function () {
        const incoming = Array.from(input.files);

        if (maxFiles && files.length + incoming.length > maxFiles) {
            alert('Selecciona como máximo ' + maxFiles + ' archivos.');
        }

        incoming.forEach(function (file) {
            if (maxFiles && files.length >= maxFiles) {
                return;
            }

            const yaEstaba = files.some(function (existing) {
                return existing.name === file.name
                    && existing.size === file.size
                    && existing.lastModified === file.lastModified;
            });

            if (!yaEstaba) {
                files.push(file);
            }
        });

        sync();
    });
}
