<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ExtractoBancarioRepository")
 */
class ExtractoBancario
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Bank", inversedBy="extractos")
     * @ORM\JoinColumn(nullable=false, name="banco_id")
     */
    private $bank;

    /**
     * @ORM\Column(type="date")
     */
    private $fecha;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $archivo;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\RenglonExtracto", mappedBy="extracto", orphanRemoval=true, cascade={"persist"})
     */
    private $renglones;

    public function __construct()
    {
        $this->renglones = new ArrayCollection();
    }

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

    public function getArchivo(): ?string
    {
        return $this->archivo;
    }

    public function setArchivo(string $archivo): self
    {
        $this->archivo = $archivo;

        return $this;
    }

    /**
     * @return Collection|RenglonExtracto[]
     */
    public function getRenglones(): Collection
    {
        return $this->renglones;
    }

    /**
     * @param RenglonExtracto $renglon
     * @return ExtractoBancario
     */
    public function addRenglon(RenglonExtracto $renglon): self
    {
        if (!$this->renglones->contains($renglon)) {
            $this->renglones[] = $renglon;
            $renglon->setExtracto($this);
        }

        return $this;
    }

    public function removeRenglone(RenglonExtracto $renglone): self
    {
        if ($this->renglones->contains($renglone)) {
            $this->renglones->removeElement($renglone);
            // set the owning side to null (unless already changed)
            if ($renglone->getExtracto() === $this) {
                $renglone->setExtracto(null);
            }
        }

        return $this;
    }
}
