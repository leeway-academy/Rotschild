<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MovimientoRepository")
 */
class Movimiento
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
    private $concepto;

    /**
     * @ORM\Column(type="date")
     */
    private $fecha;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $importe;

    /**
     * @ORM\ManyToOne(targetEntity="Bank", inversedBy="movimientos")
     * @ORM\JoinColumn(nullable=false, name="banco_id")
     */
    private $bank;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\GastoFijo", inversedBy="movimientos")
     */
    private $clonDe;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\RenglonExtracto", inversedBy="movimientos")
     */
    private $renglonExtracto;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\AppliedCheck", inversedBy="movimiento", cascade={"persist", "remove"})
     */
    private $appliedCheck;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\ChequeEmitido", mappedBy="movimiento", cascade={"persist", "remove"})
     */
    private $issuedCheck;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\ChequeEmitido", mappedBy="movimiento", cascade={"persist", "remove"})
     */
    private $chequeEmitido;

    public function getId()
    {
        return $this->id;
    }

    public function getConcepto(): ?string
    {
        return $this->concepto;
    }

    public function setConcepto(string $concepto): self
    {
        $this->concepto = $concepto;

        return $this;
    }

    public function getFecha(): ?\DateTimeInterface
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeInterface $fecha): self
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getImporte()
    {
        return $this->importe;
    }

    public function setImporte($importe): self
    {
        $this->importe = $importe;

        return $this;
    }

    public function getBank(): ?Bank
    {
        return $this->bank;
    }

    public function setBank(?Bank $bank): self
    {
        $this->bank = $bank;

        return $this;
    }

    public function __toString()
    {
        return $this->getFecha()->format('d/m/Y').': '.($this->importe < 0 ? '-' : '').'$'.abs($this->importe).' ('.$this->getConcepto().')';
    }

    public function __construct()
    {
        $this->setFecha( new \DateTime() );
    }

    public function getClonDe(): ?GastoFijo
    {
        return $this->clonDe;
    }

    public function setClonDe(?GastoFijo $clonDe): self
    {
        $this->clonDe = $clonDe;

        return $this;
    }

    public function getRenglonExtracto(): ?RenglonExtracto
    {
        return $this->renglonExtracto;
    }

    public function setRenglonExtracto(?RenglonExtracto $renglonExtracto): self
    {
        $this->renglonExtracto = $renglonExtracto;

        return $this;
    }

    /**
     * @return bool
     */
    public function isConcretado() : bool
    {
        return !empty($this->getRenglonExtracto());
    }

    /**
     * @return bool
     */
    public function isProjected() : bool
    {
        return !$this->isConcretado();
    }

    public function getAppliedCheck(): ?AppliedCheck
    {
        return $this->appliedCheck;
    }

    public function setAppliedCheck(?AppliedCheck $appliedCheck): self
    {
        $this->appliedCheck = $appliedCheck;

        return $this;
    }

    public function isCredit() : bool
    {

        return $this->getImporte() > 0;
    }

    public function isDebit() : bool
    {

        return $this->getImporte() < 0;
    }

    public function getIssuedCheck(): ?IssuedCheck
    {
        return $this->issuedCheck;
    }

    public function setIssuedCheck(?IssuedCheck $issuedCheck): self
    {
        $this->issuedCheck = $issuedCheck;

        // set (or unset) the owning side of the relation if necessary
        $newMovimiento = $issuedCheck === null ? null : $this;
        if ($newMovimiento !== $issuedCheck->getMovimiento()) {
            $issuedCheck->setMovimiento($newMovimiento);
        }

        return $this;
    }

    public function getChequeEmitido(): ?ChequeEmitido
    {
        return $this->chequeEmitido;
    }

    public function setChequeEmitido(?ChequeEmitido $chequeEmitido): self
    {
        $this->chequeEmitido = $chequeEmitido;

        // set (or unset) the owning side of the relation if necessary
        $newMovimiento = $chequeEmitido === null ? null : $this;
        if ($newMovimiento !== $chequeEmitido->getMovimiento()) {
            $chequeEmitido->setMovimiento($newMovimiento);
        }

        return $this;
    }
}
