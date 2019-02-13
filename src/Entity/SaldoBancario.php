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
     * @ORM\ManyToOne(targetEntity="Bank", inversedBy="saldos")
     * @ORM\JoinColumn(nullable=false, name="banco_id")
     */
    private $bank;

    /**
     * @ORM\Column(type="date")
     */
    private $fecha;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $valor;

    /**
     * @return mixed
     */
    public function getDiferenciaConProyectado()
    {
        return $this->diferenciaConProyectado;
    }

    /**
     * @param mixed $diferenciaConProyectado
     * @return SaldoBancario
     */
    public function setDiferenciaConProyectado($diferenciaConProyectado)
    {
        $this->diferenciaConProyectado = $diferenciaConProyectado;
        return $this;
    }

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, options="{default=0}")
     */
    private $diferenciaConProyectado = 0;

    public function getId()
    {
        return $this->id;
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
