<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="BankXLSStructureRepository")
 */
class BankXLSStructure
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="smallint", options={"default=0"})
     */
    private $firstRow;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $stopWord;

    /**
     * @ORM\Column(type="smallint", options={"default=0"})
     */
    private $conceptCol;

    /**
     * @ORM\Column(type="smallint", options={"default=0"})
     */
    private $amountCol;

    /**
     * @ORM\Column(type="smallint", options={"default=0"})
     */
    private $dateCol;

    /**
     * @ORM\Column(type="string", length=255, options={"default=d/m/Y"})
     */
    private $dateFormat;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Banco", cascade={"persist", "remove"}, inversedBy="xlsStructure")
     * @ORM\JoinColumn(nullable=false)
     */
    private $banco;

    public function getId()
    {
        return $this->id;
    }

    public function getFirstRow(): ?int
    {
        return $this->firstRow;
    }

    public function setFirstRow(int $firstRow): self
    {
        $this->firstRow = $firstRow;

        return $this;
    }

    public function getStopWord(): ?string
    {
        return $this->stopWord;
    }

    public function setStopWord(string $stopWord): self
    {
        $this->stopWord = $stopWord;

        return $this;
    }

    public function getConceptCol(): ?int
    {
        return $this->conceptCol;
    }

    public function setConceptCol(int $conceptCol): self
    {
        $this->conceptCol = $conceptCol;

        return $this;
    }

    public function getAmountCol(): ?int
    {
        return $this->amountCol;
    }

    public function setAmountCol(int $amountCol): self
    {
        $this->amountCol = $amountCol;

        return $this;
    }

    public function getDateCol(): ?int
    {
        return $this->dateCol;
    }

    public function setDateCol(int $dateCol): self
    {
        $this->dateCol = $dateCol;

        return $this;
    }

    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    public function setDateFormat(string $dateFormat): self
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }

    public function getBanco(): ?Banco
    {
        return $this->banco;
    }

    public function setBanco(Banco $banco): self
    {
        $this->banco = $banco;

        return $this;
    }
}
