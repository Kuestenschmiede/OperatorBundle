<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright (c) 2010-2026, by Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\Events\AfterImportEvent;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use Contao\CoreBundle\Crawl\Monolog\CrawlCsvLogHandler;
use con4gis\MapsBundle\Classes\Caches\C4GMapsAutomator;
use Contao\System;
use gutesio\DataModelBundle\Classes\Cache\ImageCache;
use gutesio\DataModelBundle\Classes\ChildFullTextContentUpdater;
use gutesio\DataModelBundle\Classes\LocstyleRepairService;
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
            System::loadLanguageFile('import');
            $this->rootDir = $rootDir = System::getContainer()->getParameter('kernel.project_dir');
            $importType = $event->getImportType();
            $createRobots = false;
            if ($importType == 'gutesio') {
                $importData = $this->getLatestImportData();
                $repair = new LocstyleRepairService();
                $repair->repair($importData);

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
                    if (!$this->filesystem->exists($this->rootDir . '/public/robots.txt')) {
                        $this->filesystem->touch($this->rootDir . '/public/robots.txt');
                        $createRobots = true;
                    }
                    $subscribers = $this->escargotFactory->getSubscriberNames();
                    $queue = new InMemoryQueue();
                    $baseUris = $this->escargotFactory->getCrawlUriCollection();

                    $this->escargot = $this->escargotFactory->create($baseUris, $queue, $subscribers);

                    $this->escargot = $this->escargot
                        ->withLogger($this->createLogger())
                        ->withConcurrency(5) //10
                        ->withRequestDelay(0)
                        ->withMaxRequests(20) //0
                        ->withMaxDepth(10) //0
                    ;

                    $this->escargot->crawl();

                    if ($this->filesystem->exists($this->rootDir . '/public/robots.txt') && $createRobots) {
                        $this->filesystem->remove($this->rootDir . '/public/robots.txt');
                    }
                }
                $automator =  new C4GMapsAutomator();
                $automator->purgeMapApiCache();

                $rootDir = \Contao\System::getContainer()->getParameter('kernel.project_dir');
                $localCachePath = $rootDir.'/files/con4gis_import_data/images';
                ImageCache::purgeCache($localCachePath);
            }
        } catch (\Throwable $e) {
            if ($this->filesystem->exists($this->rootDir . '/public/robots.txt') && $createRobots) {
                $this->filesystem->remove($this->rootDir . '/public/robots.txt');
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

        $csvDebugHandler = new CrawlCsvLogHandler($this->rootDir . '/var/logs/gutesio_crawl_log.csv', Logger::INFO);
        $handlers[] = $csvDebugHandler;

        $groupHandler = new GroupHandler($handlers);

        $logger = new Logger('gutesio-crawl-logger');
        $logger->pushHandler($groupHandler);

        return $logger;
    }

    /**
     * Tries to find and load the latest import JSON data.
     */
    private function getLatestImportData(): ?array
    {
        try {
            $db = Database::getInstance();
            $latestImport = $db->prepare("
                SELECT importUuid, importTables, type 
                FROM tl_c4g_import_data 
                WHERE type = 'gutesio' 
                ORDER BY tstamp DESC 
                LIMIT 1
            ")->execute()->fetchAssoc();

            if (!$latestImport || !$latestImport['importUuid']) {
                return null;
            }

            $uuid = $latestImport['importUuid'];
            $shortUuid = substr($uuid, 0, -5);
            $rootDir = System::getContainer()->getParameter('kernel.project_dir');
            
            // Try to find the JSON file in the typical import data folder
            $importDir = $rootDir . '/files/con4gis_import_data/' . $shortUuid;
            if (is_dir($importDir)) {
                $files = glob($importDir . '/data/*.json');
                if (!empty($files)) {
                    $jsonFile = $files[0];
                    $content = file_get_contents($jsonFile);
                    if ($content) {
                        $data = json_decode($content, true);
                        if (is_array($data)) {
                            C4gLogModel::addLogEntry('operator', "Locstyle Repair: Import-JSON für UUID $uuid geladen.");
                            return $data;
                        }
                    }
                }
            }
            
            // Fallback: search in io-data cache
            $ioDataDir = $rootDir . '/files/con4gis_import_data/io-data';
            if (is_dir($ioDataDir)) {
                $files = glob($ioDataDir . '/*/data/*.json');
                if (!empty($files)) {
                    // Sort by file time to get the newest
                    usort($files, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $content = file_get_contents($files[0]);
                    if ($content) {
                        $data = json_decode($content, true);
                        if (is_array($data)) {
                            C4gLogModel::addLogEntry('operator', "Locstyle Repair: Import-JSON aus Cache geladen (" . basename($files[0]) . ").");
                            return $data;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            C4gLogModel::addLogEntry('operator', 'Error loading latest import data for repair: ' . $e->getMessage());
        }

        return null;
    }
}
