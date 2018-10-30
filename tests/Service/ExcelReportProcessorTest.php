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
use PhpOffice\PhpSpreadsheet\Shared\Date;
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
                -300
            ],
            [
                new \DateTimeImmutable(),
                'An income',
                500
            ],
        ];
    }

    /**
     * @param \DateTimeImmutable $d
     * @param string $concept
     * @param string $amount
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider bankSummaryRowProvider
     */
    public function testGetBankSummaryTransactionsWillReturnArray( \DateTimeImmutable $d, string $concept, string $amount )
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(4, 1 )->setValue( Date::dateTimeToExcel( $d ) );
        $worksheet->getCellByColumnAndRow(2, 2 )->setValue( $concept );
        $worksheet->getCellByColumnAndRow(3, 2 )->setValue( $amount );

        $xlsStructure = new BankXLSStructure();
        $xlsStructure->setConceptCol( 2 );
        $xlsStructure->setAmountCol( 3 );
        $xlsStructure->setDateCol( 4 );

        $transactions = $reportsProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure );

        $this->assertTrue( is_array( $transactions ) );
    }

    /**
     * @param \DateTimeImmutable $d
     * @param string $concept
     * @param string $amount
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider bankSummaryRowProvider
     */
    public function testGetBankSummaryTransactionsWillStopWordIfAvailable(\DateTimeImmutable $d, string $concept, string $amount)
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $xlsStructure = new BankXLSStructure();
        $xlsStructure
            ->setDateCol( 1 )
            ->setAmountCol( 2 )
            ->setConceptCol( 3 )
            ->setStopWord( 'Stop' )
        ;

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue( Date::dateTimeToExcel( $d ) );
        $worksheet->getCellByColumnAndRow(2, 1 )->setValue( $amount );
        $worksheet->getCellByColumnAndRow(3, 1 )->setValue( $concept );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( 'Stop' );

        $transactions = $reportsProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);

        $this->assertCount( 1, $transactions );
    }

    /**
     * @param \DateTimeImmutable $d
     * @param string $concept
     * @param string $amount
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider bankSummaryRowProvider
     */
    public function testGetBankSummaryTransactionsWillStopOnBlankIfNoStopWordAvailable(\DateTimeImmutable $d, string $concept, string $amount)
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $xlsStructure = new BankXLSStructure();
        $xlsStructure
            ->setDateCol(1)
            ->setConceptCol(2)
            ->setAmountCol(3)
            ;

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue( Date::dateTimeToExcel($d) );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( Date::dateTimeToExcel($d) );
        $worksheet->getCellByColumnAndRow(1, 3 )->setValue( Date::dateTimeToExcel($d) );

        $transactions = $reportsProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure);

        $this->assertCount( 3, $transactions );
    }

    /**
     * @param \DateTimeImmutable $d
     * @param string $concept
     * @param string $amount
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider bankSummaryRowProvider
     */
    public function testGetBankSummaryTransactionsWillStartOnFirstRow(\DateTimeImmutable $d, string $concept, string $amount)
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $xlsStructure = new BankXLSStructure();
        $xlsStructure
            ->setDateCol(1)
            ->setFirstRow(2 )
            ;

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue( Date::dateTimeToExcel($d) );
        $worksheet->getCellByColumnAndRow(1, 2 )->setValue( Date::dateTimeToExcel($d) );

        $transactions = $reportsProcessor->getBankSummaryTransactions( $spreadsheet, $xlsStructure );

        $this->assertCount( 1, $transactions );
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @dataProvider bankSummaryRowProvider
     */
    public function testGetBankSummaryWillUseColumnInformationFromXlsStructure( \DateTimeImmutable $d, string $concept, string $amount )
    {
        $reportsProcessor = new ExcelReportsProcessor();

        $xlsStructure = new BankXLSStructure();
        $xlsStructure
            ->setDateCol( 1 )
            ->setAmountCol( 2 )
            ->setConceptCol( 3 )
        ;

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->insertNewRowBefore( 1, 2 );
        $worksheet->getCellByColumnAndRow(1, 1 )->setValue( Date::dateTimeToExcel( $d ) );
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