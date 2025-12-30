<?php

namespace App\Entity;

use App\Repository\ScaleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScaleRepository::class)]
class Scale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: Power::class, inversedBy: 'scales')]
    private $power;

    #[ORM\Column(type: 'integer')]
    private $year;

    #[ORM\Column(type: 'integer')]
    private $km_min;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $km_max;

    #[ORM\Column(type: 'float')]
    private $rate;

    #[ORM\Column(type: 'integer')]
    private $amount;

    #[ORM\OneToMany(targetEntity: ReportLine::class, mappedBy: 'scale')]
    private $reportlines;

    public function __construct()
    {
        $this->reportlines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPower(): ?Power
    {
        return $this->power;
    }

    public function setPower(?Power $power): self
    {
        $this->power = $power;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getKmMin(): ?int
    {
        return $this->km_min;
    }

    public function setKmMin(int $km_min): self
    {
        $this->km_min = $km_min;

        return $this;
    }

    public function getKmMax(): ?int
    {
        return $this->km_max;
    }

    public function setKmMax(?int $km_max): self
    {
        $this->km_max = $km_max;

        return $this;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(float $rate): self
    {
        $this->rate = $rate;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function __toString()
    {
        $scale = 'Distance (d) ';
        
        if($this->getKmMin()==0)
        {
            $scale .= "jusqu'à ".$this->getKmMax().' km';
        }

        if($this->getKmMin()==3001 || $this->getKmMin()==5001)
        {
            $scale .= 'de '.$this->getKmMin().' km à '.$this->getKmMax().' km';
        }

        if($this->getKmMin()==6001 || $this->getKmMin()==20001)
        {
            $scale .= 'au-delà de '.$this->getKmMin().' km';
        }

        $scale .= ' = (d x '.$this->getRate().')';

        if($this->getAmount() && $this->getAmount()>0){
            $scale .= ' + '.$this->getAmount();
        }

        return $scale;
    }

    public function __toStringWithoutAmount()
    {
        $scale = 'Distance (d) ';
        
        if($this->getKmMin()==0)
        {
            $scale .= "jusqu'à ".$this->getKmMax().' km';
        }

        if($this->getKmMin()==3001 || $this->getKmMin()==5001)
        {
            $scale .= 'de '.$this->getKmMin().' km à '.$this->getKmMax().' km';
        }

        if($this->getKmMin()==6001 || $this->getKmMin()==20001)
        {
            $scale .= 'au-delà de '.$this->getKmMin().' km';
        }

        return $scale;
    }

    public function __toStringAmountOnly()
    {
        $scale = '(d x '.number_format($this->getRate(), 3, ',').')';

        if($this->getAmount() && $this->getAmount()>0){
            $scale .= ' + '.$this->getAmount();
        }

        return $scale;
    }

    /**
     * @return Collection, ReportLine
     */
    public function getReportlines(): Collection
    {
        return $this->reportlines;
    }

    public function addReportline(ReportLine $reportline): self
    {
        if (!$this->reportlines->contains($reportline)) {
            $this->reportlines[] = $reportline;
            $reportline->setScale($this);
        }

        return $this;
    }

    public function removeReportline(ReportLine $reportline): self
    {
        if ($this->reportlines->removeElement($reportline)) {
            // set the owning side to null (unless already changed)
            if ($reportline->getScale() === $this) {
                $reportline->setScale(null);
            }
        }

        return $this;
    }

}
