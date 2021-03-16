<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\Events\AfterImportEvent;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use Contao\CoreBundle\Crawl\Monolog\CrawlCsvLogHandler;
use Contao\System;
use gutesio\DataModelBundle\Classes\ChildFullTextContentUpdater;
use Monolog\Handler\GroupHandler;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Contao\CoreBundle\Crawl\Escargot\Factory;
use Contao\Database;
use Symfony\Component\Filesystem\Filesystem;
use Terminal42\Escargot\Escargot;
use Terminal42\Escargot\Queue\InMemoryQueue;

class AfterImportListener
{
    /**
     * @var Factory
     */
    private $escargotFactory;

    /**
     * @var Escargot
     */
    private $escargot;

    /**
     * @var Filesystem
     */
    private $filesystem;

    private $rootDir;

    public function __construct(Factory $escargotFactory, Filesystem $filesystem)
    {
        $this->escargotFactory = $escargotFactory;
        $this->filesystem = $filesystem;
    }

    public function afterImportBaseData(AfterImportEvent $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        try {
            System::loadLanguageFile("import");
            $this->rootDir = $rootDir = System::getContainer()->getParameter('kernel.project_dir');
            $importType = $event->getImportType();
            $createRobots = false;
            if ($importType == 'gutesio') {
                $contentUpdate = new ChildFullTextContentUpdater();
                $contentUpdate->update();

                $database = Database::getInstance();
                $c4gSettings = $memberData = $database->prepare('SELECT * FROM tl_c4g_settings')->execute()->fetchAssoc();

                //Delete Search Index
                if ($c4gSettings['deleteSearchIndex'] == 1) {
                    //Delete Search Index
                    $database->prepare('TRUNCATE tl_search')->execute();
                    $database->prepare('TRUNCATE tl_search_index')->execute();
                }

                //Update Search Index
                if ($c4gSettings['updateSearchIndex'] == 1) {
                    if (!$this->filesystem->exists($this->rootDir . '/web/robots.txt')) {
                        $this->filesystem->touch($this->rootDir . '/web/robots.txt');
                        $createRobots = true;
                    }
                    $subscribers = $this->escargotFactory->getSubscriberNames();
                    $queue = new InMemoryQueue();
                    $baseUris = $this->escargotFactory->getCrawlUriCollection();

                    $this->escargot = $this->escargotFactory->create($baseUris, $queue, $subscribers);

                    $this->escargot = $this->escargot
                        ->withLogger($this->createLogger())
                        ->withConcurrency(10)
                        ->withRequestDelay(0)
                        ->withMaxRequests(0)
                        ->withMaxDepth(0)
                    ;

                    $this->escargot->crawl();

                    if ($this->filesystem->exists($this->rootDir . '/web/robots.txt') && $createRobots) {
                        $this->filesystem->remove($this->rootDir . '/web/robots.txt');
                    }
                }
            }
        } catch (\Throwable $e) {
            if ($this->filesystem->exists($this->rootDir . '/web/robots.txt') && $createRobots) {
                $this->filesystem->remove($this->rootDir . '/web/robots.txt');
            }
            $event->setError($GLOBALS['TL_LANG']['import']['error_updating_index']);
            C4gLogModel::addLogEntry('operator', 'Error while crawling: ' . $e);
        }
    }

    private function createLogger()
    {
        $handlers = [];

        if ($this->filesystem->exists($this->rootDir . '/var/logs/gutesio_crawl_log.csv')) {
            $this->filesystem->remove($this->rootDir . '/var/logs/gutesio_crawl_log.csv');
        }

        $csvDebugHandler = new CrawlCsvLogHandler($this->rootDir . '/var/logs/gutesio_crawl_log.csv', Logger::DEBUG);
        $handlers[] = $csvDebugHandler;

        $groupHandler = new GroupHandler($handlers);

        $logger = new Logger('gutesio-crawl-logger');
        $logger->pushHandler($groupHandler);

        return $logger;
    }
}
