<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RenglonExtractoRepository")
 */
class RenglonExtracto
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
}
