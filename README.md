# IAL Network — Gestor de despachos aduanales

Aplicación Symfony 7.2 para administrar el ciclo completo de una importación /
exportación: desde que el cliente da el aviso hasta que se cierra el expediente
con su cuenta de gastos.

## El flujo de negocio

| # | Etapa | Quién | Entidades | Estado |
|---|-------|-------|-----------|--------|
| 1 | Alerta de importación/exportación y carga de documentos | Cliente | `ImportRequest`, `ImportDocument`, `Container` | Implementado |
| 2 | Alta del pedimento y fases de la operación | Ejecutivo | `Operation` | Implementado |
| 3 | Aviso al transporte | Ejecutivo → Transportista | `Delivery`, `FreightHauler` | Implementado |
| 4 | Entrega de mercancía y devolución de vacío | Transportista | `EmptyReturn`, `ContainerYard` | Implementado |
| 5 | Cierre del expediente y cuenta de gastos | Ejecutivo | `InternInvoice` | Implementado |

`ImportRequest` es el expediente: todo lo demás cuelga de él.

## Estados del expediente

El recorrido depende de **dos** ejes: importación o exportación, y contenedor o
carga suelta. Son cuatro secuencias distintas, definidas en
[`App\Workflow\ImportRequestWorkflow`](src/Workflow/ImportRequestWorkflow.php).
No comparten los mismos pasos ni el mismo orden: en exportación, «Liberado en
terminal» va antes del pago cuando es contenedor y después de la modulación
cuando es carga suelta. Y **solo la importación contenerizada devuelve vacío**:
la carga suelta se desconsolida en el recinto, así que la agencia nunca toma
posesión del contenedor.

**Importación · Contenedor**
> Pendiente → Capturado → Revalidado → Pagado → Programado → Modulado → *(Inspección fuera de puerto)* → En tránsito → Entregado → **Vacío devuelto** → Finalizado

**Importación · Carga suelta**
> Pendiente → Capturado → **Desconsolidado** → Revalidado → Pagado → Programado → Modulado → *(Inspección fuera de puerto)* → En tránsito → Entregado → Finalizado

**Exportación · Contenedor**
> Pendiente → Capturado → Ingresado → Liberado en terminal → Pagado → Modulado → Finalizado

**Exportación · Carga suelta**
> Pendiente → Capturado → Ingresado → Pagado → Modulado → Liberado en terminal → Finalizado

*Inspección fuera de puerto* es un paso **opcional**: solo aplica a cierta
mercancía de importación. Cuando el expediente llega a Modulado, la pantalla
ofrece las dos salidas y el ejecutivo elige.

El expediente solo puede saltar al estado que su secuencia permite; el
controlador rechaza cualquier otro valor que llegue del formulario.

## Transporte

El ejecutivo avisa al transporte desde el expediente; el transportista confirma
salida y entrega desde `/dashboard/despachos`, y **son esas confirmaciones las
que mueven el estatus**, no un botón del ejecutivo.

Cuándo se puede avisar, y a qué estado lleva cada confirmación:

| | Se avisa estando en | Salida → | Entrega → |
|---|---|---|---|
| **Importación** | Modulado o Inspección | En tránsito | Entregado |
| **Exportación** | Capturado | *(no aplica)* | Ingresado |

En exportación el camión recoge en planta del cliente y lo lleva al recinto, así
que no hay trayecto propio que registrar: solo su llegada.

Un expediente puede llevar **varios despachos**. La carga suelta lleva uno; la
contenerizada, uno por camión, con un máximo de
`Delivery::MAX_CONTAINERS` (dos) contenedores cada uno. De ahí que el
expediente pase a «En tránsito» en cuanto **sale el primer** camión, pero solo
llegue a «Entregado» cuando **han llegado todos**.

## Devolución de vacíos

Solo aplica a la **importación contenerizada**, que es la única secuencia con el
estado «Vacío devuelto».

Lo registra el transportista desde su despacho, una vez confirmada la entrega:
por cada contenedor captura el patio, el tipo de devolución
([`EmptyReturnCatalog`](src/Workflow/EmptyReturnCatalog.php): Directa o
Recolección), la fecha, el folio del EIR y el EIR escaneado. El expediente pasa
a «Vacío devuelto» cuando **han vuelto todos** los contenedores.

El ejecutivo no puede marcar ese estado a mano: el controlador lo rechaza
mientras quede algún contenedor sin EIR, para que el expediente no cierre sin el
respaldo de los patios.

Los EIR se guardan en `public/uploads/eir/{idExpediente}/`. La columna
`eir_route` es nullable a propósito: el formulario exige el documento, pero el
esquema no debe impedir registrar la devolución si el escaneo llega después.

## Cuenta de gastos

El contador manda un ZIP con el PDF de la cuenta y su XML, mas los comprobantes
que correspondan, asi que el formulario acepta **tantos documentos como haga
falta**, cada uno con su concepto. Se guardan en
`public/uploads/gastos/{idExpediente}/`.

El expediente **no cierra sin cuenta de gastos**: el boton de «Finalizado» no
aparece y el servidor lo rechaza mientras no haya nada anexado. Una vez cerrado,
la cuenta de gastos queda inmutable: no se puede anexar ni eliminar.

El cliente puede abrir en solo lectura los expedientes de sus empresas afiliadas
para seguir el avance y descargar su cuenta de gastos. La comprobacion es por
afiliacion, no por rol: un cliente recibe 403 en el expediente de una empresa
con la que no esta asociado.

## Maniobras

`Operation` registra las maniobras del expediente. El catálogo habitual vive en
[`App\Workflow\OperationCatalog`](src/Workflow/OperationCatalog.php), pero **no
es una lista cerrada**: el formulario deja capturar una maniobra propia para los
casos fuera de lo común.

## Afiliaciones

Un cliente ve los expedientes, documentos y cuentas de gastos de las empresas a
las que está afiliado, así que la afiliación **la autoriza la agencia**. El
cliente la solicita desde «Empresas»; queda pendiente y no concede nada hasta
que un ejecutivo la aprueba en `/dashboard/afiliaciones`.

La excepción es registrar una empresa nueva: quien la da de alta queda afiliado
de inmediato, porque no hay datos ajenos que proteger.

## CSRF

Los formularios tradicionales llevan su `_token` en el cuerpo. Los endpoints que
se llaman con `fetch()` lo reciben en la cabecera `X-CSRF-Token`:
`baseDashboard.html.twig` imprime el token en un `<meta>` y `assets/app.js`
envuelve `fetch` una sola vez para adjuntarlo a **toda petición mutante del
mismo origen**. Las peticiones a otros dominios no lo llevan.

Si añades un endpoint JSON nuevo, valídalo con
[`AjaxCsrfTrait`](src/Controller/AjaxCsrfTrait.php); el lado del navegador ya
está cubierto.

## Idiomas

La portada es **una sola plantilla** (`templates/index.html.twig`) para español e
inglés. El texto vive en `translations/messages.es.yaml` y
`messages.en.yaml`, y el idioma lo fija la ruta:

| Ruta | Idioma |
|------|--------|
| `/` | español (`_locale: es`) |
| `/en` | inglés (`_locale: en`) |

Ambas apuntan a la misma acción de `Home`; `_locale` es un parámetro especial de
Symfony y con él `|trans` resuelve contra el catálogo que toca.

Para agregar un texto: la clave en los **dos** catálogos y `{{ 'clave'|trans }}`
en la plantilla. Si falta en uno, Symfony cae al otro en vez de imprimir la
clave cruda.

`default_locale` es `es`, que es el idioma del resto de la aplicación.

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

> **Cuidado:** `doctrine:fixtures:load` purga **todas** las tablas del modelo,
> incluida `user`. Las cuentas reales creadas con `app:user:create` desaparecen
> y hay que volver a darlas de alta después de cargar las fixtures.

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

- `APP_SECRET` y `backup.sql` siguen presentes en el historial de git aunque ya
  no estén en el árbol de trabajo. Las credenciales deben rotarse.
- Las rutas de subida (`uploads/...`) son relativas al directorio de trabajo de
  PHP. Funcionan porque Apache sirve desde `public/`, pero conviene moverlas a
  un parámetro del contenedor de servicios.
- Solo la cuenta de gastos valida la extensión de lo que se sube. Los documentos
  de empresa y de importación aceptan cualquier archivo; Apache ya impide
  ejecutarlos, pero les falta la lista blanca.
- `Delivery` guarda fecha y hora en dos columnas separadas; un solo
  `datetime_immutable` sería más simple.
- `templates/dashboard/client.html.twig` muestra cifras fijas escritas a mano
  (incluida una tarjeta titulada «Otra cosa que ahorita no sé»), no datos
  reales.
- `assets/bootstrap.js` nunca se importa desde `assets/app.js`, así que Stimulus
  no arranca y el controlador `csrf_protection` queda inactivo.
