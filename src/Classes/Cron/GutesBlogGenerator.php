<?php

namespace gutesio\OperatorBundle\Classes\Cron;

use Contao\Database;
use gutesio\DataModelBundle\Classes\StringUtils;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataChildTypeModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use Contao\PageModel;
//use con4gis\PwaBundle\Classes\Callbacks\PwaConfigurationCallback;
//use con4gis\PwaBundle\con4gisPwaBundle;
//use Contao\Controller;
//use Contao\NewsBundle\ContaoNewsBundle;
//
//use Contao\System;
//use con4gis\PwaBundle\Classes\Events\PushNotificationEvent;
//use gutesio\OperatorBundle\Classes\Callback\GutesioModuleCallback;


class GutesBlogGenerator
{

    public function onDaily(): void
    {
        $db = Database::getInstance();
        $currentDate = strtotime('today');

        $subscriptionTypes = $this->checkSubscriptions($db);
        $gutesNewsArchives = $this->checkArchives($db, $subscriptionTypes);
//        $types = $this->getGutesioEventTypes();


//        //might be completly redundand
//        $categories = [];
//        foreach ($subscriptionTypes as $subscriptionType) {
//            $categories[$subscriptionType['id']] = unserialize($subscriptionType['gutesioEventTypes']);
//            //prep sql statements to import news for each archive
//        }

        //todo get only news from the categories picked (problem is do we go through every archive )
//        $categories = $this->getPickedCategories();

        $gutesNews = $this->getGutesNews($db, $currentDate/*, $categories*/);

        //todo is this enough? maybe check the arcives aswell?
        if ($gutesNewsArchives) {
            //setFowardingLink so we don't have to keep requesting
//            $dns = $this->getDomain($db);
            //here we can add different intervals for the pn
            foreach ($gutesNewsArchives as $archive) {
                $archiveSubs = unserialize($archive['subscriptionTypes']);
                foreach ($archiveSubs as $archiveSub) {
                    foreach ($subscriptionTypes as $subscriptionType) {
                        if (intval($archiveSub) == $subscriptionType['id'])  {
                            $this->addGutesNews($db, $archive, $currentDate, $gutesNews);
                        }
                    }
                }

                $this->cleanArchive($archive, $currentDate, $db);
            }
        }
    }

    private function checkArchives($db, $subtypes)
    {
        $achives = $db->prepare('SELECT * FROM tl_news_archive WHERE subscriptionTypes IS NOT NULL AND generateGutesBlog IS NOT NULL')
            ->execute()
            ->fetchAllAssoc();


        return $achives;

    }

    private function checkSubscriptions($db)
    {
        $subtypes = $db->prepare('SELECT * FROM tl_c4g_push_subscription_type WHERE notifyUpcomingEvents = 1 AND gutesioEventTypes IS NOT NULL OR gutesioEventTypes != 0')
            ->execute()
            ->fetchAllAssoc();

        return $subtypes;
    }

    private function getImagePath($arrItem)
    {
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $cdnUrl = $objSettings->cdnUrl;
        $imagePath = StringUtils::addUrlToPath($cdnUrl,$arrItem['imageCDN']);

        return $imagePath;
    }


    private function getGutesNews($db, $currentDate/*, $categories*/)
    {
        //todo get event news with only with the categories (typeID == category or subtype uuid)
        //todo only type interval "tmr"
        //todo currently the push notification is set for 6 am. Maybe add another field to specify time. May where the inter val is already set to "today"

        $endDate = strtotime('tomorrow');

        // Retrieve records from tl_gutesio_data_child_event where beginDate > currentDate and join with tl_gutesio_data_child
//              WHERE e.beginDate >= '$currentDate'
//              AND e.beginDate <= '$endDate'";

//        WHERE e.beginDate > ?

        $query = "  SELECT t.type,e.beginDate,e.beginTime,c.uuid, c.name, c.description, c.shortDescription, c.typeId, c.imageCDN
                        FROM tl_gutesio_data_child_event e
                        JOIN tl_gutesio_data_child c ON e.childId = c.uuid
                        JOIN tl_gutesio_data_child_type t ON c.typeId = t.uuid
                        WHERE e.beginDate >= '$currentDate'
                        AND e.beginDate <= '$endDate'";

        return $db->prepare($query)
            ->execute($currentDate)
            ->fetchAllAssoc();

    }

    private function getGutesioEventTypes(): array
    {
        $arrTypes = GutesioDataChildTypeModel::findAll();
        $arrTypes = $arrTypes ? $arrTypes->fetchAll() : [];
        $options = [];
        $options['-'] = "-";
        foreach ($arrTypes as $type) {
            if ($type['type'] == 'event') {
                $options[$type['uuid']] = $type['name'];


            }
        }
        return $options;
    }

    private function addGutesNews($db, $archive, $currentDate, $gutesEvents): void
    {
        $archiveId = $archive['id'];
        // Check if the 'uuid' column exists
        $checkUuidColumnQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tl_news' AND COLUMN_NAME = 'gutesUuid'";
        $stmtCheckUuidColumn = $db->query($checkUuidColumnQuery);
        $missingUuid = $stmtCheckUuidColumn->fetchAssoc() === false;
//        $missingUuid = $stmtCheckUuidColumn ? $stmtCheckUuidColumn->fetchAssoc()  : 0;

        // If the 'uuid' column doesn't exist, add it to the table
        if ($missingUuid) {
            $addUuidColumnQuery = "ALTER TABLE tl_news ADD COLUMN `gutesUuid` VARCHAR(255) DEFAULT 0";

            $db->query($addUuidColumnQuery);
        }
//        $addUuidColumnQuery = "ALTER TABLE tl_news drop COLUMN `gutesUuid`";
//        $db->query($addUuidColumnQuery);

        $insertQuery = "INSERT INTO tl_news (id, pid, tstamp, headline, date, time, description,
            teaser, /*addImage,*/pnSendDate, source, url, published, gutesUuid) 
            VALUES ( /*?,*/?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmtInsert = $db->prepare($insertQuery);

        // Get the maximum existing ID from tl_news
        $maxIdQuery = "SELECT MAX(id) AS maxId FROM tl_news";
        $maxIdResult = $db->query($maxIdQuery);
        $maxIdRow = $maxIdResult->fetchAssoc();
        $maxId = $maxIdRow['maxId'];
        $counter = $maxId ? $maxId + 1 : 1;

        //preventing duplicated entries
        $currentEQuery = "SELECT gutesUuid FROM tl_news";

        $result = $db->query($currentEQuery);

        $existingUuids = array();
        while ($row = $result->fetchAssoc()) {
            if ($row['gutesUuid'] !== '0') {
                $existingUuids[] = $row['gutesUuid'];
            }
        }

        // Iterate over the events and insert them into the table
        foreach ($gutesEvents as $event) {
            $uuid = $event['uuid'];

            if (in_array($uuid, $existingUuids) || $missingUuid) {
                continue;
            }

            $imageUrl = $this->getImagePath($event);
//            $addImage = $imageUrl ? 1 : 0;
//
//            todo meta-data needs to be filled perhaps?

//            //$logoData = $this->createFileDataFromModel($logoModel);
//            $logoData = $this->createFileDataFromFile($showcase['logoCDN']);
//            $logoData['href'] = $showcase['alias'];
//            $datum['relatedShowcaseLogos'][] = $logoData;
//            $datum['relatedShowcases'][] = [
//                'uuid' => $showcase['uuid'],
//                'foreignLink' => $showcase['foreignLink'],
//                'releaseType' => $showcase['releaseType'],
//                'name' => html_entity_decode($showcase['name']),
//            ];

            $id = $counter;
            $pid = $archiveId;
            $tstamp = $currentDate;
            $title = $event['name'];
            $date = $event['beginDate'];
            $time = $date + $event['beginTime'];
            $description = $event['description'];

            //source
            $source = 'external';
            $fowardingUrl = $this->getFowardingUrl($uuid);

            if ($imageUrl) {
                $teaser = '<img src="' . $imageUrl . '">' . $event['shortDescription'];
            } else {
                $teaser = $event['shortDescription'];
            }

            if ($fowardingUrl && $imageUrl) {
                $teaser = '<a href="' . $fowardingUrl . '"><img src="' . $imageUrl . '"></a><br>' . $event['shortDescription'];
            }

//            $url = $alias . str_replace(['{', '}'], '', unserialize($uuid));


            //todo pn send date
            $pnSendDate = $date + 21600; // at 6:00
            $published = 1;

            $stmtInsert->execute($id, $pid, $tstamp, $title, $date,
                                 $time, $description, $teaser, /*$addImage, $imageUrl,*/ $pnSendDate, $source,
                                 $fowardingUrl, $published, $uuid);

            $counter++;
        }
    }
    private function cleanArchive($archive, $currentDate, $db): void
    {
        $deleteQuery = "DELETE FROM tl_news
                        WHERE (pid = ? AND tstamp < ?) OR (pid = ? AND date < ?)";
        $stmtDelete = $db->prepare($deleteQuery);
        $stmtDelete->execute([$archive['id'], $currentDate, $archive['id'], $currentDate]);
    }

    private function getFowardingUrl($uuid): string
    {
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $detailPageId = $objSettings->eventDetailPage;

        $page = PageModel::findById($detailPageId);

        $alias = $page->alias;

        $uuid = str_replace(['{', '}'], '', $uuid); // Remove curly braces from UUID

        $forwardingUrl =  '/' . $alias . '/' . $uuid;

        return $forwardingUrl;
    }


//    private function getDomain($db)
//    {
//        $pages = PageModel::findByType('root');
//
//        foreach ($pages as $page) {
//            if ($page->published) {
//                return $page->dns;
//            }
//        }
//        return '';
//    }

//    todo Pictures need a better way to be saved
//    public function createFileDataFromFile($file, $svg = false) : array
//    {
//        $objSettings = GutesioOperatorSettingsModel::findSettings();
//        $cdnUrl = $objSettings->cdnUrl;
//
//        if ($svg) {
//            $width = 100;
//            $height = 100;
//        } else {
//            //ToDo extreme slow
//            //list($width, $height) = getimagesize(StringUtils::addUrlToPath($cdnUrl,$file));
//            $width = 600;
//            $height = 450;
//        }
//
//        $url = StringUtils::addUrlToPath($cdnUrl, $file);
//
//        return [
//            'src' => $url,
//            'path' => $url,
//            'uuid' => '',
//            'alt' => '',
//            'name' => '',
//            'height' => $height,
//            'width' => $width
//        ];
//    }
}