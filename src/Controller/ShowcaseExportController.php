<?php

namespace gutesio\OperatorBundle\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Event\MenuEvent;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Menu\BackendMenuBuilder;
use Contao\Database;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use gutesio\OperatorBundle\Classes\Services\ShowcaseExportService;
use gutesio\OperatorBundle\Form\ShowcaseExportType;
use Knp\Menu\Renderer\ListRenderer;
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
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->framework->initialize();
        System::loadLanguageFile("default");
        System::loadLanguageFile("tl_gutesio_data_element");

        $exportId = $request->query->get("exportId");

        if (!$exportId) {
            // we need an export ID
            return new Response();
        }

        $exportResponse = $this->handleExport($exportId);

        if (null !== $exportResponse) {
            return $exportResponse;
        }

        // TODO show error feedback

        Message::addError("Beim Exportieren ist ein Fehler aufgetreten.");

        return new Response();
    }

    private function handleExport(string $exportId)
    {
        $exportData = Database::getInstance()->prepare("SELECT * FROM tl_gutesio_showcase_export WHERE `id` = ?")
            ->execute($exportId)->fetchAssoc();

        $exportName = $exportData['name'];
        $exportName = str_replace(" ", "_", $exportName);

        $selectedTypes = StringUtil::deserialize($exportData['types'], true);

        if (count($selectedTypes) > 0) {
            $showcaseData = $this->showcaseExportService->createExportData($selectedTypes);

        } else {
            // TODO export all published types
            $showcaseData = [];
        }

        if ($showcaseData) {

            $headerFields = [
                'Email',
                'Unternehmen',
                'Straße',
                'PLZ',
                'Ort',
                'Land',
                'Telefon',
                'Mobil'
            ];

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
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$exportName.'.csv"');

            return $response;
        }

        return null;
    }
}