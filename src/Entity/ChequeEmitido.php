<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChequeEmitidoRepository")
 */
class ChequeEmitido implements Witness
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Bank", inversedBy="chequesEmitidos")
     * @ORM\JoinColumn(nullable=false)
     */
    private $banco;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $numero;

    /**
     * @ORM\Column(type="date")
     */
    private $fecha;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $importe;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Movimiento", mappedBy="parentIssuedCheck", cascade={"persist", "remove"})
     */
    private $childDebit;

    public function getId()
    {
        return $this->id;
    }

    public function getBanco(): ?Bank
    {
        return $this->banco;
    }

    public function setBanco(?Bank $banco): self
    {
        $this->banco = $banco;

        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): self
    {
        $this->numero = $numero;

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

    public function getMovimiento(): ?Movimiento
    {
        return $this->movimiento;
    }

    public function setMovimiento(?Movimiento $movimiento): self
    {
        $this->movimiento = $movimiento;

        return $this;
    }

    public function getChildDebit(): ?Movimiento
    {
        return $this->childDebit;
    }

    public function setChildDebit(?Movimiento $childDebit): self
    {
        $this->childDebit = $childDebit;

        // set (or unset) the owning side of the relation if necessary
        $newParentIssuedCheck = $childDebit === null ? null : $this;
        if ($newParentIssuedCheck !== $childDebit->getParentIssuedCheck()) {
            $childDebit->setParentIssuedCheck($newParentIssuedCheck);
        }

        return $this;
    }

    /**
     * @return Movimiento
     */
    public function createChildDebit(): Movimiento
    {
        $debit = new Movimiento();
        $debit
            ->setBank($this->getBanco())
            ->setImporte($this->getImporte() * -1)
            ->setConcepto('Cheque ' . $this->getNumero())
            ->setFecha($this->getFecha())/** @Todo probably will need to be some time in the future */
            ->setParentIssuedCheck($this);
        $this->setChildDebit($debit);

        return $debit;
    }
}
