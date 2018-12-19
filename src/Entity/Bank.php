<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BancoRepository")
 * @ORM\Table(name="bank")
 */
class Bank
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nombre;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Movimiento", mappedBy="bank")
     * @ORM\OrderBy({"fecha"="ASC"})
     */
    private $movimientos;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\GastoFijo", mappedBy="bank")
     */
    private $gastosFijos;

    /**
     * @ORM\OneToMany(targetEntity="SaldoBancario", mappedBy="bank", orphanRemoval=true, indexBy="fecha")
     * @ORM\OrderBy({"fecha"="ASC"})
     */
    private $saldos;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $codigo;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\BankXLSStructure", mappedBy="bank")
     */
    private $xlsStructure = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ExtractoBancario", mappedBy="bank", orphanRemoval=true)
     */
    private $extractos;

    /**
     * @return mixed
     */
    public function getCodigo()
    {
        return $this->codigo;
    }

    /**
     * @param mixed $codigo
     * @return Bank
     */
    public function setCodigo($codigo)
    {
        $this->codigo = $codigo;
        return $this;
    }

    public function __construct()
    {
        $this->movimientos = new ArrayCollection();
        $this->gastosFijos = new ArrayCollection();
        $this->saldos = new ArrayCollection();
        $this->extractos = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;

        return $this;
    }

    /**
     * @return Collection|Movimiento[]
     */
    public function getMovimientos(): Collection
    {
        return $this->movimientos;
    }

    public function addMovimiento(Movimiento $movimiento): self
    {
        if (!$this->movimientos->contains($movimiento)) {
            $this->movimientos[] = $movimiento;
            $movimiento->setBank($this);
        }

        return $this;
    }

    public function removeMovimiento(Movimiento $movimiento): self
    {
        if ($this->movimientos->contains($movimiento)) {
            $this->movimientos->removeElement($movimiento);
            // set the owning side to null (unless already changed)
            if ($movimiento->getBank() === $this) {
                $movimiento->setBank(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->getNombre();
    }

    /**
     * @return Collection|GastoFijo[]
     */
    public function getGastosFijos(): Collection
    {
        return $this->gastosFijos;
    }

    public function addGastoFijo(GastoFijo $gastosFijo): self
    {
        if (!$this->gastosFijos->contains($gastosFijo)) {
            $this->gastosFijos[] = $gastosFijo;
            $gastosFijo->setBank($this);
        }

        return $this;
    }

    public function removeGastoFijo(GastoFijo $gastosFijo): self
    {
        if ($this->gastosFijos->contains($gastosFijo)) {
            $this->gastosFijos->removeElement($gastosFijo);
            // set the owning side to null (unless already changed)
            if ($gastosFijo->getBank() === $this) {
                $gastosFijo->setBank(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|SaldoBancario[]
     */
    public function getSaldos(): Collection
    {
        return $this->saldos;
    }

    public function addSaldo(SaldoBancario $saldo): self
    {
        if (!$this->saldos->contains($saldo)) {
            $this->saldos[ $saldo->getFecha()->format('Y-m-d') ] = $saldo;
            $saldo->setBank($this);
        }

        return $this;
    }

    public function removeSaldo(SaldoBancario $saldo): self
    {
        if ($this->saldos->contains($saldo)) {
            $this->saldos->removeElement($saldo);
            // set the owning side to null (unless already changed)
            if ($saldo->getBank() === $this) {
                $saldo->setBank(null);
            }
        }

        return $this;
    }

    /**
     * @param \DateTimeInterface|null $date
     * @return SaldoBancario
     */
    public function getBalance(\DateTimeImmutable $date = null ): ?SaldoBancario
    {
        $balances = $this->getSaldos();

        if ( $date ) {
            $oneDay = new \DateInterval('P1D');

            while ( !$balances->isEmpty() && !$balances->containsKey( $date->format('Y-m-d') ) && $balances->first()->getFecha()->diff( $date )->days > 0 ) {
                $date = $date->sub( $oneDay );
            }

            return $balances->containsKey( $date->format('Y-m-d') ) ? $balances->get( $date->format('Y-m-d') ) : null;
        } else {

            return !$balances->isEmpty() ? $balances->last() : null;
        }
    }

    /**
     * @param \DateTimeInterface $fecha
     * @return SaldoBancario
     */
    public function getProjectedBalance(\DateTimeInterface $fecha): SaldoBancario
    {
        $unDia = new \DateInterval('P1D');
        $fechaInicial = $fecha->sub( new \DateInterval('P1D') );
        $saldos = $this->getSaldos();

        $saldoActual = new SaldoBancario();
        $saldoActual->setValor(0);
        $saldoActual->setBank( $this );

        $primerSaldo = $saldos->first();
        if ( $primerSaldo ) {
            $primeraFecha = $primerSaldo->getFecha()->format('Y-m-d');
            while ( $fechaInicial->format('Y-m-d') != $primeraFecha && !$saldos->containsKey( $fechaInicial->format('Y-m-d') ) ) {
                $fechaInicial = $fechaInicial->sub($unDia);
            }
            $saldoActual = clone $saldos->get( $fechaInicial->format('Y-m-d') );
        }

        foreach ($this->getTransactionsBetween( $fechaInicial, $fecha, false ) as $movimiento) {
            $saldoActual->setValor( $saldoActual->getValor() + $movimiento->getImporte() );
        }

        $saldoActual->setFecha( $fecha );

        return $saldoActual;
    }

    /**
     * @param \DateTimeInterface $fechaInicio
     * @param \DateTimeInterface $fechaFin
     * @return Collection
     */
    public function getTransactionsBetween(\DateTimeInterface $fechaInicio, \DateTimeInterface $fechaFin, bool $concretados = null ) : Collection
    {
        $criteria = Criteria::create()
            ->andWhere(
                Criteria::expr()
                    ->gte('fecha', $fechaInicio)
            )
            ->andWhere(
                Criteria::expr()
                    ->lt('fecha', $fechaFin)
            );

        return $this
            ->getMovimientos()
            ->matching( $criteria )
            ->filter( function( Movimiento $m ) use ( $concretados ) {
                if ( $concretados === true ) {

                    return $m->getConcretado();
                } elseif ( $concretados === false ) {

                    return !$m->getConcretado();
                } else {

                    return true;
                }
            });
    }

    /**
     * @return BankXLSStructure
     */
    public function getXLSStructure(): ?BankXLSStructure
    {
        return $this->xlsStructure;
    }

    /**
     * @param BankXLSStructure|null $xlsStructure
     * @return Bank
     */
    public function setXLSStructure(BankXLSStructure $xlsStructure = null )
    {
        $this->xlsStructure = $xlsStructure;

        return $this;
    }

    /**
     * @param int|null $limit
     *
     * @return Collection
     */
    public function getDebitosProyectados( int $limit = null ): Collection
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->isNull('renglonExtracto'))
            ->andWhere(Criteria::expr()->lt('importe', 0))
            ->orderBy(['fecha' => 'ASC'])
        ;

        if ( $limit ) {
            $criteria->setMaxResults( $limit );
        }

        return $this->movimientos->matching( $criteria );
    }

    /**
     * @param int|null $limit
     * @return Collection
     */
    public function getCreditosProyectados( int $limit = null ) : Collection
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->isNull('renglonExtracto'))
            ->andWhere(Criteria::expr()->gt('importe', 0))
            ->orderBy(['fecha' => 'ASC'])
        ;

        if ( $limit ) {
            $criteria->setMaxResults( $limit );
        }

        return $this->movimientos->matching( $criteria );
    }

    /**
     * @return Collection|ExtractoBancario[]
     */
    public function getExtractos(): Collection
    {
        return $this->extractos;
    }

    public function addExtracto(ExtractoBancario $extracto): self
    {
        if (!$this->extractos->contains($extracto)) {
            $this->extractos[] = $extracto;
            $extracto->setBank($this);
        }

        return $this;
    }

    public function removeExtracto(ExtractoBancario $extracto): self
    {
        if ($this->extractos->contains($extracto)) {
            $this->extractos->removeElement($extracto);
            // set the owning side to null (unless already changed)
            if ($extracto->getBank() === $this) {
                $extracto->setBank(null);
            }
        }

        return $this;
    }
}
