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

class ExcelReportProcessor
{
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
}