<?php

namespace gutesio\OperatorBundle\Classes\Listener;

use Ausi\SlugGenerator\SlugGenerator;
use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Event\SitemapEvent;
use Contao\Database;
use Contao\ModuleModel;
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

    private function isOfferTypeinModule($category, $type) : bool
    {
        if ($category && $type) {
            $db = Database::getInstance();

            $moduleResult = $db->prepare("SELECT id FROM tl_module " .
                "WHERE type = 'offer_list_module' AND (".
                    "gutesio_child_data_mode = '0' OR ".
                    "(gutesio_child_data_mode = '1' AND gutesio_child_type LIKE '%".$type."%') OR ".
                    "(gutesio_child_data_mode = '2' AND gutesio_child_category LIKE '%".$category."%') OR ".
                    "gutesio_child_data_mode = '3')")->execute()->fetchAllAssoc();

            if ($moduleResult) {
                foreach ($moduleResult as $module) {
                    if ($module['id']) {
                        $result = $db->prepare("SELECT id FROM tl_content " .
                            "WHERE `type` = 'module' AND `module` = ? AND NOT `invisible` = '1'")->execute($module['id'])->fetchAllAssoc();
                        if ($result) {
                            //ToDo check visible parent (article)
                            //Todo check tl_layout modules
                            //ToDo check tags (3)
                            return true;
                        }
                    } else {
                        return false;
                    }
                }
            }
        }

        return false;
    }

    private function getOfferUrls(array $pageIds) : array
    {
        $db = Database::getInstance();
        $result = $db->prepare('SELECT c.uuid as uuid, t.type as type, t.uuid as category FROM tl_gutesio_data_child c ' .
            'JOIN tl_gutesio_data_child_type t ON c.typeId = t.uuid ' .
            'WHERE c.published = 1')->execute()->fetchAllAssoc();

        $urls = [];

        foreach ($pageIds as $pageId) {
            foreach ($result as $row) {
                if (!$this->isOfferTypeinModule($row['category'], $row['type'])) {
                    continue;
                }

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
                    case 'person':
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $page = $objSettings->personDetailPage;
                        break;
                    case 'voucher':
                        $objSettings = GutesioOperatorSettingsModel::findSettings();
                        $page = $objSettings->voucherDetailPage;
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

                $urls[$alias] = $this->combineUrl($page, $url, $alias);
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