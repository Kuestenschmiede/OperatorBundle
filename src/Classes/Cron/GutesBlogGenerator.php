<?php

namespace gutesio\OperatorBundle\Classes\Cron;

use Contao\Database;
use gutesio\DataModelBundle\Classes\StringUtils;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataChildTypeModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use Contao\PageModel;

class GutesBlogGenerator
{

    public function onDaily(): void
    {
        $db = Database::getInstance();
        $currentDate = strtotime('today');

        $subscriptionTypes = $this->checkSubscriptions($db);
        $gutesNewsArchives = $this->checkArchives($db, $subscriptionTypes);

        //todo is this enough? maybe check the arcives aswell?
        if ($gutesNewsArchives) {
            //todo here we can add different intervals for the pn
            foreach ($gutesNewsArchives as $archive) {

                $this->addGutesNews($db, $archive, $currentDate, $subscriptionTypes);

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
        $subtypes = $db->prepare('SELECT * FROM tl_c4g_push_subscription_type WHERE gutesioEventTypes IS NOT NULL OR gutesioEventTypes != 0')
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

    private function getGutesNews($db, $currentDate, $gutesCategorie)
    {
        $categoryUUID = unserialize($gutesCategorie['gutesioEventTypes']);
        $cat = $categoryUUID[0];
        //todo only type interval "tmr"
        //todo [low prio] currently the push notification is set for 6 am. Maybe add another field to specify time. May where the inter val is already set to "today"
        //todo [performance] is it better to load all tmr events then only select the ones with the category or all events tmr and category in one go then for each archive?

        $endDate = strtotime('tomorrow');

        $query = "  SELECT t.type,e.beginDate,e.beginTime,c.uuid, c.name, c.description, c.shortDescription, c.typeId, c.imageCDN
                        FROM tl_gutesio_data_child_event e
                        JOIN tl_gutesio_data_child c ON e.childId = c.uuid
                        JOIN tl_gutesio_data_child_type t ON c.typeId = t.uuid
                        WHERE c.typeId ='$cat'
                        WHERE e.beginDate >= '$currentDate'
                        AND e.beginDate <= '$endDate'
                        ";
        /*
         *
         *
        */

        return $db->prepare($query)
            ->execute($currentDate)
            ->fetchAllAssoc();

    }

    private function addGutesNews($db, $archive, $currentDate, $subscriptionTypes): void
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

        foreach ($subscriptionTypes as $subscriptionType) {
            if ($subscriptionType['id'] == intval($archive['subscriptionTypes'])){
                $gutesEvents = $this->getGutesNews($db, $currentDate, $subscriptionType);
            }
        }

        // Iterate over the events and insert them into the table
        foreach ($gutesEvents as $event) {
            $uuid = $event['uuid'];

            if (in_array($uuid, $existingUuids) || $missingUuid) {
                continue;
            }

            $imageUrl = $this->getImagePath($event);

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
}