<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 10/27/18
 * Time: 9:12 PM
 */

namespace App\Tests;

use App\Entity\BankXLSStructure;
use App\Service\ExcelReportsProcessor;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

class ExcelReportProcessorTest extends TestCase
{
    public function bankSummaryRowProvider()
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
        $reportsProcessor = new ExcelReportsProcessor();

        $transactions = $reportsProcessor->getBankSummaryTransactions( new Spreadsheet(), new BankXLSStructure() );

        $this->assertTrue( is_array( $transactions ) );
    }

    public function testGetBankSummaryTransactionsWillStopWordIfAvailable()
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $xlsStructure = new BankXLSStructure();
        $xlsStructure
            ->setStopWord( 'Stop' )
        ;

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue( (new \DateTimeImmutable())->format('Y/m/d') );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( 'Stop' );

        $transactions = $reportsProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);

        $this->assertCount( 1, $transactions );
    }

    public function testGetBankSummaryTransactionsWillStopOnBlankIfNoStopWordAvailable()
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $xlsStructure = new BankXLSStructure();

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue((new \DateTimeImmutable())->format('Y/m/d') );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( (new \DateTimeImmutable())->format('Y/m/d') );
        $worksheet->getCellByColumnAndRow(1, 3 )->setValue( (new \DateTimeImmutable())->format('Y/m/d') );

        $transactions = $reportsProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);

        $this->assertCount( 3, $transactions );
    }

    public function testGetBankSummaryTransactionsWillStartOnFirstRow()
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $xlsStructure = new BankXLSStructure();
        $xlsStructure->setFirstRow(2 );

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue((new \DateTimeImmutable())->format('Y/m/d') );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( (new \DateTimeImmutable())->format('Y/m/d') );

        $transactions = $reportsProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);

        $this->assertCount( 1, $transactions );
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider bankSummaryRowProvider
     */
    public function testGetBankSummaryWillUseColumnInformationFromXlsStructure( \DateTimeImmutable $d, string $concept, float $amount, $dateFormat )
    {
        $dateFormat = 'Y/m/d';
        $reportsProcessor = new ExcelReportsProcessor();

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

        $transactions = $reportsProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);
        $transaction = current($transactions);

        $this->assertEquals( $d->format('d-m-Y'), $transaction['date']->format('d-m-Y') );
        $this->assertEquals( $amount, $transaction['amount'] );
        $this->assertEquals( $concept, $transaction['concept'] );
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider bankSummaryRowProvider
     */
    public function testGetBankSummaryWillUseDatFormatFromXlsStructure( \DateTimeImmutable $d, string $concept, float $amount, $dateFormat )
    {
        $reportsProcessor = new ExcelReportsProcessor();

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

        $transactions = $reportsProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);
        $transaction = current($transactions);

        $this->assertEquals( $d->format('d-m-Y'), $transaction['date']->format('d-m-Y') );
        $this->assertEquals( $amount, $transaction['amount'] );
        $this->assertEquals( $concept, $transaction['concept'] );
    }

    public function issuedChecksRowProvider(): array
    {
        return [
            [
                new \DateTimeImmutable(),
                '81906522',
                '072',
                '11,473.51',
            ],
            [
                new \DateTimeImmutable('yesterday'),
                '81906523',
                '072',
                '59,048.00',
            ],
            [
                new \DateTimeImmutable('tomorrow'),
                '81906524',
                '072',
                '4,000.00',
            ],
            [
                new \DateTimeImmutable('first day of last month'),
                '6190446',
                '011',
                '30,446.82',
            ],
            [
                new \DateTimeImmutable('last day of last month'),
                '6190447',
                '011',
                '22,082.11',
            ],
        ];
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider issuedChecksRowProvider
     */
    public function testGetIssuedChecksWillReturnArray( \DateTimeImmutable $d, string $checkNbr, string $bankCode, string $amount )
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 4 );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( 'Non empty' );
        $worksheet->getCellByColumnAndRow(4, 2 )->setValue( \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel($d) );
        $worksheet->getCellByColumnAndRow(2, 2 )->setValue( $checkNbr );
        $worksheet->getCellByColumnAndRow(7, 2 )->setValue( $bankCode );
        $worksheet->getCellByColumnAndRow(8, 2 )->setValue( $amount );

        $checks = $reportsProcessor->getIssuedChecks( $spreadsheet );

        $this->assertTrue( is_array( $checks ), 'Result is not an array' );
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider issuedChecksRowProvider
     */
    public function testGetIssuedChecksWillStopOnBlankFirstColumn( \DateTimeImmutable $d, string $checkNbr, string $bankCode, string $amount )
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $numRows = rand( 1, 10 );
        $worksheet->insertNewRowBefore( 1, $numRows );

        for ( $i = 0; $i < $numRows; $i++ ) {
            $worksheet->getCellByColumnAndRow(1, $i + 2 )->setValue( 'Non empty' );
            $worksheet->getCellByColumnAndRow(4, $i + 2 )->setValue( \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel($d) );
            $worksheet->getCellByColumnAndRow(2, $i + 2 )->setValue( $checkNbr );
            $worksheet->getCellByColumnAndRow(7, $i + 2 )->setValue( $bankCode );
            $worksheet->getCellByColumnAndRow(8, $i + 2 )->setValue( $amount );
        }

        $checks = $reportsProcessor->getIssuedChecks( $spreadsheet );

        $this->assertCount( $numRows, $checks );
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider issuedChecksRowProvider
     */
    public function testGetIssuedChecksWillFetchDataCorrectly( \DateTimeImmutable $d, string $checkNbr, string $bankCode, string $amount )
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $numRows = rand( 1, 10 );
        $worksheet->insertNewRowBefore( 1, $numRows );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( 'Non empty' );
        $worksheet->getCellByColumnAndRow(4, 2 )->setValue( \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel($d) );
        $worksheet->getCellByColumnAndRow(2, 2 )->setValue( $checkNbr );
        $worksheet->getCellByColumnAndRow(7, 2 )->setValue( $bankCode );
        $worksheet->getCellByColumnAndRow(8, 2 )->setValue( $amount );

        $checks = $reportsProcessor->getIssuedChecks( $spreadsheet );

        foreach ( $checks as $check ) {
            $this->assertEquals( $check['date']->format('d-m-Y'), $d->format('d-m-Y') );
            $this->assertEquals( $check['checkNumber'], $checkNbr );
            $this->assertEquals( $check['amount'], $amount );
            $this->assertEquals( $check['bankCode'], $bankCode );
        }
    }
}