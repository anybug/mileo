<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'subscription')]
    private $user;

    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: Plan::class, inversedBy: 'subscriptions')]
    private $plan;
    
    #[ORM\Column(type: 'datetime')]
    private $subscription_start;

    #[ORM\Column(type: 'datetime')]
    private $subscription_end;

    public function __construct()
    {
    
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): self
    {
        $this->plan = $plan;

        return $this;
    }

    public function getSubscriptionStart(): ?\DateTimeInterface
    {
        return $this->subscription_start;
    }

    public function setSubscriptionStart(\DateTimeInterface $subscription_start): self
    {
        $this->subscription_start = $subscription_start;

        return $this;
    }

    public function setSubscriptionEnd(\DateTimeInterface $subscription_end): self
    {
        $this->subscription_end = $subscription_end;

        return $this;
    }

    public function getSubscriptionEnd(): ?\DateTimeInterface
    {
        return $this->subscription_end;
    }
    
    public function __toString()
    {
        return $this->getPlan()->getName();
    }

    public function isValid()
    {
        $now = new \DateTime('now');
        if($this->getSubscriptionEnd() > $now)
        {
            return true;
        }

        return false;
    }
    
    public function isWarning()
    {
        $now = new \DateTime("now");
        $warning = new \DateTime($this->getSubscriptionEnd()->format("Y-m-d"));
        $warning->modify('-1 month');
        if($now > $warning && $this->isValid())
        {
            return true;
        }

        return false;
    }
    
    public function isWarningMail()
    {
        $now = new \DateTime("now");
        $date = new \DateTime($now->format("Y-m-d"));
        $warning = new \DateTime($this->getSubscriptionEnd()->format("Y-m-d"));
        $warning->modify('-1 month');
        if($date == $warning && $this->isValid())
        {
            return true;
        }

        return false;
    }



    public function getNumberDays(){
        $now = time(); // or your date as well
        $your_date = strtotime($this->getSubscriptionEnd()->format("Y-m-d"));
        $datediff = $your_date - $now;
        //dd($this->getNumberDays());
        return( round($datediff / (60 * 60 * 24)));
    }

    public function getProgressValue(){

        $value = $this->getNumberDays()/360*100 ;
        return $value;
    }
}
