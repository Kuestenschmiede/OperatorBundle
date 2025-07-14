<?php

namespace gutesio\OperatorBundle\Classes\Listener;

use Contao\CoreBundle\Event\MenuEvent;
use gutesio\OperatorBundle\Controller\ShowcaseExportController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class BackendMenuListener
{
    protected $router;
    protected $requestStack;

    public function __construct(RouterInterface $router, RequestStack $requestStack)
    {
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    public function __invoke(MenuEvent $event): void
    {
        $factory = $event->getFactory();
        $tree = $event->getTree();

        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        $contentNode = $tree->getChild('gutesio');

        $node = $factory
            ->createItem('showcase-export')
            ->setUri($this->router->generate(ShowcaseExportController::class))
            ->setLabel('Schaufenster exportieren')
            ->setLinkAttribute('title', 'Schaufenster exportieren')
            ->setLinkAttribute('class', 'showcase-export')
            ->setCurrent($this->requestStack->getCurrentRequest()->get('_controller') === ShowcaseExportController::class)
        ;

        $contentNode->addChild($node);
    }
}