<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 10/27/18
 * Time: 8:41 PM
 */

namespace App\Service;

use App\Entity\BankXLSStructure;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelReportsProcessor
{
    private $config = [];

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * ExcelReportsProcessor constructor.
     * @param array $config
     */
    public function __construct( array $config = [] )
    {
        $this->setConfig( $config );
    }

    /**
     * @param Spreadsheet $spreadsheet
     * @param BankXLSStructure $xlsStructure
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function getBankSummaryTransactions( Spreadsheet $spreadsheet, BankXLSStructure $xlsStructure ): array
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $ret = [];

        $stopWord = $xlsStructure->getStopWord();
        $row = 1;

        $firstValue = $worksheet->getCellByColumnAndRow( 1, $row )->getValue();
        $firstWord = !empty($stopWord) ? substr( $firstValue, 0, strlen( $stopWord ) ) : $firstValue;
        $firstHeader = $xlsStructure->getFirstHeader();

        while ( !empty( $firstHeader ) && $firstWord != $firstHeader ) {
            $row++;
            $firstValue = $worksheet->getCellByColumnAndRow( 1, $row )->getValue();
            $firstWord = !empty($stopWord) ? substr( $firstValue, 0, strlen( $stopWord ) ) : $firstValue;
        }

        $row += $xlsStructure->getFirstRow();
        while ( ( !empty($stopWord) && $firstWord != $stopWord ) || (empty($stopWord) && !empty($firstWord) ) ) {
            $dateValue = $worksheet->getCellByColumnAndRow( $xlsStructure->getDateCol(), $row )->getValue();
            $ret[] = [
                'date' => is_numeric( $dateValue ) ? Date::excelToDateTimeObject( $dateValue ) : \DateTimeImmutable::createFromFormat( $xlsStructure->getDateFormat(), $dateValue ),
                'concept' => $worksheet->getCellByColumnAndRow( $xlsStructure->getConceptCol(), $row )->getValue(),
                'amount' => $worksheet->getCellByColumnAndRow( $xlsStructure->getAmountCol(), $row )->getValue(),
            ];
            $row++;
            $firstValue = $worksheet->getCellByColumnAndRow( 1, $row )->getValue();
            $firstWord = !empty($stopWord) ? substr( $firstValue, 0, strlen( $stopWord ) ) : $firstValue;
        }

        return $ret;
    }

    /**
     * @param Spreadsheet $spreadsheet
     * @return array
     */
    public function getIssuedChecks( Spreadsheet $spreadsheet ) : array
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $ret = [];

        // Ignore headers row
        $row = 2;
        $firstValue = $worksheet->getCellByColumnAndRow( 1, $row )->getValue();

        while ( !empty($firstValue) ) {
            $ret[] = [
                'date' => \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject( $worksheet->getCellByColumnAndRow( 4, $row )->getValue() ),
                'amount' => $worksheet->getCellByColumnAndRow( 8, $row )->getValue(),
                'bankCode' => $worksheet->getCellByColumnAndRow( 7, $row )->getValue(),
                'checkNumber' => $worksheet->getCellByColumnAndRow( 2, $row )->getValue(),
            ];
            $row++;
            $firstValue = $worksheet->getCellByColumnAndRow( 1, $row )->getValue();
        }

        return $ret;
    }

    /**
     * @param Spreadsheet $spreadsheet
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function getAppliedChecks( Spreadsheet $spreadsheet ) : array
    {
        $worksheet = $spreadsheet->getActiveSheet();
        $ret = [];

        // Ignore headers row
        $row = 2;
        $firstValue = $worksheet->getCellByColumnAndRow( 1, $row )->getValue();

        while ( !empty($firstValue) ) {
            $ret[] = [
                'date' => \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject( $worksheet->getCellByColumnAndRow( 3, $row )->getValue() ),
                'amount' => $worksheet->getCellByColumnAndRow( 12, $row )->getValue(),
                'number' => $worksheet->getCellByColumnAndRow( 2, $row )->getValue(),
                'type' => $worksheet->getCellByColumnAndRow( 8, $row ),
                'sourceBank' => $worksheet->getCellByColumnAndRow( 4, $row )->getValue(),
                'issuer' => $worksheet->getCellByColumnAndRow( 9, $row )->getValue(),
                'destination' => $worksheet->getCellByColumnAndRow( 11, $row )->getValue(),
            ];
            $row++;
            $firstValue = $worksheet->getCellByColumnAndRow( 1, $row )->getValue();
        }

        return $ret;
    }
}