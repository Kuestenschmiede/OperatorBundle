<?php

namespace gutesio\OperatorBundle\Classes\Cron;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\PwaBundle\Classes\Events\PushNotificationEvent;
use con4gis\PwaBundle\Entity\PushSubscriptionType;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\StringUtil;
use Doctrine\ORM\EntityManagerInterface;
use gutesio\DataModelBundle\Classes\FileUtils;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;
use Contao\PageModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;

class GutesBlogGenerator
{

    public function __construct(
        private LoggerInterface $logger,
        private ContaoFramework $framework,
        private EventDispatcherInterface $eventDispatcher,
        private RouterInterface $router,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function onHourly(): void
    {
        $this->framework->initialize();
        $this->logger->error("Start GutesBlogGenerator run...");
        $objSettings = \con4gis\CoreBundle\Resources\contao\models\C4gSettingsModel::findSettings();
        if (isset($objSettings->syncDataAutomaticly) && $objSettings->syncDataAutomaticly !== null && $objSettings->syncDataAutomaticly) {
            $db = Database::getInstance();
            $currentTime = strtotime('today');
            $currentDate = new \DateTime();
            $currentDate->setTimestamp($currentTime);
            $currentDate->setTime(0, 0, 0);
            $currentDate = $currentDate->getTimestamp();

            // check operator settings
            $this->logger->error("Checking push configuration...");
            $settings = GutesioOperatorSettingsModel::findSettings();
            $pushConfiguration = StringUtil::deserialize($settings->dailyEventPushConfig, true);
            $subTypeRepo = $this->entityManager->getRepository(PushSubscriptionType::class);

            if ($pushConfiguration && count($pushConfiguration) > 0) {
                foreach ($pushConfiguration as $pushConfig) {
                    $sendMessage = false;
                    $types = $pushConfig['subscriptionTypes'];
                    foreach ($types as $type) {
                        /** @var PushSubscriptionType $objType */
                        $objType = $subTypeRepo->findOneBy(['id' => $type]);
                        if ($objType) {
                            $gutesCategories = $objType->getGutesioEventTypes();
                            $postals = $objType->getPostals();
                            $events = $this->getGutesEvents($db, $currentDate, $gutesCategories, $postals);
                            if (count($events) > 0) {
                                $sendMessage = true;
                            }
                        }
                    }

                    if (!$sendMessage) {
                        continue;
                    }
                    $time = $pushConfig['pushTime'];
                    $arrTime = explode(":", $time);
                    $datetime = new \DateTime();
                    $datetime->setTime(intval($arrTime[0]) % 24, intval($arrTime[1]) % 60);

                    $now = new \DateTime();
                    if ($now->getTimestamp() >= $datetime->getTimestamp()) {
                        // time matches, push message
                        $message = $pushConfig['pushMessage'];
                        $pageId = $pushConfig['pushRedirectPage'];
                        $identifier = implode("_", [sha1($message), $pageId, $datetime->getTimestamp()]);
                        $updateMode = "";
                        $results = $db->prepare("SELECT `sentTime` FROM tl_gutesio_event_push_notifications WHERE `identifier` = ?")
                            ->execute($identifier)->fetchAllAssoc();
                        if (count($results) === 0) {
                            $sent = false;
                            $updateMode = "insert";
                        } else if (count($results) === 1) {
                            $sentTime = $results[0]['sentTime'];
                            $sent = $sentTime >= $datetime->getTimestamp();
                            $updateMode = "update";
                        } else {
                            // too many results, shouldn't happen
                            $sent = true;
                        }

                        if (!$sent) {
                            if ($pageId) {
                                $clickUrl = $this->router->generate("tl_page." . $pageId);
                            } else {
                                $clickUrl = "";
                            }
                            $event = new PushNotificationEvent();
                            $event->setMessage($message);
                            $event->setSubscriptionTypes($types);
                            $event->setClickUrl($clickUrl);
                            $this->eventDispatcher->dispatch($event, PushNotificationEvent::NAME);
                            $this->logger->error("Sent notification with text: " . $event->getMessage() . " to " . count($event->getSubscriptions()) . " recipients.");
                            if ($updateMode === "insert") {
                                $sql = "INSERT INTO tl_gutesio_event_push_notifications (`identifier`, `sentTime`) VALUES (?,?)";
                                $db->prepare($sql)->execute($identifier, time());
                            } else if ($updateMode === "update") {
                                $sql = "UPDATE tl_gutesio_event_push_notifications SET `sentTime` = ? WHERE `identifier` = ?";
                                $db->prepare($sql)->execute(time(), $identifier);
                            }
                        }
                    }
                }
            } else {
                $this->logger->error("No valid push configuration found.");
            }
        }
        $this->logger->error("...finished GutesBlogGenerator run.");
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
        $subtypes = $db->prepare('SELECT * FROM tl_c4g_push_subscription_type WHERE gutesioEventTypes IS NOT NULL OR gutesioEventTypes != 0')
            ->execute()
            ->fetchAllAssoc();

        return $subtypes;
    }

    private function getImagePath($arrItem)
    {
        $objSettings = GutesioOperatorSettingsModel::findSettings();
        $cdnUrl = $objSettings->cdnUrl;
        $fileUtils = new FileUtils();
        $imagePath = $fileUtils->addUrlToPathAndGetImage($cdnUrl,$arrItem['imageCDN']);

        return $imagePath;
    }

    //todo get event news with only with the categories (typeID == category or subtype uuid)
    private function getGutesEvents($db, $currentDate, $categories, $postals)
    {
        // todo get event news with only the categories (typeID == category or subtype uuid)
        $endDate = strtotime('tomorrow');
        $params = [];

        if ($postals) {
            $postals = str_replace("*", "%", $postals);
            $arrPostals = explode(",", $postals);
            $query = "SELECT t.type, 
                     IF(e.beginDate <= '$currentDate' AND e.endDate >= '$currentDate', '$currentDate', e.beginDate) AS beginDate,
                     e.beginTime,
                     c.uuid,
                     c.name,
                     c.description,
                     c.shortDescription,
                     c.typeId,
                     c.imageCDN,
                     elem.locationZip
              FROM tl_gutesio_data_child_event e
              JOIN tl_gutesio_data_child c ON e.childId = c.uuid
              JOIN tl_gutesio_data_child_type t ON c.typeId = t.uuid
              JOIN tl_gutesio_data_child_connection cc ON cc.childId = c.uuid
              JOIN tl_gutesio_data_element elem ON cc.childId = c.uuid
              WHERE ('$currentDate' BETWEEN e.beginDate AND e.endDate)
              OR (e.beginDate = '$currentDate')
              OR (e.endDate = '$currentDate')
              AND e.beginDate <= '$endDate'";

            $query .= " AND ";

            foreach ($arrPostals as $key=>$arrPostal) {
                $query .= " `locationZip` LIKE ?";
                if (!(array_key_last($arrPostals) === $key)) {
                    $query .= " OR";
                }
                $params[] = $arrPostal;
            }
        } else {
            $query = "SELECT t.type, 
                     IF(e.beginDate <= '$currentDate' AND e.endDate >= '$currentDate', '$currentDate', e.beginDate) AS beginDate,
                     e.beginTime,
                     c.uuid,
                     c.name,
                     c.description,
                     c.shortDescription,
                     c.typeId,
                     c.imageCDN
              FROM tl_gutesio_data_child_event e
              JOIN tl_gutesio_data_child c ON e.childId = c.uuid
              JOIN tl_gutesio_data_child_type t ON c.typeId = t.uuid
              WHERE ('$currentDate' BETWEEN e.beginDate AND e.endDate)
              OR (e.beginDate = '$currentDate')
              OR (e.endDate = '$currentDate')
              AND e.beginDate <= '$endDate'";
        }

        if ($categories) {
            $categories = StringUtil::deserialize($categories, true);
            $query .= " AND c.typeId " . C4GUtils::buildInString($categories);
            $params = array_merge($params, $categories);
        }

        $result = $db->prepare($query)
            ->execute(...$params)
            ->fetchAllAssoc();

        if (count($result) === 0) {
            $this->logger->error("No events found that match the configuration.");
        }

        return $result;
    }


    private function addGutesNews($db, $archive, $currentDate, $gutesCategories, $gutesEvents): void
    {
        $archiveId = $archive['id'];

        // Check if the 'uuid' column exists
        $checkUuidColumnQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tl_news' AND COLUMN_NAME = 'gutesUuid'";
        $stmtCheckUuidColumn = $db->query($checkUuidColumnQuery);
        $missingUuid = !$stmtCheckUuidColumn->numRows;

        // If the 'uuid' column doesn't exist, add it to the table
        // todo there needs to be a better way to do this
        if ($missingUuid) {
            $addUuidColumnQuery = "ALTER TABLE tl_news ADD COLUMN `gutesUuid` VARCHAR(255) DEFAULT ''";
            $db->query($addUuidColumnQuery);
        }

        $insertQuery = "INSERT INTO tl_news (pid, tstamp, headline, date, time, description,
        teaser, stop, pnSendDate, pnSent, source, url, published, gutesUuid) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmtInsert = $db->prepare($insertQuery);

        // Get the maximum existing ID from tl_news
//        $maxIdQuery = "SELECT MAX(id) AS maxId FROM tl_news";
//        $maxIdResult = $db->query($maxIdQuery);
//        $maxIdRow = $maxIdResult->fetchAssoc();
//        $maxId = $maxIdRow['maxId'];
//        $counter = $maxId ? $maxId + 1 : 1;
        $counter = 0;
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
            if ((is_array($gutesCategories) && in_array($event['typeId'], $gutesCategories)) ||
                (!is_array($gutesCategories) && $event['typeId'] === $gutesCategories)) {

                $uuid = $event['uuid'];

                if (in_array($uuid, $existingUuids) || $missingUuid) {
                    continue;
                }

                $gutesEventExists = true;

                $imageUrl = $this->getImagePath($event);
                $pid = $archiveId ?: 0;
                $tstamp = $currentDate ?: '';
                $title = $event['name'] ?: '';


                $date = $event['beginDate'] ?: '';
                $beginDate = intval($event['beginDate']) ?: 0;
                $endDate = intval($event['endDate']) ?: 0;
                if ($beginDate && $endDate) {
                    if ($currentDate >= $beginDate && $currentDate <= $endDate) {
                        $date = $currentDate;
                    }
                }

                $time = ($event['beginDate'] ?: 0) + ($event['beginTime'] ?: 0);
                $description = $event['description'] ?: '';

                $source = 'external';
                $fowardingUrl = $this->getFowardingUrl($uuid) ?: '';

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

                $stmtInsert->execute([$pid, $tstamp, $title, $date,
                    $time, $description, $teaser,  strtotime('tomorrow'), $pnSendDate, $pnSent,
                    $source, $fowardingUrl, $published, $uuid]);

                $counter++;
            }
        }

        // Add one pn
        if ($gutesEventExists && !$this->specialEventExists($db)) {
            $specialTitle = $archive['gutesBlogTitle'] ?: '';
            $specialTeaser = $archive['gutesBlogTeaser'] ?: '';
            $specialDescription = $specialTeaser ?: '';
            $specialDate = $currentDate ?: 0;
            $specialTime = $currentDate ?: 0;
            $specialSource = 'external';
            $specialPublished = 1;
            $specialUuid = 1;
            $redirectPage = PageModel::findById($archive['jumpTo']);
            $specialUrl = ($redirectPage !== null) ? $redirectPage->getAbsoluteUrl() : "";
            $specialPnSendDate = $specialDate + 21600 ?: 0;
            $specialPnSent = 0;
            $specialUnpublish = $currentDate + 60 ?: 0;

            $stmtInsert->execute([$archiveId, $currentDate, $specialTitle, $specialDate,
                $specialTime, $specialDescription, $specialTeaser, $specialUnpublish, $specialPnSendDate, $specialPnSent, $specialSource, $specialUrl, $specialPublished, $specialUuid]);
        }
    }

    private function specialEventExists($db): bool
    {
        $checkSpecialEventQuery = "SELECT * FROM tl_news WHERE gutesUuid = 1";
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