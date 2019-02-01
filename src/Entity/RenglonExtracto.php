<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RenglonExtractoRepository")
 */
class RenglonExtracto implements Witness
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ExtractoBancario", inversedBy="renglones")
     * @ORM\JoinColumn(nullable=false)
     */
    private $extracto;

    /**
     * @ORM\Column(type="integer")
     */
    private $linea;

    /**
     * @ORM\Column(type="date")
     */
    private $fecha;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $concepto;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $importe;

    private $movimientos;

    public function __construct()
    {
        $this->movimientos = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getExtracto(): ?ExtractoBancario
    {
        return $this->extracto;
    }

    public function setExtracto(?ExtractoBancario $extracto): self
    {
        $this->extracto = $extracto;

        return $this;
    }

    public function getLinea(): ?int
    {
        return $this->linea;
    }

    public function setLinea(int $linea): self
    {
        $this->linea = $linea;

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

    public function getConcepto(): ?string
    {
        return $this->concepto;
    }

    public function setConcepto(string $concepto): self
    {
        $this->concepto = $concepto;

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
            $movimiento->setWitness($this);
        }

        return $this;
    }

    public function removeMovimiento(Movimiento $movimiento): self
    {
        if ($this->movimientos->contains($movimiento)) {
            $this->movimientos->removeElement($movimiento);
            // set the owning side to null (unless already changed)
            if ($movimiento->getWitness() === $this) {
                $movimiento->setWitness(null);
            }
        }

        return $this;
    }

    public function setMovimientos( Collection $c )
    {
        $this->movimientos = $c;
    }

    public function __toString()
    {
        return $this->getExtracto()->getBank().' '.$this->getExtracto()->getFecha()->format('d/m/Y').': "'.$this->getConcepto().'" (linea '.$this->getLinea().')';
    }

    public function makeAvailable()
    {
        // This method was implemented to alter the processed flag, initially only useful for issuedChecks but...
    }
}
