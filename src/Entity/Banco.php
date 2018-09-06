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
     * @ORM\OneToMany(targetEntity="SaldoBancario", mappedBy="banco", orphanRemoval=true)
     */
    private $saldos;

    public function __construct()
    {
        $this->movimientos = new ArrayCollection();
        $this->gastosFijos = new ArrayCollection();
        $this->saldos = new ArrayCollection();
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
            $this->saldos[] = $saldo;
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

            return !empty($saldos) ? $saldos->last() : null;
        } else {
            foreach ( $saldos as $saldo ) {
                if ( $saldo->getFecha()->diff( $fecha )->d === 0 ) {

                    return $saldo;
                }
            }
            $nuevoSaldo = $this->createSaldo($fecha);
            $this->addSaldo($nuevoSaldo);

            return $nuevoSaldo;
        }
    }

    /**
     * @param \DateTimeInterface $fecha
     */
    private function createSaldo(\DateTimeInterface $fecha): SaldoBancario
    {
        $hoy = new \DateTime();
        if ( $fecha->diff( $hoy )->d < 0 ) {
            // Nunca deberia entrar, pero... tal vez deberia arrojar una exception

            return $this->getSaldo( $fecha );
        }
        $saldo = $this->getSaldo()->getValor();

        $movimientos = $this->getMovimientos()->filter( function (Movimiento $m) use ($fecha, $hoy) {

            return $m->getFecha()->diff($hoy)->d >= 0 && $m->getFecha()->diff($fecha)->d <= 0 && !$m->getConcretado();
        } );

        foreach ($movimientos as $movimiento) {
            $saldo += $movimiento->getImporte();
        }

        $ret = new SaldoBancario();
        $ret->setBanco( $this );
        $ret->setValor( $saldo );
        $ret->setFecha( $fecha );

        return $ret;
    }

    /**
     * @return Collection|SaldoBancario[]
     */
    public function getSaldosProyectados(): Collection
    {
        $hoy = new \DateTimeImmutable();
        $period = new \DatePeriod($hoy, new \DateInterval('P1D'), 30);
        $saldos = new ArrayCollection();

        foreach ($period as $d) {
            $saldos[] = $this->getSaldo( $d );
        }

        return $saldos;
    }
}
