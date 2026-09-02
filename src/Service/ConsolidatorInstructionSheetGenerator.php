<?php

namespace App\Service;

use App\Entity\ConsolidatorInstruction;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Llena el formato de "Orden de Remisión / Bill of Lading" que se manda al
 * consolidador de carga (XCF). La plantilla (resources/templates/xcf-orden-remision.xlsx)
 * es casi enteramente fija — remitente, agente aduanal, texto legal, cargos
 * accesoriales — así que en vez de reconstruir el documento desde cero, se
 * abre la plantilla real y solo se escriben las celdas que de verdad cambian
 * por envío; todo lo demás queda intacto.
 */
final class ConsolidatorInstructionSheetGenerator
{
    private const TEMPLATE_PATH = __DIR__.'/../../resources/templates/xcf-orden-remision.xlsx';

    /**
     * Código de aduana (2 dígitos) para el número de pedimento completo —
     * unica aduana que maneja la agencia hoy (mismo supuesto que
     * SoiaClient::ADUANA_MANZANILLO, pero ese es el id del combo del portal
     * SOIA, no el código real de 2 dígitos que lleva el pedimento).
     */
    private const ADUANA_MANZANILLO = '16';

    public function __construct(
        #[Autowire(env: 'SOIA_PATENTE')]
        private readonly string $patente,
    ) {
    }

    public function generate(ConsolidatorInstruction $instruction): string
    {
        $import = $instruction->getReference();
        // Null significa domicilio fiscal de la empresa: Company tiene los
        // mismos campos de domicilio/contacto que DeliveryPoint a proposito.
        $destinatario = $instruction->getDeliveryPoint() ?? $import->getIdCompany();

        $spreadsheet = IOFactory::load(self::TEMPLATE_PATH);
        $sheet = $spreadsheet->getSheetByName('Formato BL') ?? $spreadsheet->getActiveSheet();

        // Pedimento completo: año (2 dígitos, el actual — el numero guardado
        // en importNumber no lo trae, igual que en SoiaClient) + aduana +
        // patente + numero de pedimento; y fraccion arancelaria.
        $anio = (new \DateTimeImmutable())->format('y');
        $sheet->setCellValue('C6', sprintf('%s %s %s %s', $anio, self::ADUANA_MANZANILLO, $this->patente, $import->getImportNumber()));
        $sheet->setCellValue('C8', (string) $import->getTariffFraction());

        // Destinatario.
        $sheet->setCellValue('F11', (string) $destinatario->getName());
        $sheet->setCellValue('I11', (string) $destinatario->getRfc());
        $sheet->setCellValue('F13', (string) $destinatario->getStreet());
        $sheet->setCellValue('F15', (string) $destinatario->getExtNumber());
        $sheet->setCellValue('G15', (string) $destinatario->getIntNumber());
        $sheet->setCellValue('H15', (string) $destinatario->getNeighborhood());
        $sheet->setCellValue('F17', (string) $destinatario->getLocality());
        $sheet->setCellValue('H17', (string) $destinatario->getMunicipality());
        $sheet->setCellValue('F19', (string) $destinatario->getState());
        $sheet->setCellValue('H19', (string) $destinatario->getCountry());
        $sheet->setCellValue('I19', (string) $destinatario->getZipCode());
        $sheet->setCellValue('F21', (string) $destinatario->getContactName());
        $sheet->setCellValue('H21', (string) $destinatario->getContactPhone());
        $sheet->setCellValue('I21', (string) $destinatario->getContactEmail());

        // Facturador: siempre los datos de la empresa del cliente.
        $company = $import->getIdCompany();
        $sheet->setCellValue('A39', (string) $company->getName());
        $sheet->setCellValue('D39', (string) $company->getRfc());
        $sheet->setCellValue('A41', (string) $company->getStreet());
        $sheet->setCellValue('A43', (string) $company->getExtNumber());
        $sheet->setCellValue('B43', (string) $company->getIntNumber());
        $sheet->setCellValue('C43', (string) $company->getNeighborhood());
        $sheet->setCellValue('A45', (string) $company->getLocality());
        $sheet->setCellValue('C45', (string) $company->getMunicipality());
        $sheet->setCellValue('A47', (string) $company->getState());
        $sheet->setCellValue('C47', (string) $company->getCountry());
        $sheet->setCellValue('D47', (string) $company->getZipCode());
        $sheet->setCellValue('A49', (string) $company->getContactName());
        $sheet->setCellValue('C49', (string) $company->getContactPhone());
        $sheet->setCellValue('D49', (string) $company->getContactEmail());

        // Cobrar a: si el cliente paga directo, se llena con lo mismo del
        // facturador; si no, se deja el valor de la plantilla (la agencia).
        if ($instruction->isBilledToClient()) {
            $sheet->setCellValue('F39', (string) $company->getName());
            $sheet->setCellValue('I39', (string) $company->getRfc());
            $sheet->setCellValue('F41', (string) $company->getStreet());
            $sheet->setCellValue('F43', (string) $company->getExtNumber());
            $sheet->setCellValue('G43', (string) $company->getIntNumber());
            $sheet->setCellValue('H43', (string) $company->getNeighborhood());
            $sheet->setCellValue('F45', (string) $company->getLocality());
            $sheet->setCellValue('H45', (string) $company->getMunicipality());
            $sheet->setCellValue('F47', (string) $company->getState());
            $sheet->setCellValue('H47', (string) $company->getCountry());
            $sheet->setCellValue('I47', (string) $company->getZipCode());
            $sheet->setCellValue('F49', (string) $company->getContactName());
            $sheet->setCellValue('H49', (string) $company->getContactPhone());
        }

        // Mercancia.
        $sheet->setCellValue('A60', $instruction->getClaveSat());
        $sheet->setCellValue('B60', $instruction->getDescripcion());
        $sheet->setCellValue('C60', $instruction->getQuantity());
        $sheet->setCellValue('D60', $instruction->getClaveUnidad());
        $sheet->setCellValue('E60', $instruction->getUnidad());
        $sheet->setCellValue('I60', $instruction->getWeightKg());
        $sheet->setCellValue('J60', $instruction->isEstibable() ? 'SI' : 'NO');
        $sheet->setCellValue('H83', sprintf(' %d %s', $instruction->getQuantity(), $instruction->getUnidad()));

        // Referencia de la agencia.
        $sheet->setCellValue('G87', (string) $import->getAgencyReference());

        return $this->save($spreadsheet);
    }

    private function save(Spreadsheet $spreadsheet): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'xcf-');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tmpPath);
        $bytes = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $bytes;
    }
}
