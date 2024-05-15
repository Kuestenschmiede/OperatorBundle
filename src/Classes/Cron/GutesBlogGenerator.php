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
        $gutesNewsArchives = $this->checkArchives($db);

        $gutesNews = $this->getGutesNews($db, $currentDate/*, $categories*/);

        //todo add multiple gutes type for each subType (pwa)
        if ($gutesNewsArchives) {
            //todo here we can add different intervals for the pn
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

    private function checkArchives($db)
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
        $endDate = strtotime('tomorrow');

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

    private function addGutesNews($db, $archive, $currentDate, $gutesEvents): void
    {
        $archiveId = $archive['id'];

        // Check if the 'uuid' column exists
        $checkUuidColumnQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tl_news' AND COLUMN_NAME = 'gutesUuid'";
        $stmtCheckUuidColumn = $db->query($checkUuidColumnQuery);
        $missingUuid = !$stmtCheckUuidColumn->numRows;

        // If the 'uuid' column doesn't exist, add it to the table
        // todo there needs to be a better way to do this
        if ($missingUuid) {
            $addUuidColumnQuery = "ALTER TABLE tl_news ADD COLUMN `gutesUuid` VARCHAR(255) DEFAULT '0'";
            $db->query($addUuidColumnQuery);
        }

        $insertQuery = "INSERT INTO tl_news (id, pid, tstamp, headline, date, time, description,
        teaser, stop, pnSendDate, pnSent, source, url, published, gutesUuid) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmtInsert = $db->prepare($insertQuery);

        // Get the maximum existing ID from tl_news
        $maxIdQuery = "SELECT MAX(id) AS maxId FROM tl_news";
        $maxIdResult = $db->query($maxIdQuery);
        $maxIdRow = $maxIdResult->fetchAssoc();
        $maxId = $maxIdRow['maxId'];
        $counter = $maxId ? $maxId + 1 : 1;

        // Prevent duplicated entries
        $currentEQuery = "SELECT gutesUuid FROM tl_news";
        $result = $db->query($currentEQuery);

        $existingUuids = [];
        while ($row = $result->fetchAssoc()) {
            if ($row['gutesUuid'] !== '0') {
                $existingUuids[] = $row['gutesUuid'];
            }
        }

        $gutesEventExists = false;

        // Iterate over the events and insert them into the table
        foreach ($gutesEvents as $event) {
            $uuid = $event['uuid'];

            if (in_array($uuid, $existingUuids) || $missingUuid) {
                continue;
            }

            $gutesEventExists = true;

            $imageUrl = $this->getImagePath($event);
            $id = $counter;
            $pid = $archiveId;
            $tstamp = $currentDate;
            $title = $event['name'];
            $date = $event['beginDate'];
            $time = $date + $event['beginTime'];
            $description = $event['description'];

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

            $published = 1;

            $pnSendDate = 0;
            $pnSent = 1;


            $stmtInsert->execute([$id, $pid, $tstamp, $title, $date,
                $time, $description, $teaser,  strtotime('tomorrow'), $pnSendDate, $pnSent,
                $source, $fowardingUrl, $published, $uuid]);

            $counter++;
        }

        // Add one pn
        if ($gutesEventExists && !$this->specialEventExists($db)) {
            $specialEventId = $counter;
            $specialTitle = $archive['gutesBlogTitle'];
            $specialTeaser = $archive['gutesBlogTeaser'];
            $specialDescription = $specialTeaser;
            $specialDate = $currentDate;
            $specialTime = $currentDate;
            $specialSource = 'external';
            $specialPublished = 1;
            $specialUuid = 1;
            $specialUrl = $this->getFowardingUrl($specialUuid);
            $specialPnSendDate = $date + 21600;
            $specialPnSent = 0;
            $specialUnpublish = $currentDate + 60;

            $stmtInsert->execute([$specialEventId, $archiveId, $currentDate, $specialTitle, $specialDate,
                $specialTime, $specialDescription, $specialTeaser, $specialUnpublish, $specialPnSendDate, $specialPnSent, $specialSource, $specialUrl, $specialPublished, $specialUuid]);
        }
    }

    private function specialEventExists($db): bool
    {
        $checkSpecialEventQuery = "SELECT 1 FROM tl_news WHERE gutesUuid = 1";
        $stmtCheckSpecialEvent = $db->query($checkSpecialEventQuery);
        return $stmtCheckSpecialEvent->numRows > 0;
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

        if ($uuid === 1) {
            $forwardingUrl = '/' . $page->alias;
            return $forwardingUrl;
        }

        $alias = $page->alias;

        $uuid = str_replace(['{', '}'], '', $uuid); // Remove curly braces from UUID

        $forwardingUrl =  '/' . $alias . '/' . $uuid;

        return $forwardingUrl;
    }
}