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
        $row = 1;
        $worksheet = $spreadsheet->getActiveSheet();
        $ret = [];

        $stopWord = $xlsStructure->getStopWord();
        $firstWord = substr( $worksheet->getCellByColumnAndRow( 1, $row )->getValue(), 0, strlen( $stopWord ) );

        while ( $firstWord != $stopWord) {
            $ret[] = [];
            $row++;
            $firstWord = substr( $worksheet->getCellByColumnAndRow( 1, $row )->getValue(), 0, strlen( $stopWord ) );
        }

        return $ret;
    }
}