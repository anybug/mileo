<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Report;
use Symfony\Component\Security\Core\Security;

class NewReportValidator extends ConstraintValidator
{
    private $entityManager;
    private $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function validate($entity, Constraint $constraint)
    {
        $startDate = $entity->getStartDate();
        $endDate = $entity->getEndDate();

        $reports= $this->entityManager->getRepository(Report::class)->Findby(["user" => $this->security->getToken()->getUser()]);
        foreach ($reports as $report) {
            if($report->getStartDate() == $startDate && $report->getEndDate() == $endDate){
                $this->context->buildViolation("Le rapport de cette période a déjà été créé")
                    ->addViolation();
            }
        }
    }
}