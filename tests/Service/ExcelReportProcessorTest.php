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
    public function rowProvider()
    {
        return [
            [
                new \DateTimeImmutable(),
                'An expense',
                -300,
                'd/M/y',
            ],
            [
                new \DateTimeImmutable(),
                'An income',
                500,
                'Y-m-d',
            ],
        ];
    }
    public function testGetBankSummaryTransactionsWillReturnArray()
    {
        $reportProcessor = new ExcelReportProcessor();

        $transactions = $reportProcessor->getBankSummaryTransactions( new Spreadsheet(), new BankXLSStructure() );

        $this->assertTrue( is_array( $transactions ) );
    }

    public function testGetBankSummaryTransactionsWillStopWordIfAvailable()
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
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue( (new \DateTimeImmutable())->format('Y/m/d') );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( 'Stop' );

        $transactions = $reportProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);

        $this->assertCount( 1, $transactions );
    }

    public function testGetBankSummaryTransactionsWillStopOnBlankIfNoStopWordAvailable()
    {
        $reportProcessor = new ExcelReportProcessor();

        $xlsStructure = new BankXLSStructure();

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue((new \DateTimeImmutable())->format('Y/m/d') );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( (new \DateTimeImmutable())->format('Y/m/d') );
        $worksheet->getCellByColumnAndRow(1, 3 )->setValue( (new \DateTimeImmutable())->format('Y/m/d') );

        $transactions = $reportProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);

        $this->assertCount( 3, $transactions );
    }

    public function testGetBankSummaryTransactionsWillStartOnFirstRow()
    {
        $reportProcessor = new ExcelReportProcessor();

        $xlsStructure = new BankXLSStructure();
        $xlsStructure->setFirstRow(2 );

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue((new \DateTimeImmutable())->format('Y/m/d') );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( (new \DateTimeImmutable())->format('Y/m/d') );

        $transactions = $reportProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);

        $this->assertCount( 1, $transactions );
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider rowProvider
     */
    public function testGetBankSummaryWillUseColumnInformationFromXlsStructure( \DateTimeImmutable $d, string $concept, float $amount, $dateFormat )
    {
        $dateFormat = 'Y/m/d';
        $reportProcessor = new ExcelReportProcessor();

        $xlsStructure = new BankXLSStructure();
        $xlsStructure
            ->setDateFormat($dateFormat)
            ->setDateCol( 1 )
            ->setAmountCol( 2 )
            ->setConceptCol( 3 )
        ;

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue($d->format($dateFormat) );
        $worksheet->getCellByColumnAndRow(2, 1 )->setValue($amount );
        $worksheet->getCellByColumnAndRow(3, 1 )->setValue($concept );

        $transactions = $reportProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);
        $transaction = current($transactions);

        $this->assertEquals( $d->format('d-m-Y'), $transaction['date']->format('d-m-Y') );
        $this->assertEquals( $amount, $transaction['amount'] );
        $this->assertEquals( $concept, $transaction['concept'] );
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider rowProvider
     */
    public function testGetBankSummaryWillUseDatFormatFromXlsStructure( \DateTimeImmutable $d, string $concept, float $amount, $dateFormat )
    {
        $reportProcessor = new ExcelReportProcessor();

        $xlsStructure = new BankXLSStructure();
        $xlsStructure
            ->setDateFormat($dateFormat)
            ->setDateCol( 1 )
            ->setAmountCol( 2 )
            ->setConceptCol( 3 )
        ;

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue($d->format($dateFormat) );
        $worksheet->getCellByColumnAndRow(2, 1 )->setValue($amount );
        $worksheet->getCellByColumnAndRow(3, 1 )->setValue($concept );

        $transactions = $reportProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);
        $transaction = current($transactions);

        $this->assertEquals( $d->format('d-m-Y'), $transaction['date']->format('d-m-Y') );
        $this->assertEquals( $amount, $transaction['amount'] );
        $this->assertEquals( $concept, $transaction['concept'] );
    }
}
