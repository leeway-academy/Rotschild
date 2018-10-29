<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 10/27/18
 * Time: 9:12 PM
 */

namespace App\Tests;

use App\Entity\BankXLSStructure;
use App\Service\ExcelReportProcessor;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

class ExcelReportProcessorTest extends TestCase
{
    public function testGetBankSummaryTransactionsReturnsArray()
    {
        $reportProcessor = new ExcelReportProcessor();

        $transactions = $reportProcessor->getBankSummaryTransactions( new Spreadsheet(), new BankXLSStructure() );

        $this->assertTrue( is_array( $transactions ) );
    }

    public function testGetBankSummaryTransactionsUsesStopWordIfAvailable()
    {
        $reportProcessor = new ExcelReportProcessor();

        $xlsStructure = new BankXLSStructure();
        $xlsStructure
//            ->setAmountCol( 0 )
//            ->setConceptCol( 1 )
//            ->setDateCol( 2 )
//            ->setDateFormat( 'Y-m-d' )
//            ->setFirstRow( 1 )
            ->setStopWord( 'Stop' )
        ;

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue('DontStop' );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( 'Stop' );

        $transactions = $reportProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);

        $this->assertCount( 1, $transactions );
    }
}
