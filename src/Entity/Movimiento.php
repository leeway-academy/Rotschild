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
     * @ORM\Column(type="integer", nullable=true)
     */
    private $witnessId;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $witnessClass;

    private $witness;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\ChequeEmitido", inversedBy="childDebit", cascade={"persist", "remove"})
     */
    private $parentIssuedCheck;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\AppliedCheck", inversedBy="childCredit", cascade={"persist", "remove"})
     */
    private $parentAppliedCheck;

    /**
     * @param Witness|null $w
     */
    public function setWitness( Witness $w = null )
    {
        $this->witness = $w;
        $this->witnessClass = get_class( $w );
        $this->witnessId = $w->getId();

        return $this;
    }

    /**
     * @return Witness|null
     */
    public function getWitness() : ?Witness
    {
        return $this->witness;
    }

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

    /**
     * @return bool
     */
    public function isConcretado() : bool
    {
        return !empty( $this->witnessId );
    }

    /**
     * @return bool
     */
    public function isProjected() : bool
    {
        return !$this->isConcretado();
    }

    public function isCredit() : bool
    {

        return $this->getImporte() > 0;
    }

    public function isDebit() : bool
    {

        return $this->getImporte() < 0;
    }

    public function getParentIssuedCheck(): ?ChequeEmitido
    {
        return $this->parentIssuedCheck;
    }

    public function setParentIssuedCheck(?ChequeEmitido $parentIssuedCheck): self
    {
        $this->parentIssuedCheck = $parentIssuedCheck;

        return $this;
    }

    public function getParentAppliedCheck(): ?AppliedCheck
    {
        return $this->parentAppliedCheck;
    }

    public function setParentAppliedCheck(?AppliedCheck $parentAppliedCheck): self
    {
        $this->parentAppliedCheck = $parentAppliedCheck;

        return $this;
    }
}
