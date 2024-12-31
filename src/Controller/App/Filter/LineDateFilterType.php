<?php

namespace App\Controller\App\Filter;

use App\Entity\ReportLine;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class LineDateFilterType extends AbstractType
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
                'choices' => $this->getChoices(),
            ]
        ]);
    }

    public function getParent(): string
    {
        return ChoiceFilterType::class;
    }

    public function getChoices() {
        $reportLines = $this->entityManager->getRepository(ReportLine::class)->getLineForUser();
        $choices = [];
        foreach($reportLines as $line)
        {
            $fmt = new \IntlDateFormatter(
                'fr_FR',
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::FULL,
                'Europe/Paris',
                \IntlDateFormatter::GREGORIAN,
                'LLLL'
            );

            $month = ucfirst($fmt->format($line->getTravelDate()));

            $choices[$line->getTravelDate()->format("Y")][$month." ".$line->getTravelDate()->format("Y")] = $line->getTravelDate()->format("F")."/".$line->getTravelDate()->format("Y");
        }

        return $choices;
    }
}