<?php
// src/Service/ReportService.php
namespace App\Service;

use App\Entity\Report;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ReportService
{
    private $entityManager;
    private $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function getAvailablePeriodsForReportDuplication(Report $sourceReport): array
    {
        $user = $this->security->getUser();
        $availablePeriods = [];
        $existingReports = $this->entityManager->getRepository(Report::class)->findBy(['user' => $user]);

        // Générer une liste des périodes déjà utilisées (format : YYYY-MM|reportId)
        $usedPeriods = [];
        foreach ($existingReports as $report) {
            if ($report->getStartDate()) {
                $usedPeriods[] = $report->getStartDate()->format('Y-m') . '|' . $report->getId();
            }
        }

        // Générer une liste des périodes disponibles (années et mois sans rapport existant)
        $currentYear = (int)date('Y');
        $periodsByYear = [];
        for ($year = $currentYear - 3; $year <= $currentYear + 1; $year++) {
            $yearPeriods = [];
            for ($month = 1; $month <= 12; $month++) {
                $periodKey = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);

                // Vérifier si la période est déjà utilisée (et exclure le rapport source)
                $isUsed = false;
                foreach ($usedPeriods as $usedPeriod) {
                    [$usedYearMonth, $reportId] = explode('|', $usedPeriod);
                    if ($usedYearMonth === $periodKey && $reportId !== $sourceReport->getId()) {
                        $isUsed = true;
                        break;
                    }
                }

                if (!$isUsed) {
                    $yearPeriods[$this->getMonthName($month).' '.$year] = $periodKey;
                }
            }

            if (!empty($yearPeriods)) {
                $availablePeriods[$year] = $yearPeriods;
            }
        }

        return $availablePeriods;
    }

    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        return $months[$month] ?? '';
    }



}
