<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Los documentos subidos (RequiredDocument::route y equivalentes) guardan
 * una ruta relativa como 'uploads/requisitos/40/foo.pdf'. Antes se resolvia
 * sola porque el proceso corria con cwd en public/ y Apache servia esa
 * carpeta como estatica — exactamente el hueco de seguridad que se esta
 * cerrando (cualquiera con la URL podia descargar sin login). Ahora esa
 * misma cadena relativa se resuelve contra var/ (fuera del webroot), y cada
 * documento se sirve por una ruta de Symfony que si revisa quien lo pide.
 */
final class UploadPath
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function resolve(string $route): string
    {
        return $this->projectDir.'/var/'.$route;
    }
}
