<?php

namespace App\Entity;

use App\Entity\Subscription;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityNotFoundException;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @ORM\HasLifecycleCallbacks
 * @UniqueEntity(fields="email", message="Cette adresse e-mail est déjà utilisée")
 */
class User implements UserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Email(message="Adresse e-mail non valide")
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * 
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     * 
     */
    private $resetToken;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $last_login;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created_at;

    /**
     * @ORM\Column(type="datetime")
     */
    private $updated_at;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Regex(pattern="/^\S*(?=\S{7,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*$/",
     * message="Merci de saisir un mot de passe plus sécurisé")
     */
    private $password;

    /**
     * @Assert\Regex(pattern="/^\S*(?=\S{7,})(?=\S*[a-z])(?=\S*[A-Z])(?=\S*[\d])\S*$/",
     * message="Merci de saisir un mot de passe plus sécurisé")
     */
    private $plainPassword;
    
    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $google_id;

    /**
    * @Assert\EqualTo(propertyPath="password", message="Les mots de passe ne correspondent pas")
    */
    public $confirm_password;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $first_name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $last_name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $company;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     * 
     */
    private $balanceStartPeriod;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private $roles = [];

    public $captcha;

    /**
     * @ORM\OneToMany(targetEntity=Vehicule::class, mappedBy="user",cascade={"persist", "remove"})
     */
    private $vehicules;

    /**
     * @ORM\OneToMany(targetEntity=Report::class, mappedBy="user",cascade={"persist", "remove"})
     */
    private $reports;

    /**
     * @ORM\OneToOne(targetEntity=Subscription::class, mappedBy="user",cascade={"persist", "remove"})
     */
    private $subscription;

    /**
     * @ORM\OneToMany(targetEntity=Order::class, mappedBy="user",cascade={"persist", "remove"})
     */
    private $orders;

    /**
     * @ORM\OneToMany(targetEntity=UserAddress::class, mappedBy="user", orphanRemoval=true, cascade={"persist", "remove"})
     */
    private $userAddresses;

    const PERIOD_JANUARY = 'January';
    const PERIOD_FEBRUARY = 'February';
    const PERIOD_MARCH = 'March';
    const PERIOD_APRIL = 'April';
    const PERIOD_MAY = 'May';
    const PERIOD_JUNE = 'June';
    const PERIOD_JULY = 'July';
    const PERIOD_AUGUST = 'August';
    const PERIOD_SEPTEMBER = 'September';
    const PERIOD_OCTOBER = 'October';
    const PERIOD_NOVEMBER = 'November';
    const PERIOD_DECEMBER = 'December';

    public static function getBalanceStartPeriods()
    {
        return [
            self::PERIOD_JANUARY,
            self::PERIOD_FEBRUARY,
            self::PERIOD_MARCH,
            self::PERIOD_APRIL,
            self::PERIOD_MAY,
            self::PERIOD_JUNE,
            self::PERIOD_JULY,
            self::PERIOD_AUGUST,
            self::PERIOD_SEPTEMBER,
            self::PERIOD_OCTOBER,
            self::PERIOD_NOVEMBER,
            self::PERIOD_DECEMBER
        ];
    }

    public function __construct()
    {
        $this->vehicules = new ArrayCollection();
        $this->reports = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->userAddresses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(string $password): self
    {
        $this->plainPassword = $password;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(string $first_name): self
    {
        $this->first_name = $first_name;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(string $last_name): self
    {
        $this->last_name = $last_name;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function setRoles(?array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }


    public function eraseCredentials(){}

    public function getSalt(){}

    public function __toString()
    {
        return $this->getFirstName().' '.$this->getLastName();
    }

    /**
     * @return Collection|Vehicule[]
     */
    public function getVehicules(): Collection
    {
        return $this->vehicules;
    }

    public function addVehicule(Vehicule $vehicule): self
    {
        if (!$this->vehicules->contains($vehicule)) {
            $this->vehicules[] = $vehicule;
            $vehicule->setUser($this);
        }

        return $this;
    }

    public function removeVehicule(Vehicule $vehicule): self
    {
        if ($this->vehicules->removeElement($vehicule)) {
            // set the owning side to null (unless already changed)
            if ($vehicule->getUser() === $this) {
                $vehicule->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Report[]
     */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function addReport(Report $report): self
    {
        if (!$this->reports->contains($report)) {
            $this->reports[] = $report;
            $report->setUser($this);
        }

        return $this;
    }

    public function removeReport(Report $report): self
    {
        if ($this->reports->removeElement($report)) {
            // set the owning side to null (unless already changed)
            if ($report->getUser() === $this) {
                $report->setUser(null);
            }
        }

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
            $order->setUser($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): self
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|UserAddress[]
     */
    public function getUserAddresses(): Collection
    {
        return $this->userAddresses;
    }

    public function addUserAddress(UserAddress $userAddress): self
    {
        if (!$this->userAddresses->contains($userAddress)) {
            $this->userAddresses[] = $userAddress;
            $userAddress->setUser($this);
        }

        return $this;
    }

    public function removeUserAddress(UserAddress $userAddress): self
    {
        if ($this->userAddresses->removeElement($userAddress)) {
            // set the owning side to null (unless already changed)
            if ($userAddress->getUser() === $this) {
                $userAddress->setUser(null);
            }
        }

        return $this;
    }

    public function getLastReport()
    {
        //tri par année
        $sort = new Criteria(null, ['validate_date' => Criteria::DESC]);

        $sortedReports = $this->getReports()->matching($sort);

        $report = $sortedReports->first();

        return $report;
        
    }

    public function getGoogleId(): ?string
    {
        return $this->google_id;
    }

    public function setGoogleId(string $google_id): self
    {
        $this->google_id = $google_id;

        return $this;
    }

    public function getDefaultVehicule()
    {
        foreach($this->getVehicules() as $v)
        {
            if($v->getIsDefault()){
                return $v;
            }
        }

        return false;
    }

    public function getBalanceStartPeriod(): ?string
    {
        return $this->balanceStartPeriod;
    }

    public function setBalanceStartPeriod(?string $balanceStartPeriod): self
    {
        $this->balanceStartPeriod = $balanceStartPeriod;

        return $this;
    }

    public function getBalanceEndPeriod(): ?string
    {
        $start = new \DateTime('first day of ' . $this->getBalanceStartPeriod() . ' 2000'); //astuce antibug: 2000 est une année bissextile
        $start->modify('+11 month');
        $end = $start->format('F');

        return $end;
    }

    public function generateBalancePeriodByReport(Report $report): array
    {
        $startMonth = $this->getBalanceStartPeriod(); // ex: 'October'
        $reportDate = $report->getStartDate(); // DateTimeImmutable
    
        // Créer une date de début de période fiscale basée sur le mois de départ
        $fiscalStart = new \DateTimeImmutable('first day of ' . $startMonth . ' ' . $reportDate->format('Y'));
    
        // Si la date du rapport est antérieure à la date de début de la période fiscale, reculer d'un an
        if ($reportDate < $fiscalStart) {
            $fiscalStart = $fiscalStart->modify('-1 year');
        }
    
        // Définir la date de fin de la période fiscale (11 mois après le début)
        $fiscalEnd = $fiscalStart->modify('+11 months')->modify('last day of this month');
    
        return [
            'start' => $fiscalStart,
            'end' => $fiscalEnd,
        ];
    }

    public function getCurrentFiscalPeriod(): ?array
    {
        $startMonth = $this->getBalanceStartPeriod(); // ex: "October"
        $now = new \DateTimeImmutable(); // Date actuelle
    
        // Construire la date de début de la période fiscale potentielle
        $fiscalStart = new \DateTimeImmutable('first day of ' . $startMonth . ' ' . $now->format('Y'));
    
        // Si on est avant le début de la période fiscale, alors on est encore dans la période précédente
        if ($now < $fiscalStart) {
            $fiscalStart = $fiscalStart->modify('-1 year');
        }
    
        $fiscalEnd = $fiscalStart->modify('+11 months')->modify('last day of this month');
    
        return [
            'start' => $fiscalStart,
            'end' => $fiscalEnd,
        ];
    }

    public function getFormattedBalancePeriod($period = array())
    {
        if($period){
            //$value = $period['startMonth'] ." ".$period['prevYear']." -> ".$period['endMonth']." ".$period['nextYear'];
            $value = $period['start']->format('M') ." ".$period['start']->format('Y')." -> ".$period['end']->format('M')." ".$period['end']->format('Y');
            return $value;
        }
        
        return false;
    }

    public function getTranslattedBalancePeriod($period = array())
    {
        if($period){
            $fmt = new \IntlDateFormatter(
                'fr_FR',
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::FULL,
                'Europe/Paris',
                \IntlDateFormatter::GREGORIAN,
                'LLLL'
            );

            $startMonthFr = ucfirst($fmt->format(new \DateTime("first day of ".$period['start']->format('M'))));
            $lastMonthFr = ucfirst($fmt->format(new \DateTime("last day of ".$period['end']->format('M'))));

            $value = $startMonthFr ." ".$period['start']->format('Y')." -> ".$lastMonthFr." ".$period['end']->format('Y');

            return $value;
        }

        return false;
    }

    /*public function getBalancePeriodFilterKeyFromReport(?Report $report): ?string
    {
        $period = $this->generateBalancePeriodByReport($report) ?? $this->getCurrentFiscalPeriod();

        if (!$period) {
            return null;
        }

        // Exemple: '2023-10'
        return $period['start']->format('Y-m');
    }

    public function formatFiscalPeriodLabelFromKey(string $key): string
    {
        // $key = 'YYYY-MM'
        $fiscalStart = \DateTimeImmutable::createFromFormat('Y-m-d', $key . '-01');

        $fiscalEnd = $fiscalStart->modify('+11 months')->modify('last day of this month');

        return sprintf(
            '%s %d → %s %d',
            $fiscalStart->format('F'),
            $fiscalStart->format('Y'),
            $fiscalEnd->format('F'),
            $fiscalEnd->format('Y')
        );
    }*/


    public function hasCompletedStep2()
    {
        if($this->getLastName() && $this->getFirstName() && $this->getBalanceStartPeriod()){
            return true;
        }

        return false;            
    }
    
    public function hasCompletedStep3()
    {
        if($this->getDefaultVehicule()){
            return true;
        }

        return false;            
    }

    public function hasCompletedSetup()
    {
        if($this->hasCompletedStep2() && $this->hasCompletedStep3()) {
            return true;
        }

        return false;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): self
    {
        // unset the owning side of the relation if necessary
        if ($subscription === null && $this->subscription !== null) {
            $this->subscription->setUser(null);
        }

        // set the owning side of the relation if necessary
        if ($subscription !== null && $subscription->getUser() !== $this) {
            $subscription->setUser($this);
        }

        $this->subscription = $subscription;

        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): self
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->last_login;
    }

    public function setLastLogin(\DateTimeInterface $last_login): self
    {
        $this->last_login = $last_login;

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

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
    */
    public function updatedTimestamps(): void
    {
        $this->setUpdatedAt(new \DateTime('now'));    
        if ($this->getCreatedAt() === null) {
            $this->setCreatedAt(new \DateTime('now'));
        }
    }

}

