<?php
/**
 * This file is part of con4gis,
 * the gis-kit for Contao CMS.
 *
 * @package   	con4gis
 * @version    7
 * @author  	    con4gis contributors (see "authors.txt")
 * @license 	    LGPL-3.0-or-later
 * @copyright 	Küstenschmiede GmbH Software & Design
 * @link              https://www.con4gis.org
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
use Doctrine\DBAL\Connection;
use Contao\CoreBundle\Framework\ContaoFramework;
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

    public function __construct(Factory $escargotFactory, Filesystem $filesystem) {
        $this->escargotFactory = $escargotFactory;
        $this->filesystem = $filesystem;
    }

    public function afterImportBaseData(AfterImportEvent $event, $eventName, EventDispatcherInterface $eventDispatcher) {
        try {
            $this->rootDir = $rootDir = System::getContainer()->getParameter('kernel.project_dir');
            $importType = $event->getImportType();
            if ($importType == "gutesio") {
                $contentUpdate = new ChildFullTextContentUpdater();
                $contentUpdate->update();

                $database = Database::getInstance();
                $c4gSettings = $memberData = $database->prepare("SELECT * FROM tl_c4g_settings")->execute()->fetchAssoc();

                //Delete Search Index
                if ($c4gSettings['deleteSearchIndex'] == 1) {
                    //Delete Search Index
                    $database->prepare("TRUNCATE tl_search")->execute();
                    $database->prepare("TRUNCATE tl_search_index")->execute();
                }

                //Update Search Index
                if ($c4gSettings['updateSearchIndex'] == 1) {
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
                }
            }
        } catch (\Exception $e) {
            $event->setError("Fehler beim Erneuern/Löschen des Suchindexes. Mehr Informationen siehe Log.");
            C4gLogModel::addLogEntry('operator', 'Error while crawling: ' . $e);
        }
    }

    private function createLogger() {
        $handlers = [];

        if ($this->filesystem->exists($this->rootDir."/var/logs/gutesio_crawl_log.csv")) {
            $this->filesystem->remove($this->rootDir."/var/logs/gutesio_crawl_log.csv");
        }

        $csvDebugHandler = new CrawlCsvLogHandler($this->rootDir."/var/logs/gutes_crawl_log.csv", Logger::DEBUG);
        $handlers[] = $csvDebugHandler;

        $groupHandler = new GroupHandler($handlers);

        $logger = new Logger('gutesio-crawl-logger');
        $logger->pushHandler($groupHandler);

        return $logger;
    }
}