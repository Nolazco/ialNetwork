<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validacion de CSRF para los endpoints que se llaman con fetch().
 *
 * Los formularios normales mandan su token en el cuerpo, pero estos endpoints
 * reciben JSON o FormData desde JavaScript, asi que el token viaja en la
 * cabecera X-CSRF-Token. El valor sale del <meta name="csrf-token"> que
 * baseDashboard.html.twig imprime en cada pagina.
 *
 * Sin esto bastaba con que un cliente autenticado visitara una pagina ajena para
 * que esa pagina disparara peticiones a su nombre: cambiar roles, borrar
 * documentos o editar empresas.
 */
trait AjaxCsrfTrait
{
    private const AJAX_TOKEN_ID = 'ajax';

    /**
     * Devuelve una respuesta de error cuando el token no es valido, o null
     * cuando la peticion puede continuar.
     */
    private function rejectInvalidAjaxCsrf(Request $r): ?JsonResponse
    {
        $token = $r->headers->get('X-CSRF-Token') ?? $r->request->get('_token');

        if ($this->isCsrfTokenValid(self::AJAX_TOKEN_ID, $token)) {
            return null;
        }

        return new JsonResponse([
            'success' => false,
            'error' => 'Token de seguridad inválido. Recarga la página e intenta de nuevo.',
        ], 419);
    }
}
