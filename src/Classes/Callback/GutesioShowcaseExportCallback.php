<?php

namespace gutesio\OperatorBundle\Classes\Callback;

use gutesio\OperatorBundle\Classes\Services\ShowcaseExportService;

class GutesioShowcaseExportCallback
{


    public function __construct(private ShowcaseExportService $exportService)
    {
    }

    public function getExportButton(
        array $recordData,
        ?string $buttonHref,
        string $label,
        string $title,
        ?string $icon
    ) {
        $icon = "<img src='system/themes/flexible/icons/theme_export.svg' alt='Export' width='18' height='18'>";

        return "<a href='/contao/showcase/export?exportId=".$recordData['id']."' title='Schaufenster exportieren'>$icon</a>";

    }

    public function getTypeOptions()
    {
        return $this->exportService->getTypeOptions();
    }
}