# IAL Network — Gestor de despachos aduanales

Aplicación Symfony 7.2 para administrar el ciclo completo de una importación /
exportación: desde que el cliente da el aviso hasta que se cierra el expediente
con su cuenta de gastos.

## El flujo de negocio

| # | Etapa | Quién | Entidades | Estado |
|---|-------|-------|-----------|--------|
| 1 | Alerta de importación/exportación y carga de documentos | Cliente | `ImportRequest`, `ImportDocument`, `Container` | Implementado |
| 2 | Alta del pedimento y fases de la operación | Ejecutivo | `Operation` | Solo modelo + EasyAdmin |
| 3 | Aviso al transporte | Ejecutivo → Transportista | `Delivery`, `FreightHauler` | Solo modelo + EasyAdmin |
| 4 | Entrega de mercancía y devolución de vacío | Transportista | `EmptyReturn`, `ContainerYard` | Solo modelo + EasyAdmin |
| 5 | Cierre del expediente y cuenta de gastos | Ejecutivo | `InternInvoice` | Solo modelo + EasyAdmin |

`ImportRequest` es el expediente: todo lo demás cuelga de él.

## Roles

| Rol | Alcance |
|-----|---------|
| `ROLE_ADMIN` | Todo, incluida la administración de usuarios (`/dashboard/usuarios`) |
| `ROLE_EXECUTIVE` | Operación completa y el backend de EasyAdmin (`/admin`) |
| `ROLE_CLIENT` | Sus empresas afiliadas y sus propias solicitudes |
| `ROLE_FH` | Transportista |

El registro público siempre crea `ROLE_CLIENT` con estatus `pending`; un admin
lo activa y le asigna el rol definitivo. Las cuentas `pending` e `inactive` no
pueden iniciar sesión (`App\Security\UserChecker`).

## Arquitectura

Hay **dos interfaces sobre el mismo modelo**:

- `/dashboard/*` — el dashboard propio, con plantillas Twig escritas a mano.
  Es donde vive el flujo de negocio real.
- `/admin` — EasyAdmin, CRUD genérico sobre las 14 entidades. Herramienta
  interna de respaldo, restringida a `ROLE_EXECUTIVE` y superiores.

```
src/
  Controller/          Dashboard propio (rutas /dashboard/*)
  Controller/Admin/    EasyAdmin (DashboardController + un CrudController por entidad)
  Entity/              14 entidades Doctrine
  Repository/          Un repositorio por entidad
  Security/            UserChecker
templates/
  dashboard/           Vistas del dashboard, todas extienden baseDashboard.html.twig
  easyAdmin/           Portada de EasyAdmin
docker/
  php/                 Dockerfile, php.ini y entrypoint del contenedor de aplicación
  apache/              VirtualHost que apunta a public/
```

## Cómo levantarlo (Windows)

Solo se necesita **Docker Desktop**. PHP, Composer y Node viven dentro del
contenedor; no hay que instalar nada en Windows.

```bash
docker compose up -d
```

El primer arranque tarda varios minutos: el entrypoint corre `composer install`,
`npm install` y compila los assets con Encore. Se puede seguir el avance con:

```bash
docker compose logs -f app
```

| Servicio | URL | Notas |
|----------|-----|-------|
| Aplicación | http://localhost:8000 | |
| Mailpit (correo de prueba) | http://localhost:8025 | |
| PostgreSQL | `localhost:5432` | usuario `app`, base `app` |

### Por qué vendor/ y var/ están en volúmenes

Docker Desktop corre sobre WSL2, así que el bind mount hacia el sistema de
archivos de Windows es unas **60 veces más lento** que el disco del contenedor
(6541 ms contra 103 ms para leer 1500 archivos PHP). Una petición de Symfony en
modo dev abre más de mil archivos de `vendor/` y reescribe `var/cache`, de modo
que cada página tardaba ~6 s; con la petición extra de la barra del profiler,
el navegador veía 15-20 s por click.

Por eso **solo el código que se edita a mano vive en el bind mount**. `vendor/`,
`var/`, `node_modules` y las cachés de descarga están en volúmenes nombrados. No
los muevas de vuelta al bind mount.

La copia de `vendor/` que queda en el host queda ensombrecida por el volumen,
pero sigue sirviendo para el autocompletado del editor. Después de un
`composer require` hay que resincronizarla si se quiere que el IDE la vea:

```bash
docker compose cp app:/app/vendor ./vendor
```

### Base de datos y datos de prueba

El esquema lo construyen las migraciones y los datos de prueba salen de las
fixtures. Para dejar la base como recién instalada:

```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

```bash
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

Las fixtures **borran todo** antes de cargar. Dejan una cuenta activa por rol,
todas con la contraseña `Qa123456!`:

| Correo | Rol |
|--------|-----|
| `admin@qa.com` | `ROLE_ADMIN` |
| `ejecutivo@qa.com` | `ROLE_EXECUTIVE` |
| `cliente@qa.com` | `ROLE_CLIENT` |
| `transportista@qa.com` | `ROLE_FH` |

Más los catálogos mínimos para poder recorrer el flujo: un recinto, dos
proveedores, una empresa asociada a `cliente@qa.com`, y el registro de
transportista que necesitan los despachos y las devoluciones de vacío.

`DoctrineFixturesBundle` solo está registrado en `dev` y `test`, así que estas
credenciales no pueden cargarse en producción.

Las cuentas reales se crean con un comando aparte, que pide la contraseña por
consola para no dejarla escrita en ningún archivo:

```bash
docker compose exec app php bin/console app:user:create correo@ejemplo.com ROLE_ADMIN --name="Nombre" --last-name="Apellidos"
```

### Secretos

`APP_SECRET` vive en `.env.local`, que no se versiona. Si el archivo falta, hay
que crearlo:

```bash
docker compose exec app php -r "echo 'APP_SECRET='.bin2hex(random_bytes(16)).PHP_EOL;" > .env.local
```

## Comandos frecuentes

Todos se ejecutan dentro del contenedor `app`.

```bash
docker compose exec app php bin/console cache:clear
```

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
```

```bash
docker compose exec app php bin/console doctrine:migrations:diff
```

```bash
docker compose exec app npm run watch
```

```bash
docker compose exec database psql -U app -d app
```

## Deuda técnica conocida

- Los endpoints AJAX (`editUser`, `changeRole`, `editCompany`,
  `associateCompany`, alta y borrado de documentos) **no validan CSRF**. Los
  formularios POST tradicionales sí.
- `APP_SECRET` y `backup.sql` siguen presentes en el historial de git aunque ya
  no estén en el árbol de trabajo. Las credenciales deben rotarse.
- Las rutas de subida (`uploads/empresas/...`) son relativas al directorio de
  trabajo de PHP. Funcionan porque Apache sirve desde `public/`, pero conviene
  moverlas a un parámetro del contenedor de servicios.
- `Delivery` guarda fecha y hora en dos columnas separadas; un solo
  `datetime_immutable` sería más simple.
- `ROLE_FH` no tiene panel propio: `DashboardUsers::dashboard()` manda a
  cualquier rol que no sea staff a la vista de cliente. Hace falta resolverlo
  al implementar el aviso al transporte.
- `templates/dashboard/client.html.twig` muestra cifras fijas escritas a mano
  (incluida una tarjeta titulada «Otra cosa que ahorita no sé»), no datos
  reales.
- `assets/bootstrap.js` nunca se importa desde `assets/app.js`, así que Stimulus
  no arranca y el controlador `csrf_protection` queda inactivo.
