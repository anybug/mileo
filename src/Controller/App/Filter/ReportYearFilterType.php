<?php

namespace App\Controller\App\Filter;

use App\Entity\Report;
use App\Entity\ReportLine;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class ReportYearFilterType extends AbstractType
{
    private $entityManager;
    private $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'comparison_type_options' => ['type' => 'choice'],
            'value_type' => ChoiceType::class,
            'value_type_options' => [
                'choices' => $this->getPeriods(),
            ]
        ]);
    }

    public function getParent(): string
    {
        return ChoiceFilterType::class;
    }

   public function getPeriods() 
   {

        $reports = $this->entityManager->getRepository(Report::class)->getReportsForUser();
        $choices = [];
        $year = false;

        foreach ($reports as $report) {
            $period = $this->security->getUser()->generateBalancePeriodByReport($report);
            $choices[$this->security->getUser()->getTranslattedBalancePeriod($period)] = $this->security->getUser()->getFormattedBalancePeriod($period);

        }

        if(count($choices) == 0){
            $period = $this->security->getUser()->guessBalancePeriodByYear($year);
            $choices[$this->security->getUser()->getTranslattedBalancePeriod($period)] = $this->security->getUser()->getFormattedBalancePeriod($period);
        }

        return $choices;
    }

}