<?php

namespace App\Controller\App;

use App\Entity\Report;
use App\Entity\ReportLine;
use App\Entity\Vehicule;
use App\Entity\VehiculesReport;
use App\Form\AssistantAIType;
use App\Form\ReportDuplicateType;
use App\Form\ReportLineType;
use App\Form\ReportTotalScaleType;
use App\Service\MistralApiService;
use App\Service\XlsxExporter;
use App\Utils\ReportPdf;
use App\Validator\Constraints\NewReport;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;

use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;

use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;


class ReportAppCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;
    private $exporter;
    private $slugger;
    private $mistral;

    public function __construct(
        \EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator $adminUrlGenerator,
        XlsxExporter $exporter,
        SluggerInterface $slugger,
        MistralApiService $mistral
    ) {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->exporter = $exporter;
        $this->slugger = $slugger;
        $this->mistral = $mistral;
    }

    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addHtmlContentToBody(
            '<script src="https://maps.googleapis.com/maps/api/js?key=' . $_ENV['GOOGLE_MAPS_API_KEY'] . '&libraries=places"></script>'
        );
    }

    public function configureResponseParameters(KeyValueStore $parameters): KeyValueStore
    {
        $context = $this->getContext();
        $new = parent::configureResponseParameters($parameters);

        if ($new->get('pageName') === Crud::PAGE_INDEX) {
            $new = $this->generateFooterLine($new, $context);
        }

        return $new;
    }

    public function index(AdminContext $context)
    {
        if (!$this->getUser()->getSubscription()->isValid()) {
            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(UserAppCrudController::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl()
            );
        }

        if (!$this->getUser()->hasCompletedSetup()) {
            return $this->redirectToRoute('app', ['menuIndex' => 0, 'submenuIndex' => -1]);
        }

        return parent::index($context);
    }

    public function createEntity(string $entityFqcn)
    {
        $report = new $entityFqcn();
        $report->setUser($this->getUser());
        return $report;
    }

    public function createIndexQueryBuilder(
        SearchDto $search,
        EntityDto $entity,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        return parent::createIndexQueryBuilder($search, $entity, $fields, $filters)
            ->andWhere('entity.user = :user')
            ->setParameter('user', $this->getUser());
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['start_date' => 'ASC'])
            ->setPageTitle('index', 'Reports')
            ->setPageTitle('edit', fn (Report $r) => sprintf('Modifier le rapport de %s', $r->getPeriod()))
            ->setPageTitle('new', 'New report period')

            ->overrideTemplate('crud/index', 'App/Report/index.html.twig')
            ->overrideTemplate('crud/edit', 'App/advanced_edit.html.twig')
            ->overrideTemplate('crud/new', 'App/advanced_new.html.twig')
            ->overrideTemplate('crud/filters', 'App/Report/filters.html.twig')

            ->addFormTheme('App/Report/form_theme.html.twig')

            ->setFormOptions(
                ['validation_groups' => ['new','default']],
                ['validation_groups' => ['default','edit']]
            );
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);

        $generatePdf = Action::new('generatePdf')
            ->setIcon("fa fa-file-pdf")
            ->setLabel("PDF")
            ->linkToCrudAction('generatePdf');

        $duplicateAction = Action::new('duplicate', 'Duplicate')
            ->linkToCrudAction('duplicateReport')
            ->setIcon("fa fa-copy")
            ->setCssClass("duplicate-report-action");

        $exportXls = Action::new('exportXls', 'Excel')
            ->linkToCrudAction('exportXls')
            ->setIcon("fa fa-file-excel");

            
        $assistantAI = Action::new('assistantAI', 'Assistant IA')
            ->setIcon('fa-solid fa-wand-magic-sparkles')
            ->linkToCrudAction('assistantAI')
            ->setCssClass('btn btn-secondary')
        ;    

        $isManager = $this->isGranted('ROLE_MANAGER') || $this->isGranted('ROLE_PREVIOUS_ADMIN');

        $actions
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_RETURN)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->add(Crud::PAGE_NEW, Action::SAVE_AND_CONTINUE)
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_CONTINUE, fn(Action $a) =>
                $a->setIcon("fa-solid fa-arrow-right")
                ->setLabel("Next")
                ->setCssClass('btn btn-primary suivant')
            )

            ->update(Crud::PAGE_INDEX, Action::NEW, fn(Action $a) =>
                $a->setCssClass('btn btn-primary new-report-action')
            )
            ->add(Crud::PAGE_INDEX, $generatePdf)
            ->add(Crud::PAGE_INDEX, $exportXls)
        ;

        // Duplicate: uniquement pour les managers
        if ($isManager) {
            // Assistant IA: présent sur la page EDIT
            $actions->add(Crud::PAGE_EDIT, $assistantAI);
        }

        return $actions;

    }

    public function assistantAI(AdminContext $context): Response
    {
        $report = $context->getEntity()->getInstance();
        $request = $context->getRequest();

        if ($report->getUser() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }

        $form = $this->createForm(AssistantAIType::class, null, [
            'report' => $report,
        ]);

        $form->handleRequest($request);

        $backUrl = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($report->getId())
                ->generateUrl()
        ;

        $actionUrl = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction('assistantAI')
                ->setEntityId($report->getId())
                ->generateUrl()
            ;
                
        if ($form->isSubmitted() && $form->isValid()) {
           
            $actionType = $form->get('prompt_type')->getData(); // Récupéré depuis le formulaire
            $parameters = json_decode($form->get('parameters')->getData(), true);

            $previewTrips = $this->mistral->generateTrips($actionType, $report, $parameters);

            if ($request->isXmlHttpRequest()) {
                $bulkCreateActionUrl = $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction('bulkCreateLines')
                    ->setEntityId($report->getId())
                    ->generateUrl()
                ;

                return new Response(
                    $this->renderView('App/Report/_assistant_preview_content.html.twig', [
                        'previewTrips' => $previewTrips,
                        'report' => $report,
                        'bulkCreateActionUrl' => $bulkCreateActionUrl,
                        'backUrl' => $backUrl,
                    ])
                );
            }

            /*return $this->render('App/Report/assistant_preview.html.twig', [
                'prompt' => $prompt,
                'form' => $form->createView(),
                'previewTrips' => $previewTrips,
                'backUrl' => $backUrl
            ]);*/
        }

        

        return $this->render('App/Report/assistant.html.twig', [
            'form' => $form->createView(),
            'report' => $report,
            'backUrl' => $backUrl,
            'actionUrl' => $actionUrl

        ]);
    }

    public function bulkCreateLines(AdminContext $context): RedirectResponse
    {
        $report = $context->getEntity()->getInstance();
        $request = $context->getRequest();

        $backUrl = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($report->getId())
                ->generateUrl()
        ;

        $tripsData = json_decode($request->request->get('trips'), true);

        if (empty($tripsData)) {
            $this->addFlash('error', 'Aucun trajet à créer.');
            return $this->redirect($backUrl);
        }

        $entityManager = $this->container->get('doctrine')->getManagerForClass(Report::class);
        $vehicleRepository = $entityManager->getRepository(Vehicule::class);

        foreach ($tripsData as $tripData) {
            $trip = new ReportLine();
            $trip->setTravelDate(new \DateTime($tripData['date']))
                ->setStartAdress($tripData['depart'])
                ->setEndAdress($tripData['arrivee'])
                ->setKm($tripData['km'])
                ->setKmTotal($tripData['km_total'])
                ->setIsReturn($tripData['is_return'])
                ->setVehicule($vehicleRepository->find($tripData['vehicule_id']))
                ->setAmount($tripData['amount'])
                ->setComment($tripData['commentaire'])
                ->setReport($report)
            ;

            $entityManager->persist($trip);
            //$report->addLine($trip);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Les trajets ont été crées avec succès.');
        
        return $this->redirect($backUrl);
    }


    public function edit(AdminContext $context)
    {
        if ($context->getEntity()->getInstance()->getUser() !== $this->getUser()) {
            throw new AccessDeniedHttpException();
        }
        return parent::edit($context);
    }

    public function generatePdf(AdminContext $context)
    {
        $report = $context->getEntity()->getInstance();
        $pdf = new ReportPdf();
        $period = [$report->getStartDate()->format('F'), $report->getStartDate()->format('Y')];

        $pdfContent = $pdf->generatePdf([$report], $period, 'month');

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->generateFilename().'"'
        ]);
    }

    public function generatePdfPerYear(AdminContext $context)
    {
        $entityManager = $this->container->get('doctrine')->getManagerForClass(Report::class);

        $period = $context->getRequest()->query->all()['filters']["Period"]['value'] ?? false;
        if(!$period)
        {
            throw new \Exception("Période non valide.");
        }

        $period = explode(" -> ",$period);
        $reports = $entityManager->getRepository(Report::class)->findByPeriod($period[0],$period[1]);

        if (!$reports) {
            throw new \Exception("Aucun rapport trouvé pour cette année fiscale.");
        }

        // Génération PDF
        $pdf = new ReportPdf();
        $pdfContent = $pdf->generatePdf($reports,$period,'year');

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->generateFilename().'"'
        ]);
    }


    public function scaleChangeForYear(AdminContext $context)
    {
        $entityManager = $this->container->get('doctrine')->getManagerForClass(Report::class);

        $periodFilter = $context->getRequest()->query->all()['filters']["Period"]['value'] ?? false;

        if(!$periodFilter)
        {
            throw new \Exception("Période non valide.");
        }

        $period = explode(" -> ",$periodFilter);
        $start = $period[0];
        $end = $period[1];

        $reports = $entityManager->getRepository(Report::class)->findByPeriod($start,$end);

        if (!$reports) {
            throw new \Exception("Aucun rapport trouvé pour cette année fiscale.");
        }

        // Récupération du VR ciblé
        $vrid = $context->getRequest()->query->get('vrid') ?? false;
        $vehiculesReport = $entityManager->getRepository(VehiculesReport::class)->find($vrid);

        if (!$vehiculesReport) {
            throw new \Exception("Rapport introuvable.");
        }

        // Chargement des choix possibles
        $choices = $this->getChoicesForVehiculeReport($vehiculesReport);

        $form = $this->createForm(ReportTotalScaleType::class, $vehiculesReport, [
            'choices' => $choices
        ]);

        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {

            $vehiculesReport = $form->getData();
            $newScale = $vehiculesReport->getScale();
            $vehicle = $vehiculesReport->getVehicule();
            $reportsToUpdate = [];

            // Mise à jour de tous les rapports de l'année fiscale
            foreach ($reports as $report) 
            {
                foreach ($report->getVehiculesReports() as $vr) 
                {
                    if ($vr->getVehicule() === $vehicle) {
                        $vr->setScale($newScale);
                        $reportsToUpdate[] = $report;
                    }
                }
            }
            $entityManager->flush();

            //force le recalcul a posteriori des IK des rapports concernés selon nouveau barème
            foreach($reportsToUpdate as $reportToUpdate)
            {
                $reportToUpdate->setUpdatedAt(new \DateTime());
            }
            $entityManager->flush();

            $url = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->set("filters[Period][value]", $periodFilter)
                ->generateUrl()
                ;

            return $this->redirect($url);
        }
    }


    public function generateFooterLine(KeyValueStore $params, AdminContext $context)
    {
        $paginator = $params->get('paginator');
        $reports = $paginator->getResults();

        $totals = ['km' => 0, 'amount' => 0];
        $vehiculesTotals = [];

        foreach ($reports as $report) {
            $totals['km'] += $report->getVehiculesReportsTotalKm();
            $totals['amount'] += $report->getVehiculesReportsTotalAmount();

            foreach ($report->getVehiculesReports() as $vr) {
                $vid = $vr->getVehicule()->getId();

                if (!isset($vehiculesTotals[$vid])) {
                    $vehiculesTotals[$vid] = [
                        'Vehicule' => $vr->getVehicule(),
                        'Scale' => $vr->getScale(),
                        'Vr' => $vr,
                        'km' => 0,
                        'amount' => 0
                    ];
                }

                $vehiculesTotals[$vid]['km'] += $vr->getKm();
                $vehiculesTotals[$vid]['amount'] += $vr->getTotal();
            }
        }

        /* Formulaires mis à jour */
        foreach ($vehiculesTotals as $vid => $data) {

            $vr = $data['Vr'];
            $choices = $this->getChoicesForVehiculeReport($vr);

            $form = $this->createForm(ReportTotalScaleType::class, $vr, [
                'choices' => $choices,
                'action' => $this->adminUrlGenerator
                    ->setAction('scaleChangeForYear')
                    ->set('vrid', $vr->getId())
                    ->generateUrl()
            ]);

            $vehiculesTotals[$vid]['form'] = $form->createView();

            /* Alertes mini + maxi */
            if ($data['Scale']->getKmMax() > '') {
                $kmMax = $data['Scale']->getKmMax();
                $kmMin = $data['Scale']->getKmMin();

                if ($data['km'] >= $kmMax) {
                    $vehiculesTotals[$vid]['warning'] =
                        'La distance totale dépasse celle du barème sélectionné';
                }

                if ($data['km'] <= $kmMin) {
                    $vehiculesTotals[$vid]['info'] =
                        'La distance totale est actuellement en-dessous du seuil du barème sélectionné';
                }
            }
        }

        $params->set('totals', $totals);
        $params->set('vehiculesTotals', $vehiculesTotals);

        return $params;
    }

    public function duplicateReport(
        AdminContext $context,
        Request $request,
        EntityManagerInterface $em,
        FormFactoryInterface $formFactory,
        \EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator $adminUrlGenerator,
        ValidatorInterface $validator
    ): Response {
        /** @var Report $original */
        $original = $context->getEntity()->getInstance();
        $defaultData = [
            'year' => $original->getStartDate()->format('Y')
        ];

        $form = $formFactory->create(ReportDuplicateType::class, $defaultData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $year = $data['year'];
            $month = $data['month'];

            $start = new \DateTimeImmutable("$year-$month-01");
            $end = $start->modify('last day of this month');

            $new = new Report();
            $new->setStartDate($start);
            $new->setEndDate($end);
            $new->setUser($original->getUser());

            /* Validation */
            $errors = $validator->validate($new, new NewReport());
            if (count($errors) > 0) {
                return $this->render('App/Report/duplicate_form.html.twig', [
                    'form' => $form->createView(),
                    'original' => $original,
                    'errors' => $errors,
                ]);
            }

            $em->persist($new);
            $em->flush();

            /* Clone des lignes */
            foreach ($original->getLines() as $line) {

                $newLine = new ReportLine();
               // dd($original->getLines());
                $newLine->setKm($line->getKm());
                $newLine->setIsReturn($line->getIsReturn());
                $newLine->setKmTotal($line->getKmTotal());
                $newLine->setAmount($line->getAmount());
                $newLine->setStartAdress($line->getStartAdress());
                $newLine->setEndAdress($line->getEndAdress());
                $newLine->setComment($line->getComment());
                $newLine->setVehicule($line->getVehicule());
                $newLine->setScale($line->getScale());

                /* Ajustement de la date */
                $adjusted = $line->getTravelDate()
                    ->setDate(
                        $year,
                        $month,
                        min($line->getTravelDate()->format('d'), $end->format('d'))
                    );

                $newLine->setTravelDate($adjusted);
                $newLine->setReport($new);

                $em->persist($newLine);
                $em->flush();
            }

            /* Redirection vers EDIT du report dupliqué */
            $url = $adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($new->getId())
                ->generateUrl();

            return $this->redirect($url);
        }

        return $this->render('App/Report/duplicate_form.html.twig', [
            'form' => $form->createView(),
            'original' => $original,
        ]);
    }

    public function exportXls(AdminContext $context): Response
    {
        $report = $context->getEntity()->getInstance();

        $rows = [];
        foreach ($report->getLines() as $line) {
            $rows[] = [
                'Véhicule' => $line->getVehicule(),
                'Date' => $line->getTravelDate()->format('d/m/Y'),
                'Départ' => $line->getStartAdress(),
                'Arrivé' => $line->getEndAdress(),
                'Motif' => str_replace('<br />', '\n', $line->getComment()),
                'Distance' => $line->getKmTotal(),
            ];
        }

        $slug = $this->slugger->slug(
            $report->getUser().'_'.$report->getStartDate()->format('m-Y')
        )->lower();

        $fileName = sprintf('Fiche_kilometrique_%s.xlsx', $slug);

        return $this->exporter->export($rows, $fileName, 'xlsx');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(\App\Controller\App\Filter\ReportYearFilter::new('Period'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addRow();

        yield DateField::new('start_date', 'Period')
            ->onlyOnIndex()
            ->setFormat('MMMM y');

        if ($pageName === Crud::PAGE_EDIT) {

            yield DateField::new('start_date', 'Date de début')
                ->setFormTypeOptions(['attr' => ['class' => 'report_start_date']])
                ->onlyOnForms()
                ->hideWhenCreating();

            yield DateField::new('end_date', 'Date de fin')
                ->setFormTypeOptions(['attr' => ['class' => 'report_end_date']])
                ->onlyOnForms()
                ->hideWhenCreating();

            yield CollectionField::new('lines', 'Trajet(s)')
                ->setEntryType(ReportLineType::class)
                ->allowDelete(true)
                ->hideWhenCreating()
                ->setFormTypeOptions(['attr' => ['class' => 'lines']])
                ->hideOnIndex();
        }

        if ($pageName === Crud::PAGE_NEW) {

            yield DateField::new('Year', 'Année')
                ->renderAsChoice()
                ->onlyOnForms()
                ->hideWhenUpdating()
                ->setFormTypeOptions([
                    'required' => true,
                    'years' => range(date('Y') - 4, date('Y') + 1),
                ]);

            yield ChoiceField::new('Period', 'Mois')
                ->setChoices([
                    'Janvier' => 'January',
                    'Février' => 'February',
                    'Mars' => 'March',
                    'Avril' => 'April',
                    'Mai' => 'May',
                    'Juin' => 'June',
                    'Juillet' => 'July',
                    'Août' => 'August',
                    'Septembre' => 'September',
                    'Octobre' => 'October',
                    'Novembre' => 'November',
                    'Décembre' => 'December'
                ])
                ->onlyOnForms()
                ->hideWhenUpdating()
                ->setFormTypeOptions(['required' => true]);
        }

        if ($pageName === Crud::PAGE_INDEX) {
            yield CollectionField::new('lines', 'Trajet(s)')
                ->setTemplatePath('App/Report/lines.html.twig')
                ->onlyOnIndex();
        }

        yield FormField::addRow();

        yield IntegerField::new('km', 'Distance totale (km)')
            ->setFormTypeOptions(['attr' => [
                'readonly' => true,
                'class' => 'km bg-light fw-bold'
            ]])
            ->hideWhenCreating()
            ->hideOnIndex()
            ->setColumns('col-3');

        yield IntegerField::new('km', 'Distance')
            //->setNumberFormat('%s km')
            ->onlyOnIndex();

        yield NumberField::new('total', 'Montant Total (€)')
            ->setFormTypeOptions(['attr' => [
                'readonly' => true,
                'class' => 'total bg-light fw-bold'
            ]])
            ->hideWhenCreating()
            ->hideOnIndex()
            ->setNumberFormat('%s €');

        yield MoneyField::new('total', 'Montant')
            ->setCurrency('EUR')
            
            ->setStoredAsCents(false)
            ->onlyOnIndex();
    }

    public function delete(AdminContext $context)
    {
        /** @var Report $report */
        $report = $context->getEntity()->getInstance();
        $entityManager = $this->container->get('doctrine')->getManagerForClass(Report::class);
       
        $reportYear = $report->getStartDate()->format('Y');
        $currentYear = (new \DateTime())->format('Y');
        $user = $report->getUser();

        try {
            parent::delete($context);
        } catch (\Exception $e) {
            // gérer l'erreur si la suppression échoue
            throw $e;
        }

        $remainingReports = $entityManager->getRepository(Report::class)
            ->createQueryBuilder('r')
            ->select('count(r.id)')
            ->where('r.user = :user')
            ->andWhere('YEAR(r.start_date) = :year')
            ->setParameter('user', $user)
            ->setParameter('year', $reportYear)
            ->getQuery()
            ->getSingleScalarResult();

        if ($reportYear !== $currentYear && $remainingReports == 0) {
            $targetPeriod = "Jan $currentYear -> Dec $currentYear";

            // On génère l'URL vers l'index de l'année courante
            $url = $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->set('filters[Period][value]', $targetPeriod)
                // on supprime le referrer : empêche que EasyAdmin renvoye sur l'ID qui n'existe plus
                ->unset('referrer')
                ->setEntityId(null) 
                ->generateUrl();

            return $this->redirect($url);
        }

        return $this->redirect($this->adminUrlGenerator
            ->setAction(Action::INDEX)
            ->unset('referrer')
            ->generateUrl()
        );
    }

    public function getChoicesForVehiculeReport(VehiculesReport $vehiculesReport)
    {
        $choices = [];

        foreach ($vehiculesReport->getVehicule()->getPower()->getScales() as $scale) {

            // On affiche toutes les versions valides du barème dans l'année fiscale
            if (
                $vehiculesReport->getScale()->getYear() == $scale->getYear()
                || $vehiculesReport->getVehicule()->getScale()->getYear() == $scale->getYear()
            ) {
                $powerLabel = (string) $scale->getPower() . ' (' . $scale->getYear() . ')';
                $choices[$powerLabel][$scale->__toString()] = $scale;
            }
        }

        ksort($choices);
        return $choices;
    }
}
