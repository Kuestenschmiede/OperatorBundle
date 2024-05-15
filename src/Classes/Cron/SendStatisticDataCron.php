<?php

namespace gutesio\OperatorBundle\Classes\Cron;

use Contao\Database;
use Symfony\Component\HttpClient\HttpClient;

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
//        $request = new \Contao\Request();
//        $request->method = 'POST';
//        $request->data = json_encode($data);
//        if ($_SERVER['HTTP_REFERER']) {
//            $request->setHeader('Referer', $_SERVER['HTTP_REFERER']);
//        }
//        if ($_SERVER['HTTP_USER_AGENT']) {
//            $request->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT']);
//        }

        $headers = [];
        if ($_SERVER['HTTP_REFERER']) {
            $headers['Referer'] = $_SERVER['HTTP_REFERER'];
        }
        if ($_SERVER['HTTP_USER_AGENT']) {
            $headers['User-Agent'] = $_SERVER['HTTP_USER_AGENT'];
        }

        $client = HttpClient::create([
            'headers' => $headers,
            'body'    => json_encode($data)
        ]);
        $response = $client->request('POST', $statisticUrl, ['timeout' => 2]);
        if ($response) {
            $response = $response->getContent();
        }
//
//        $request->send($statisticUrl);
//        $response = $request->response;
        $success = json_decode($response, true)['success'];
        if ($success) {
            $db = Database::getInstance();
            if (count($this->offerStatisticIds) > 0) {
                $offerStatisticIdString = '(' . implode(',', $this->offerStatisticIds) . ')';
                $db->prepare('UPDATE tl_gutesio_offer_statistic SET `transferred` = 1 WHERE `id` IN ' . $offerStatisticIdString)
                    ->execute();
            }
            if (count($this->showcaseStatisticIds) > 0) {
                $showcaseStatisticIdString = '(' . implode(',', $this->showcaseStatisticIds) . ')';
                $db->prepare('UPDATE tl_gutesio_showcase_statistic SET `transferred` = 1 WHERE `id` IN ' . $showcaseStatisticIdString)
                    ->execute();
            }
        }
    }

    private function getStatisticData()
    {
        $db = Database::getInstance();

        $today = strtotime('today midnight');
        $offerStatistic = $db->prepare('SELECT * FROM tl_gutesio_offer_statistic WHERE `transferred` = 0 AND `date` <= ?')
            ->execute($today)->fetchAllAssoc();
        $showcaseStatistic = $db->prepare('SELECT * FROM tl_gutesio_showcase_statistic WHERE `transferred` = 0 AND `date` <= ?')
            ->execute($today)->fetchAllAssoc();

        $dataCtr = 0;
        if ($offerStatistic) {
            foreach ($offerStatistic as $statisticEntry) {
                $datum = [
                    'proxyKey' => 'offerStatistic_' . $statisticEntry['id'],
                    'proxyData' => $statisticEntry['uuid'] . ',' . $statisticEntry['date'] . ',' . $statisticEntry['offerId'] . ',' . $statisticEntry['visits'] . ',' . $statisticEntry['ownerId'],
                ];
                $proxyData[$datum['proxyKey']] = $datum['proxyData'];
                $dataCtr++;
                if ($dataCtr >= self::MAX_TRANSFER_DATA) {
                    break;
                }
                $this->offerStatisticIds[] = $statisticEntry['id'];
            }
        }
        if ($showcaseStatistic && ($dataCtr < self::MAX_TRANSFER_DATA)) {
            foreach ($showcaseStatistic as $statisticEntry) {
                $datum = [
                    'proxyKey' => 'showcaseStatistic_' . $statisticEntry['id'],
                    'proxyData' => $statisticEntry['uuid'] . ',' . $statisticEntry['date'] . ',' . $statisticEntry['showcaseId'] . ',' . $statisticEntry['visits'] . ',' . $statisticEntry['ownerId'],
                ];
                $proxyData[$datum['proxyKey']] = $datum['proxyData'];
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
