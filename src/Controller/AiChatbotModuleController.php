<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.
 * @package    gutesio
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */

namespace gutesio\OperatorBundle\Controller;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\ModuleModel;
use Contao\Template;
use gutesio\OperatorBundle\Classes\Services\AiChatbotService;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AiChatbotModuleController extends AbstractFrontendModuleController
{
    private AiChatbotService $aiChatbotService;

    public function __construct(AiChatbotService $aiChatbotService)
    {
        $this->aiChatbotService = $aiChatbotService;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        \con4gis\CoreBundle\Classes\ResourceLoader::loadCssResource("/bundles/gutesiooperator/dist/css/c4g_listing.min.css");
        
        $settings = \gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel::findSettings();
        $template->assistantName = ($settings ? $settings->aiAssistantName : 'KI') ?: 'KI';

        // Contao 5 doesn't use REQUEST_TOKEN by default for all POSTs, but some configurations might.
        // Also, aggressive output buffering to catch any unexpected output from anywhere.
        $action = $request->get('action');
        
        if ($action === 'ask_ai') {
            // Aggressive output buffering to catch any unexpected output from anywhere
            while (ob_get_level()) {
                ob_end_clean();
            }
            ob_start();
            
            try {
                $question = $request->get('question');
                $lat = $request->get('lat');
                $lon = $request->get('lon');
                
                $result = $this->aiChatbotService->getResponse($question, $lat, $lon);
                $answer = $result['answer'];
                $tiles = $result['tiles'] ?? [];
                
                // Ensure answer is a string and handle nulls
                $answer = (string)$answer;
                
                if (trim($answer) === "") {
                    $answer = "Die KI hat keine Antwort geliefert. Bitte prüfen Sie die Konfiguration und das System-Log.";
                }
                
                // Ensure UTF-8
                if (!mb_check_encoding($answer, 'UTF-8')) {
                    $answer = mb_convert_encoding($answer, 'UTF-8', 'UTF-8');
                }
                
                // Discard any captured output
                $capturedOutput = ob_get_clean();
                if ($capturedOutput !== "") {
                    C4gLogModel::addLogEntry("operator", "AI Chatbot suppressed unexpected output: " . $capturedOutput);
                }
                
                $response = new JsonResponse([
                    'answer' => $answer,
                    'tiles' => $tiles
                ]);
                // Set a cookie to verify this response in frontend if needed
                $response->headers->setCookie(new Cookie('ai_chatbot_active', '1', 0, '/', null, false, false, 'Lax'));
                $response->send();
                exit;
            } catch (\Throwable $e) {
                // Discard any captured output in case of error too
                if (ob_get_level()) {
                    ob_end_clean();
                }
                C4gLogModel::addLogEntry("operator", "AI Chatbot Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
                $response = new JsonResponse(['answer' => 'Ein interner Fehler ist aufgetreten: ' . $e->getMessage()], 500);
                $response->send();
                exit;
            }
        }

        return $template->getResponse();
    }
}
