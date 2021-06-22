<?php

namespace gutesio\OperatorBundle\Classes\Cron;

use Contao\Database;

class SendStatisticDataCron
{
    private $offerStatisticIds = [];

    private $showcaseStatisticIds = [];

    const MAX_TRANSFER_DATA = 500;

    public function onHourly()
    {
        $objSettings = \con4gis\CoreBundle\Resources\contao\models\C4gSettingsModel::findSettings();
        $statisticUrl = rtrim($objSettings->con4gisIoUrl, '/') . '/saveStats.php';
        $statisticUrl .= '?key=' . $objSettings->con4gisIoKey;
        $data = [];
        $data['data'] = $this->getStatisticData();
        $data['domain'] = $_SERVER['SERVER_NAME'];
        $request = new \Contao\Request();
        $request->method = 'POST';
        $request->data = $data;
        if ($_SERVER['HTTP_REFERER']) {
            $request->setHeader('Referer', $_SERVER['HTTP_REFERER']);
        }
        if ($_SERVER['HTTP_USER_AGENT']) {
            $request->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT']);
        }
        $request->send($statisticUrl);
        $response = $request->response;
        $success = json_decode($response)['success'];
        if ($success) {
            $db = Database::getInstance();
            if (count($this->offerStatisticIds) > 0) {
                $offerStatisticIdString = '(' . implode(',', $this->offerStatisticIds) . ')';
                $db->prepare('UPDATE tl_gutesio_offer_statistic SET `transferred` = 1 WHERE `id` IN ' . $offerStatisticIdString)
                    ->execute();
            }
            if (count($this->showcaseStatisticIds) > 0) {
                $showcaseStatisticIdString = '(' . implode(',', $this->showcaseStatisticIds) . ')';
                $db->prepare('UPDATE tl_gutesio_showcase_statistic SET `transferred` = 1 WHERE `id` IN ' . $offerStatisticIdString)
                    ->execute();
            }
        }
    }

    private function getStatisticData()
    {
        $db = Database::getInstance();

        $offerStatistic = $db->prepare('SELECT * FROM tl_gutesio_offer_statistic WHERE `transferred` = 0')
            ->execute()->fetchAllAssoc();
        $showcaseStatistic = $db->prepare('SELECT * FROM tl_gutesio_showcase_statistic WHERE `transferred` = 0')
            ->execute()->fetchAllAssoc();

        $dataCtr = 0;
        if ($offerStatistic) {
            foreach ($offerStatistic as $statisticEntry) {
                $proxyData[] = [
                    'proxyKey' => 'offerStatistic_' . $statisticEntry['id'],
                    'proxyData' => $statisticEntry['uuid'] . ',' . $statisticEntry['date'] . ',' . $statisticEntry['offerId'] . ',' . $statisticEntry['visits'] . ',' . $statisticEntry['ownerId'],
                ];
                $dataCtr++;
                if ($dataCtr >= self::MAX_TRANSFER_DATA) {
                    break;
                }
                $this->offerStatisticIds[] = $statisticEntry['id'];
            }
        }
        if ($showcaseStatistic && $dataCtr < self::MAX_TRANSFER_DATA) {
            foreach ($showcaseStatistic as $statisticEntry) {
                $proxyData[] = [
                    'proxyKey' => 'showcaseStatistic_' . $statisticEntry['id'],
                    'proxyData' => $statisticEntry['uuid'] . ',' . $statisticEntry['date'] . ',' . $statisticEntry['offerId'] . ',' . $statisticEntry['visits'] . ',' . $statisticEntry['ownerId'],
                ];
                $dataCtr++;
                if ($dataCtr >= self::MAX_TRANSFER_DATA) {
                    break;
                }
                $this->showcaseStatisticIds[] = $statisticEntry['id'];
            }
        }

        return $proxyData;
    }
}
