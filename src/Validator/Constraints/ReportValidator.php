<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Report;
use Symfony\Component\Security\Core\Security;

class ReportValidator extends ConstraintValidator
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
        $lines = $entity->getLines();
        if (count($lines) == 0) {
            $this->context->buildViolation("Merci de saisir au un moins un trajet dans le rapport")
                    ->addViolation();
        }
        foreach ($lines as $line) {
            if($line->getTravelDate() < $startDate || $line->getTravelDate() > $endDate){
                $this->context->buildViolation("La date d'un des trajets ne correspond pas à la période du rapport")
                    ->addViolation();
            }
        }
    }
}