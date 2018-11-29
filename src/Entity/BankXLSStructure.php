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
     * @ORM\Column(type="smallint", options={"default=1"})
     */
    private $firstRow = 1;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $stopWord;

    /**
     * @ORM\Column(type="smallint", options={"default=1"})
     */
    private $conceptCol = 1;

    /**
     * @ORM\Column(type="smallint", options={"default=1"})
     */
    private $amountCol = 1;

    /**
     * @ORM\Column(type="smallint", options={"default=1"})
     */
    private $dateCol = 1;

    /**
     * @ORM\Column(type="string", length=255, options={"default=d/m/Y"}, nullable=true)
     */
    private $dateFormat;

    /**
     * @ORM\OneToOne(targetEntity="Bank", cascade={"persist", "remove"}, inversedBy="xlsStructure")
     * @ORM\JoinColumn(nullable=false, name="banco_id")
     */
    private $bank;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $firstHeader;

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

    public function getBank(): ?Bank
    {
        return $this->bank;
    }

    public function setBank(Bank $bank): self
    {
        $this->bank = $bank;

        return $this;
    }

    public function getFirstHeader(): ?string
    {
        return $this->firstHeader;
    }

    public function setFirstHeader(?string $firstHeader): self
    {
        $this->firstHeader = $firstHeader;

        return $this;
    }
}
