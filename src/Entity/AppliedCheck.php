<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AppliedCheckRepository")
 */
class AppliedCheck
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
    private $number;

    /**
     * @ORM\Column(type="date")
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $sourceBank;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $issuer;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $destination;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $amount;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Movimiento", mappedBy="appliedCheck", cascade={"persist", "remove"})
     */
    private $movimiento;

    public function getId()
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getSourceBank(): ?string
    {
        return $this->sourceBank;
    }

    public function setSourceBank(string $sourceBank): self
    {
        $this->sourceBank = $sourceBank;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getIssuer(): ?string
    {
        return $this->issuer;
    }

    public function setIssuer(string $issuer): self
    {
        $this->issuer = $issuer;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setAmount($amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCreditDate() : \DateTimeInterface
    {
        return $this->getType() == 'Diferido' ? $this->getDate()->add( new \DateInterval('P2D') ) : $this->getDate();
    }

    public function getMovimiento(): ?Movimiento
    {
        return $this->movimiento;
    }

    public function setMovimiento(?Movimiento $movimiento): self
    {
        $this->movimiento = $movimiento;

        // set (or unset) the owning side of the relation if necessary
        $newAppliedCheck = $movimiento === null ? null : $this;
        if ($newAppliedCheck !== $movimiento->getAppliedCheck()) {
            $movimiento->setAppliedCheck($newAppliedCheck);
        }

        return $this;
    }
}
