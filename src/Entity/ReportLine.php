<?php

namespace App\Entity;

use App\Repository\ReportLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ReportLineRepository::class)
 */
class ReportLine
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Report::class, inversedBy="lines")
     */
    private $report;

    /**
     * @ORM\ManyToOne(targetEntity=Vehicule::class, inversedBy="reportlines")
     * @ORM\JoinColumn(nullable=false)
     */
    private $vehicule;

    private $scale;

    private $favories;

    /**
     * @ORM\Column(type="date")
     * @Assert\NotBlank
     * @Assert\Type("\DateTimeInterface")
     */
    private $travel_date;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank
     */
    private $startAdress;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank
     */
    private $endAdress;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Assert\NotBlank
     */
    private $km;

    /**
     * @ORM\Column(type="boolean")
     */
    private $is_return;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank
     */
    private $km_total;
    
    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     * @Assert\NotBlank
     */
    private $amount;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\NotBlank
     */
    private $comment;
    
    /**
     * @ORM\Column(type="datetime")
     */
    private $created_at;

    /**
     * @ORM\Column(type="datetime")
     */
    private $updated_at;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReport(): ?Report
    {
        return $this->report;
    }

    public function setReport(?Report $report): self
    {
        $this->report = $report;

        return $this;
    }

    public function getStartAdress(): ?string
    {
        return $this->startAdress;
    }

    public function setStartAdress(string $startAdress): self
    {
        $this->startAdress = $startAdress;

        return $this;
    }

    public function getEndAdress(): ?string
    {
        return $this->endAdress;
    }

    public function setEndAdress(string $endAdress): self
    {
        $this->endAdress = $endAdress;

        return $this;
    }

    public function getKm(): ?int
    {
        return $this->km;
    }

    public function setKm(int $km): self
    {
        $this->km = $km;

        return $this;
    }

    public function getIsReturn(): ?bool
    {
        return $this->is_return;
    }

    public function setIsReturn(bool $is_return): self
    {
        $this->is_return = $is_return;

        return $this;
    }

    public function getKmTotal(): ?int
    {
        return $this->km_total;
    }

    public function setKmTotal(int $km_total): self
    {
        $this->km_total = $km_total;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getVehicule(): ?Vehicule
    {
        return $this->vehicule;
    }

    public function setVehicule(?Vehicule $vehicule): self
    {
        $this->vehicule = $vehicule;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function calculateAmount()
    {
        $amount=0;
        $amount = ($this->getScale()->getRate()*$this->getKmTotal()) /*+ ($this->getScale()->getAmount()/12*)*/;
        $amount = round($amount, 2);
        $this->setAmount($amount);

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getScale(): ?Scale
    {
        return $this->scale;
    }

    public function setScale(?Scale $scale): self
    {
        $this->scale = $scale;

        return $this;
    }

    public function getTravelDate(): ?\DateTimeInterface
    {
        return $this->travel_date;
    }

    public function setTravelDate(?\DateTimeInterface $travel_date): self
    {
        $this->travel_date = $travel_date;

        return $this;
    }

    public function isIsReturn(): ?bool
    {
        return $this->is_return;
    }

    public function getFavories(){}

    public function setFavories($favories){
        $this->favories = $favories;
    }
    
    public function getPeriod(){}

    public function __toString()
    {
        return "Trajet du ".$this->travel_date->format("d/m/Y")." de ". $this->km_total ." km";
    }
}
