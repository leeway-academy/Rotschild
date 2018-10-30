<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 10/27/18
 * Time: 8:41 PM
 */

namespace App\Service;

use App\Entity\BankXLSStructure;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelReportsProcessor
{
    /**
     * @param Spreadsheet $spreadsheet
     * @param BankXLSStructure $xlsStructure
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function getBankSummaryTransactions( Spreadsheet $spreadsheet, BankXLSStructure $xlsStructure ): array
    {
        $row = $xlsStructure->getFirstRow();
        $worksheet = $spreadsheet->getActiveSheet();
        $ret = [];

        $stopWord = $xlsStructure->getStopWord();
        $firstValue = $worksheet->getCellByColumnAndRow( 1, $row )->getValue();
        $firstWord = !empty($stopWord) ? substr( $firstValue, 0, strlen( $stopWord ) ) : $firstValue;

        while ( ( !empty($stopWord) && $firstWord != $stopWord ) || (empty($stopWord) && !empty($firstWord) ) ) {
            $ret[] = [
                'date' => \DateTime::createFromFormat( $xlsStructure->getDateFormat(), $worksheet->getCellByColumnAndRow( $xlsStructure->getDateCol(), $row ) ),
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
}