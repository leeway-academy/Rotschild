<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * @ORM\Table(name="saldo_bancario", uniqueConstraints={@UniqueConstraint(name="saldo_banco",columns={"fecha","banco_id"})})
 * @ORM\Entity(repositoryClass="App\Repository\SaldoHistoricoRepository")
 */
class SaldoBancario
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Banco", inversedBy="saldos")
     * @ORM\JoinColumn(nullable=false)
     */
    private $banco;

    /**
     * @ORM\Column(type="date")
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

    /**
     * @return string
     * @todo This should probably be moved closer to the template layer in order to keep compatibility with different locales
     */
    public function __toString()
    {
        return $this->getFecha()->format('d/m/Y').': $'.number_format( $this->getValor(), 2, ',','.' );
    }
}
