<?php

namespace gutesio\OperatorBundle\Classes\Maintenance;

use gutesio\DataModelBundle\Classes\ChildFullTextContentUpdater;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class ChildFullTextUpdater
{
    public function updateFulltextIndex(): void
    {
        $updater = new ChildFullTextContentUpdater();

        $updater->update();
    }
}