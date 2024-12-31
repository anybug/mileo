<?php

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PlanRepository::class)
 */
class Plan
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $price_per_month;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $price_per_year;

    /**
     * @ORM\Column(type="integer", length=5)
     */
    private $plan_period;

    /**
     * @ORM\OneToMany(targetEntity=Subscription::class, mappedBy="plan")
     */
    private $subscriptions;

    /**
     * @ORM\Column(type="float")
     */
    private $totalCost;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $billingDetails;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $oldPrice;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $savingPercentage;

    /**
     * @ORM\OneToMany(targetEntity=Order::class, mappedBy="Plan")
     */
    private $orders;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $plan_description;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPricePerMonth(): ?float
    {
        return $this->price_per_month;
    }

    public function setPricePerMonth(?float $price_per_month): self
    {
        $this->price_per_month = $price_per_month;

        return $this;
    }

    public function getPricePerYear(): ?float
    {
        return $this->price_per_year;
    }

    public function setPricePerYear(?float $price_per_year): self
    {
        $this->price_per_year = $price_per_year;

        return $this;
    }

    /**
     * @return Collection|Subscription[]
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): self
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions[] = $subscription;
            $subscription->setPlan($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): self
    {
        if ($this->subscriptions->removeElement($subscription)) {
            // set the owning side to null (unless already changed)
            if ($subscription->getPlan() === $this) {
                $subscription->setPlan(null);
            }
        }

        return $this;
    }

    public function getTotalCost(): ?float
    {
        return $this->totalCost;
    }

    public function setTotalCost(float $totalCost): self
    {
        $this->totalCost = $totalCost;

        return $this;
    }

    public function getBillingDetails(): ?string
    {
        return $this->billingDetails;
    }

    public function setBillingDetails(string $billingDetails): self
    {
        $this->billingDetails = $billingDetails;

        return $this;
    }

    public function getOldPrice(): ?float
    {
        return $this->oldPrice;
    }

    public function setOldPrice(?float $oldPrice): self
    {
        $this->oldPrice = $oldPrice;

        return $this;
    }

    public function getSavingPercentage(): ?string
    {
        return $this->savingPercentage;
    }

    public function setSavingPercentage(?string $savingPercentage): self
    {
        $this->savingPercentage = $savingPercentage;

        return $this;
    }

    /**
     * @return Collection|Order[]
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): self
    {
        if (!$this->orders->contains($order)) {
            $this->orders[] = $order;
            $order->setPlan($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): self
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getPlan() === $this) {
                $order->setPlan(null);
            }
        }

        return $this;
    }

    public function getPlanDescription(): ?string
    {
        return $this->plan_description;
    }

    public function setPlanDescription(string $plan_description): self
    {
        $this->plan_description = $plan_description;

        return $this;
    }

    public function __toString()
    {
        return $this->getPlanDescription();
    }

    public function getPlanPeriod(): ?int
    {
        return $this->plan_period;
    }

    public function setPlanPeriod(int $plan_period): self
    {
        $this->plan_period = $plan_period;

        return $this;
    }

}
