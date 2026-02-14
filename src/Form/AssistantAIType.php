<?php
// src/Form/AssistantAIType.php
namespace App\Form;

use App\Entity\Report;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Event\PreSubmitEvent;

class AssistantAIType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $report = $options['report'];

        $builder
            ->add('action', ChoiceType::class, [
                'label' => 'Je veux...',
                'choices' => [
                    'Dupliquer une semaine' => 'duplicate_week',
                    //'Copier un trajet spécifique' => 'copy_trip',
                    'Répéter un trajet dans le mois (même jour de semaine)' => 'repeat_monthly',
                ],
                'expanded' => true,
                'mapped' => false,
            ])
            ->add('source_week', ChoiceType::class, [
                'label' => 'Semaine source',
                'choices' => $this->getWeeksForReport($report),
                'mapped' => false,
                'required' => false,
            ])
            ->add('target', ChoiceType::class, [
                'label' => 'Destination',
                'choices' => [
                    'Semaine suivante' => 'next_week',
                    'Tout le mois' => 'full_month',
                ],
                'mapped' => false,
                'required' => false,
            ])
            ->add('trip_id', ChoiceType::class, [
                'label' => 'Trajet',
                'choices' => $this->getTripsForReport($report),
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Sélectionnez un trajet',
            ])
            ->add('target_dates', ChoiceType::class, [
                'label' => 'Dates cibles (optionnel)',
                'choices' => [],
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Aucune date spécifique',
            ])
            ->add('parameters', HiddenType::class, [
                'mapped' => false,
            ])
            ->add('prompt_type', HiddenType::class, [
                'mapped' => false,
            ])
            /*->add('submit', SubmitType::class, [
                'label' => 'Prévisualiser',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ])*/
            ;


        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event) use ($report) {
            $data = $event->getData();
            //$form = $event->getForm();

            $actionType = $data['action'] ?? null;
            $parameters = $this->buildParameters($actionType, $data, $report);

            /*$form->get('parameters')->setData(json_encode($parameters));
            $form->get('prompt_type')->setData($actionType);*/

            $data['parameters'] = json_encode($parameters);
            $data['prompt_type'] = $actionType;

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'report' => null,
        ]);
    }

    private function buildParameters(?string $actionType, array $formData, Report $report): array
    {
        switch ($actionType) {
            case 'duplicate_week':
                return [
                    'source_week' => $formData['source_week'] ?? null,
                    'target_weeks' => $this->getTargetWeeks($formData['target'] ?? null, $formData['source_week'] ?? null),
                ];
            case 'copy_trip':
                return [
                    'trip' => $this->getTripData($report, $formData['trip_id'] ?? null),
                    'target_dates' => $this->getTargetDates($formData['target'] ?? null, $formData['source_week'] ?? null),
                ];
            case 'repeat_monthly':
                return [
                    'trip' => $this->getTripData($report, $formData['trip_id'] ?? null),
                ];
            default:
                return [];
        }
    }

    private function getWeeksForReport(Report $report): array
    {
        $weeks = [];

        foreach ($report->getLines() as $line) {
            $date = $line->getTravelDate();
            $weekNumber = (int)$date->format('W');
            $weekStart = (clone $date)->modify('Monday this week')->format('d/m/Y');
            $weekEnd = (clone $date)->modify('Sunday this week')->format('d/m/Y');
            $weekKey = "Semaine $weekNumber";
            $weekLabel = "$weekKey ($weekStart - $weekEnd)";

            if (!isset($weeks[$weekLabel])) {
                $weeks[$weekLabel] = [
                    'date' => (clone $date)->modify('Monday this week')->format('Y-m-d'),
                    'count' => 0
                ];
            }
            $weeks[$weekLabel]['count']++;
        }

        // Formater le label avec le nombre de trajets
        foreach ($weeks as $weekLabel => $data) {
            $weeks[$weekLabel . " ({$data['count']} trajet(s))"] = $data['date'];
            unset($weeks[$weekLabel]); // Supprimer l'ancienne entrée
        }

        asort($weeks);
        return $weeks;
    }



    private function getTripsForReport(Report $report): array
    {
        $trips = [];
        foreach ($report->getLines() as $line) {
            $trips[$line->__toString()] = $line->getId();
        }
        return $trips;
    }

    private function getTargetWeeks(?string $targetOption, ?string $sourceWeek): array
    {
        if (!$targetOption || !$sourceWeek) {
            return [];
        }

        $sourceDate = new \DateTime($sourceWeek);
        switch ($targetOption) {
            case 'next_week':
                return [(int)$sourceDate->format('W') + 1];
            case 'full_month':
                $endOfMonth = (clone $sourceDate)->modify('last day of this month');
                return range((int)$sourceDate->format('W') + 1, (int)$endOfMonth->format('W'));
        }
        return [];
    }

    private function getTargetDates(?string $targetOption, ?string $sourceWeek): array
    {
        if (!$targetOption || !$sourceWeek) {
            return [];
        }

        $sourceDate = new \DateTime($sourceWeek);
        switch ($targetOption) {
            case 'next_week':
                return [(clone $sourceDate)->modify('+7 days')->format('Y-m-d')];
            case 'full_month':
                $dates = [];
                $endOfMonth = (clone $sourceDate)->modify('last day of this month');
                for ($date = (clone $sourceDate)->modify('+7 days'); $date <= $endOfMonth; $date->modify('+7 days')) {
                    $dates[] = $date->format('Y-m-d');
                }
                return $dates;
        }
        return [];
    }

    private function getTripData(Report $report, ?int $tripId): ?array
    {
        if (!$tripId) {
            return null;
        }

        foreach ($report->getLines() as $line) {
            if ($line->getId() === $tripId) {
                return [
                    'date' => $line->getTravelDate()->format('Y-m-d'),
                    'depart' => $line->getStartAdress(),
                    'arrivee' => $line->getEndAdress(),
                    'km' => $line->getKm(),
                    'km_total' => $line->getKmTotal(),
                    'is_return' => $line->getIsReturn(),
                    'vehicule_id' => $line->getVehicule()->getId(),
                    'amount' => $line->getAmount(),
                    'commentaire' => $line->getComment(),
                ];
            }
        }

        return null;
    }
}
