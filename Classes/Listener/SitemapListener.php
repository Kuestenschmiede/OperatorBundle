<?php

namespace gutesio\OperatorBundle\Classes\Listener;

use Ausi\SlugGenerator\SlugGenerator;
use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use Contao\Controller;
use Contao\CoreBundle\Event\SitemapEvent;
use Contao\Database;
use Contao\PageModel;
use Contao\System;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;

class SitemapListener
{
    public function onCreateSitemap(SitemapEvent $event)
    {
        $document = $event->getDocument();
        $urlSet = $document->childNodes[0];

        $pageIds = $event->getRootPageIds();

        $urls = array_merge($this->getOfferUrls($pageIds), $this->getShowcaseUrls($pageIds));

        foreach ($urls as $url) {
            $location = $document->createElement('loc', $url);
            $element = $document->createElement('url');
            $element->appendChild($location);
            $urlSet->appendChild($element);
        }
    }

    private function getOfferUrls(array $pageIds) : array
    {
        $db = Database::getInstance();
        $result = $db->prepare('SELECT c.uuid as uuid, t.type as type FROM tl_gutesio_data_child c ' .
            'JOIN tl_gutesio_data_child_type t ON c.typeId = t.uuid ' .
            'where c.published = 1')->execute()->fetchAllAssoc();

        $urls = [];

        foreach ($pageIds as $pageId) {
            foreach ($result as $row) {
                switch ($row['type']) {
                    case 'product':
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $page = $objSettings->productDetailPage;
                        break;
                    case 'event':
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $page = $objSettings->eventDetailPage;
                        break;
                    case 'job':
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $page = $objSettings->jobDetailPage;
                        break;
                    case 'arrangement':
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $page = $objSettings->arrangementDetailPage;
                        break;
                    case 'service':
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $page = $objSettings->serviceDetailPage;
                        break;
                    default:
                        continue 2;
                }
                $parents = PageModel::findParentsById($page);
                if ($parents === null || count($parents) < 2 || (int)$parents[count($parents) - 1]->id !== (int)$pageId) {
                    continue;
                }
                $page = PageModel::findByPk($page);
                $url = $page->getAbsoluteUrl();
                $alias = strtolower(str_replace(['{', '}'], '', $row['uuid']));

                $urls[] = $this->combineUrl($page, $url, $alias);
            }
        }

        return $urls;
    }

    private function getShowcaseUrls(array $pageIds)
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("SELECT alias FROM tl_gutesio_data_element");
        $result = $stmt->execute()->fetchAllAssoc();

        $urls = [];

        foreach ($pageIds as $pageId) {
            foreach ($result as $res) {
                $objSettings = GutesioOperatorSettingsModel::findSettings();
                $parents = PageModel::findParentsById($objSettings->showcaseDetailPage);
                if ($parents === null || count($parents) < 2 || (int)$parents[count($parents) - 1]->id !== (int)$pageId) {
                    continue;
                }

                $page = PageModel::findByPk($objSettings->showcaseDetailPage);
                $url = $page->getAbsoluteUrl();
                $alias = $res['alias'];

                $urls[] = $this->combineUrl($page, $url, $alias);
            }
        }

        return $urls;
    }

    private function combineUrl(PageModel $page, string $url, string $alias) : string
    {
        if (System::getContainer()->getParameter('contao.legacy_routing')) {
            $urlSuffix = System::getContainer()->getParameter('contao.url_suffix');
        } else {
            $page->loadDetails();
            $urlSuffix = $page->urlSuffix;
        }

        if ($urlSuffix !== '') {
            return str_replace($urlSuffix, '/' . $alias . $urlSuffix, $url);
        } else {
            return $url . '/' . $alias;
        }
    }
}