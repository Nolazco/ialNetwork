<?php

namespace App\Workflow;

/**
 * Extensiones que se aceptan en cualquier documento que suba un ejecutivo o
 * un cliente (cuenta de gastos, documentos del aviso, documentos del
 * ejecutivo, clasificaciones, documentos fiscales de la empresa...):
 * formatos comunes de oficina mas zip, para expedientes completos. Nada
 * ejecutable.
 */
final class AllowedFileExtensions
{
    public const LIST = ['pdf', 'xml', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'docx', 'csv'];
}
