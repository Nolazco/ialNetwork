<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        // La app opera para clientes en Mexico (SOIA, aduanas, horarios de
        // despacho) y no puede depender de que cada servidor tenga
        // date.timezone bien configurado — en produccion (hosting
        // compartido) viene en UTC por default, lo que corria "ahora mismo"
        // (moduladoAt, reconocimientoAt, etc.) 6 horas adelantado en los
        // correos. Fijarlo aqui, antes que nada mas arranque, aplica igual a
        // peticiones web y a comandos de consola (cron del SOIA incluido).
        date_default_timezone_set('America/Mexico_City');

        parent::__construct($environment, $debug);
    }
}
