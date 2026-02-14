<?php
// src/Service/MistralPromptBuilder.php
namespace App\Service;

use App\Entity\Report;
use App\Entity\ReportLine;

class MistralPromptBuilder
{
    public function buildPrompt(string $actionType, Report $report, array $parameters): string
    {
        switch ($actionType) {
            case 'duplicate_week':
                return $this->buildDuplicateWeekPrompt($report, $parameters);
            case 'copy_trip':
                return $this->buildCopyTripPrompt($report, $parameters);
            case 'repeat_monthly':
                return $this->buildRepeatMonthlyPrompt($report, $parameters);
            default:
                throw new \InvalidArgumentException("Type d'action non supporté : $actionType");
        }
    }

    private function buildDuplicateWeekPrompt(Report $report, array $params): string
    {
        $sourceWeek = $params['source_week'];
        $targetWeeks = $params['target_weeks'];
        $trips = $this->getTripsForWeek($report, $sourceWeek);

        return sprintf(
            "Contexte : Rapport kilométrique pour le mois de %s (du %s au %s).
            Duplique les trajets de la semaine %d (du %s) vers les semaines %s en décalant les dates.
            Trajets de la semaine source :
            %s
            Format de réponse (JSON strict) :
            [{\"date\": \"YYYY-MM-DD\", \"depart\": \"...\", \"arrivee\": \"...\", \"km\": X, \"km_total\": X, \"is_return\": BOOLEAN, \"vehicule_id\": X, \"amount\": X, \"commentaire\": \"...\"}]",
            $report->getStartDate()->format('m/Y'),
            $report->getStartDate()->format('Y-m-d'),
            $report->getEndDate()->format('Y-m-d'),
            (int)(new \DateTime($sourceWeek))->format('W'),
            $sourceWeek,
            implode(', ', $targetWeeks),
            json_encode($trips)
        );
    }

    private function buildCopyTripPrompt(Report $report, array $params): string
    {
        $trip = $params['trip'];
        $targetDates = $params['target_dates'];

        return sprintf(
            "Copie le trajet du %s (véhicule %d, %s → %s, %d km, %d km totaux, aller-retour : %s, montant : %s€) aux dates suivantes : %s.
            Format de réponse (JSON strict) :
            [{\"date\": \"YYYY-MM-DD\", \"depart\": \"...\", \"arrivee\": \"...\", \"km\": X, \"km_total\": X, \"is_return\": BOOLEAN, \"vehicule_id\": X, \"amount\": X, \"commentaire\": \"...\"}]",
            $trip['date'],
            $trip['vehicule_id'],
            $trip['depart'],
            $trip['arrivee'],
            $trip['km'],
            $trip['km_total'],
            $trip['is_return'] ? 'oui' : 'non',
            $trip['amount'],
            implode(', ', $targetDates)
        );
    }

    private function buildRepeatMonthlyPrompt(Report $report, array $params): string
    {
        $trip = $params['trip'];
        $monthWeeks = $this->getWeeksInMonth($report);

        return sprintf(
            "Répète le trajet du %s (véhicule %d, %s → %s, %d km, %d km totaux, aller-retour : %s, montant : %s€) chaque %s du mois (semaines %s).
            Format de réponse (JSON strict) :
            [{\"date\": \"YYYY-MM-DD\", \"depart\": \"...\", \"arrivee\": \"...\", \"km\": X, \"km_total\": X, \"is_return\": BOOLEAN, \"vehicule_id\": X, \"amount\": X, \"commentaire\": \"...\"}]",
            $trip['date'],
            $trip['vehicule_id'],
            $trip['depart'],
            $trip['arrivee'],
            $trip['km'],
            $trip['km_total'],
            $trip['is_return'] ? 'oui' : 'non',
            $trip['amount'],
            (new \DateTime($trip['date']))->format('l'),
            implode(', ', $monthWeeks)
        );
    }

    // Méthode mise à jour pour inclure tous les champs
    private function getTripsForWeek(Report $report, string $weekDate): array
    {
        $startDate = new \DateTime($weekDate);
        $endDate = (clone $startDate)->modify('+6 days');
        $trips = [];

        foreach ($report->getLines() as $line) {
            $tripDate = $line->getTravelDate();
            if ($tripDate >= $startDate && $tripDate <= $endDate) {
                $trips[] = [
                    'date' => $tripDate->format('Y-m-d'),
                    'depart' => $line->getStartAdress(),
                    'arrivee' => $line->getEndAdress(),
                    'km' => $line->getKm(),
                    'km_total' => $line->getKmTotal(),
                    'is_return' => $line->getIsReturn(),
                    'vehicule_id' => $line->getvehicule()->getId(),
                    'amount' => $line->getAmount(),
                    'commentaire' => $line->getComment(),
                ];
            }
        }

        return $trips;
    }

    private function getWeeksInMonth(Report $report): array
    {
        $start = (clone $report->getStartDate())->modify('first day of this month');
        $end = (clone $report->getEndDate())->modify('last day of this month');
        $weeks = [];

        for ($date = $start; $date <= $end; $date->modify('+1 week')) {
            $weeks[] = (int)$date->format('W');
        }

        return array_unique($weeks);
    }
}
