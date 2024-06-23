<?php

namespace gutesio\OperatorBundle\Classes\Cron;

use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
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
        if (!isset($objSettings->disableImports)) {
            $statisticUrl = rtrim($objSettings->con4gisIoUrl, '/') . '/saveStats.php';
            $statisticUrl .= '?key=' . $objSettings->con4gisIoKey;
            $data = [];
            $data['data'] = $this->getStatisticData();
            $data['domain'] = $_SERVER['SERVER_NAME'];

            $headers = [];
            if ($_SERVER['HTTP_REFERER']) {
                $headers['Referer'] = $_SERVER['HTTP_REFERER'];
            }
            if ($_SERVER['HTTP_USER_AGENT']) {
                $headers['User-Agent'] = $_SERVER['HTTP_USER_AGENT'];
            }

            $client = HttpClient::create([
                'headers' => $headers,
                'body' => json_encode($data)
            ]);
            $response = $client->request('POST', $statisticUrl, ['timeout' => 2]);
            if ($response->getStatusCode() !== 200) {
                C4gLogModel::addLogEntry('operator', $response->getContent());
            }

            $response = $response->getContent();

            $responseData = json_decode($response, true);
            $success = $responseData['success'];
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = json_last_error_msg();
                C4gLogModel::addLogEntry('operator', $error);
            }
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
            } else {
                C4gLogModel::addLogEntry('operator', 'Fehler in Proxy-Response mit Response Content: ' . json_encode($responseData) . " \n");
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
