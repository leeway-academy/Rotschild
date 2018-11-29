<?php
/**
 * Created by PhpStorm.
 * User: mauro
 * Date: 10/4/18
 * Time: 8:13 AM
 */
declare(strict_types=1);

namespace App\Tests\Entity;
use App\Entity\Movimiento;
use App\Entity\SaldoBancario;
use PHPUnit\Framework\TestCase;
use App\Entity\Bank;

class BankTest extends TestCase
{
    public function testGetSaldoNoFuncionaParaSaldosNoCargados()
    {
        $banco = new Bank();

        $this->assertNull( $banco->getSaldo( new \DateTimeImmutable() ), 'El saldo no es null' );
    }

    public function testGetSaldoDevuelveUltimoSaldoSiNoSeAclaraFecha()
    {
        $banco = new Bank();
        $saldo1 = new SaldoBancario();

        $saldo1->setBank($banco);
        $saldo1->setValor(10);
        $saldo1->setFecha(new \DateTimeImmutable('Yesterday') );
        $banco->addSaldo( $saldo1 );

        $saldo2 = clone $saldo1;
        $saldo2->setFecha( new \DateTimeImmutable() );
        $banco->addSaldo($saldo2);

        $this->assertSame( $saldo2, $banco->getSaldo(), 'El saldo devuelto no es el ultimo' );
    }

    public function testGetSaldoProyectadoUsaSaldoAnteriorYMovimientosHastaLaFecha()
    {
        $fecha1 = new \DateTimeImmutable('today -5 days');

        $saldo1 = new SaldoBancario();
        $saldo1->setFecha($fecha1);
        $saldo1->setValor( 1000 );

        $banco = new Bank();
        $banco->addSaldo( $saldo1 );

        $movimiento1 = new Movimiento();
        $movimiento1->setFecha( $fecha1 );
        $movimiento1->setConcepto('Un gasto');
        $movimiento1->setImporte( -500 );
        $banco->addMovimiento( $movimiento1 );

        $movimiento2 = new Movimiento();
        $movimiento2->setFecha( $fecha1->add( new \DateInterval('P2D') ) );
        $movimiento2->setConcepto('Un ingreso');
        $movimiento2->setImporte( 300 );
        $banco->addMovimiento( $movimiento2 );

        $fechaActual = new \DateTimeImmutable('yesterday');

        $this->assertEquals( 800, $banco->getSaldoProyectado( $fechaActual )->getValor(), 'El saldo proyectado no coincide' );

        $movimiento3 = new Movimiento();
        $movimiento3->setFecha( $fecha1->add(new \DateInterval('P3D') ) );
        $movimiento3->setConcepto('Otro gasto');
        $movimiento3->setImporte( -200 );

        $banco->addMovimiento( $movimiento3 );

        $this->assertEquals( 600, $banco->getSaldoProyectado( $fechaActual )->getValor(), 'El saldo proyectado no coincide' );
    }
}
