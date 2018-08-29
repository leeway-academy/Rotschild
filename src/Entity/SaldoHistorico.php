<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\SaldoHistoricoRepository")
 */
class SaldoHistorico
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Banco", inversedBy="saldosHistoricos")
     * @ORM\JoinColumn(nullable=false)
     */
    private $banco;

    /**
     * @ORM\Column(type="date",unique=true)
     */
    private $fecha;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $valor;

    public function getId()
    {
        return $this->id;
    }

    public function getBanco(): ?Banco
    {
        return $this->banco;
    }

    public function setBanco(?Banco $banco): self
    {
        $this->banco = $banco;

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

    public function getValor()
    {
        return $this->valor;
    }

    public function setValor($valor): self
    {
        $this->valor = $valor;

        return $this;
    }

    public function __toString()
    {
        return $this->getFecha()->format('d/m/Y').': '.$this->getValor();
    }
}
