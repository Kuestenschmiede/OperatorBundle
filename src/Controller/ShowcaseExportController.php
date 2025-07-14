<?php

namespace gutesio\OperatorBundle\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Event\MenuEvent;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Menu\BackendMenuBuilder;
use Contao\System;
use gutesio\OperatorBundle\Classes\Services\ShowcaseExportService;
use gutesio\OperatorBundle\Form\ShowcaseExportType;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ShowcaseExportController.
 *
 * @Route(
 *     "%contao.backend.route_prefix%/showcase/export",
 *     name="gutesio\OperatorBundle\Controller\ShowcaseExportController",
 *     defaults={"_scope"="backend", "token_check"=false}
 * )
 */
class ShowcaseExportController extends AbstractController
{

    public function __construct(
        private ShowcaseExportService $showcaseExportService,
        private ContaoFramework $framework,
        private ContaoCsrfTokenManager $tokenManager,
        private BackendMenuBuilder $menuBuilder
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->framework->initialize();
        System::loadLanguageFile("default");

        $form = $this->createForm(
            ShowcaseExportType::class,
            ['REQUEST_TOKEN'=>$this->tokenManager->getDefaultTokenValue()],
            ['type_options' => $this->showcaseExportService->getTypeOptions()]
        );

        $form->handleRequest($request);

        if ($request->getMethod() === "POST") {

            $formResponse = $this->handleForm($form);

            if ($formResponse !== null) {
                return $formResponse;
            } else {
                // todo error message
            }
        }

        // TODO add menu to response
        $header = $this->menuBuilder->buildHeaderMenu();
        $mainMenu = $this->menuBuilder->buildMainMenu();

        return $this->render('@gutesioOperator/backend/showcase_export.html.twig', [
            'token' => $this->tokenManager->getDefaultTokenValue(),
            'types' => $this->showcaseExportService->getTypeOptions(),
            'form' => $form,
            'theme' => "flexible",
            'title' => "Schaufenster exportieren",
//            'version' => "1.0",
//            'menu' => true
        ]);
    }

    private function handleForm(FormInterface $form)
    {
        $formData = $form->getData();
        $selectedTypes = $formData['types'];

        if (count($selectedTypes) > 0) {
            $showcaseData = $this->showcaseExportService->createExportData($selectedTypes);

        } else {
            // TODO export all published types
            $showcaseData = [];
        }

        if ($showcaseData) {
            $headerFields = array_keys($showcaseData[0]);

            $csvData = [
                $headerFields,
                ...$showcaseData
            ];

            $fp = fopen("php://temp", "w");

            foreach ($csvData as $line) {
                fputcsv($fp, $line);
            }

            rewind($fp);
            $response = new Response(stream_get_contents($fp));
            fclose($fp);

            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="schaufenster.csv"');

            return $response;
        }

        return null;
    }
}