<?php

namespace gutesio\OperatorBundle\Classes\Cron;

use gutesio\OperatorBundle\Classes\Services\AiKnowledgeService;
use Contao\CoreBundle\Framework\ContaoFramework;

class AiKnowledgeCron
{
    public function __construct(
        private ContaoFramework $framework,
        private AiKnowledgeService $knowledgeService
    ) {
    }

    public function onDaily()
    {
        $this->framework->initialize();
        
        $settings = \gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel::findSettings();
        if ($settings && $settings->aiEnabled && $settings->aiApiKey && $settings->aiAssistantId) {
            $this->knowledgeService->runKnowledgeUpdate();
        }
    }
}
