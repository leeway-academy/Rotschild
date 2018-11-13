<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BancoRepository")
 */
class Banco
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
     * @ORM\OneToMany(targetEntity="App\Entity\Movimiento", mappedBy="banco")
     * @ORM\OrderBy({"fecha"="ASC"})
     */
    private $movimientos;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\GastoFijo", mappedBy="banco")
     */
    private $gastosFijos;

    /**
     * @ORM\OneToMany(targetEntity="SaldoBancario", mappedBy="banco", orphanRemoval=true, indexBy="fecha")
     * @ORM\OrderBy({"fecha"="ASC"})
     */
    private $saldos;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $codigo;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\BankXLSStructure", mappedBy="banco")
     */
    private $xlsStructure = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ExtractoBancario", mappedBy="banco", orphanRemoval=true)
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
     * @return Banco
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
            $movimiento->setBanco($this);
        }

        return $this;
    }

    public function removeMovimiento(Movimiento $movimiento): self
    {
        if ($this->movimientos->contains($movimiento)) {
            $this->movimientos->removeElement($movimiento);
            // set the owning side to null (unless already changed)
            if ($movimiento->getBanco() === $this) {
                $movimiento->setBanco(null);
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
            $gastosFijo->setBanco($this);
        }

        return $this;
    }

    public function removeGastoFijo(GastoFijo $gastosFijo): self
    {
        if ($this->gastosFijos->contains($gastosFijo)) {
            $this->gastosFijos->removeElement($gastosFijo);
            // set the owning side to null (unless already changed)
            if ($gastosFijo->getBanco() === $this) {
                $gastosFijo->setBanco(null);
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
            $saldo->setBanco($this);
        }

        return $this;
    }

    public function removeSaldo(SaldoBancario $saldo): self
    {
        if ($this->saldos->contains($saldo)) {
            $this->saldos->removeElement($saldo);
            // set the owning side to null (unless already changed)
            if ($saldo->getBanco() === $this) {
                $saldo->setBanco(null);
            }
        }

        return $this;
    }

    /**
     * @param \DateTimeInterface|null $fecha
     * @return SaldoBancario
     */
    public function getSaldo( \DateTimeInterface $fecha = null ): ?SaldoBancario
    {
        $saldos = $this->getSaldos();
        if ( empty($fecha) ) {

            return !$saldos->isEmpty() ? $saldos->last() : null;
        } else {

            return $saldos->containsKey( $fecha->format('Y-m-d') ) ? $saldos->get( $fecha->format('Y-m-d') ) : null;
        }
    }

    /**
     * @param \DateTimeInterface $fecha
     * @return SaldoBancario
     */
    public function getSaldoProyectado(\DateTimeInterface $fecha): SaldoBancario
    {
        $unDia = new \DateInterval('P1D');
        $fechaInicial = $fecha->sub( new \DateInterval('P1D') );
        $saldos = $this->getSaldos();

        $saldoActual = new SaldoBancario();
        $saldoActual->setValor(0);
        $saldoActual->setBanco( $this );

        $primerSaldo = $saldos->first();
        if ( $primerSaldo ) {
            $primeraFecha = $primerSaldo->getFecha()->format('Y-m-d');
            while ( $fechaInicial->format('Y-m-d') != $primeraFecha && !$saldos->containsKey( $fechaInicial->format('Y-m-d') ) ) {
                $fechaInicial = $fechaInicial->sub($unDia);
            }
            $saldoActual = clone $saldos->get( $fechaInicial->format('Y-m-d') );
        }

        $movimientos = $this->getMovimientos()->filter( function (Movimiento $m) use ($fecha, $fechaInicial) {

            return $m->getFecha()->getTimestamp() >= $fechaInicial->getTimestamp() && $m->getFecha()->getTimestamp() < $fecha->getTimestamp() && !$m->getConcretado();
        } );

        foreach ($movimientos as $movimiento) {
            $saldoActual->setValor( $saldoActual->getValor() + $movimiento->getImporte() );
        }

        $saldoActual->setFecha( $fecha );

        return $saldoActual;
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
     * @return Banco
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
            ->andWhere(Criteria::expr()->eq('concretado', false))
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
            ->andWhere(Criteria::expr()->eq('concretado', false))
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
            $extracto->setBanco($this);
        }

        return $this;
    }

    public function removeExtracto(ExtractoBancario $extracto): self
    {
        if ($this->extractos->contains($extracto)) {
            $this->extractos->removeElement($extracto);
            // set the owning side to null (unless already changed)
            if ($extracto->getBanco() === $this) {
                $extracto->setBanco(null);
            }
        }

        return $this;
    }
}
