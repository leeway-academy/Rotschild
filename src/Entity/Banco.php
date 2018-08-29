<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     */
    private $movimientos;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\GastoFijo", mappedBy="banco")
     */
    private $gastosFijos;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\SaldoHistorico", mappedBy="banco", orphanRemoval=true)
     */
    private $saldosHistoricos;

    public function __construct()
    {
        $this->movimientos = new ArrayCollection();
        $this->gastosFijos = new ArrayCollection();
        $this->saldosHistoricos = new ArrayCollection();
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
     * @return Collection|SaldoHistorico[]
     */
    public function getSaldosHistoricos(): Collection
    {
        return $this->saldosHistoricos;
    }

    public function addSaldoHistorico(SaldoHistorico $saldoHistorico): self
    {
        if (!$this->saldosHistoricos->contains($saldoHistorico)) {
            $this->saldosHistoricos[] = $saldoHistorico;
            $saldoHistorico->setBanco($this);
        }

        return $this;
    }

    public function removeSaldoHistorico(SaldoHistorico $saldoHistorico): self
    {
        if ($this->saldosHistoricos->contains($saldoHistorico)) {
            $this->saldosHistoricos->removeElement($saldoHistorico);
            // set the owning side to null (unless already changed)
            if ($saldoHistorico->getBanco() === $this) {
                $saldoHistorico->setBanco(null);
            }
        }

        return $this;
    }
}
