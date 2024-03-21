<?php

namespace gutesio\OperatorBundle\Classes\Cron;

use Contao\Database;
use Contao\System;
use con4gis\PwaBundle\Classes\Events\PushNotificationEvent;

class PushUpcomingEvents
{
    // push gutes events that are within the "upcoming gutes events calendar"
    public function onDaily()
    {
        $db = Database::getInstance();
        $currentDate = time();

        $contaoCalendar = $this->checkContaoCalendar($db);
        $subscriptionType = $this->checkSubscription($db);

        // Check if the conditions are met

        foreach ($contaoCalendar as $cal) {
            if (!empty($cal) && !empty($subscriptionType) && $cal['pushUpcomingEvents'] == '1') {

                $upcomingEvents = $this->getGutesEvents($db, $currentDate);

                if ($upcomingEvents) {
                    $this->addGutesEvents($db, $currentDate,$upcomingEvents,$cal);
                }

            }

        }

    }

    private function checkContaoCalendar($db)
    {
        return $db->prepare('SELECT * FROM tl_calendar WHERE pushUpcomingEvents = 1')
            ->execute()
            ->fetchAllAssoc();
    }

    private function checkSubscription($db)
    {
        return $db->prepare('SELECT * FROM tl_c4g_push_subscription_type WHERE notifyUpcomingEvents = 1')
            ->execute()
            ->fetchAllAssoc();
    }

    private function getGutesEvents($db, $currentDate)
    {
        // Retrieve records from tl_gutesio_data_child_event where beginDate > currentDate and join with tl_gutesio_data_child

        $query = "  SELECT t.type,e.beginDate,e.beginTime,e.uuid, c.name,c.shortDescription,c.description,c.image
                    FROM tl_gutesio_data_child_event e
                    JOIN tl_gutesio_data_child c ON e.childId = c.uuid
                    JOIN tl_gutesio_data_child_type t ON c.typeId = t.uuid
                    WHERE e.beginDate > ?
                    ";

        return $db->prepare($query)
            ->execute($currentDate)
            ->fetchAllAssoc();

    }

    private function addGutesEvents($db, $currentDate, $events, $cal): void
    {
//        $addUuidColumnQuery = "ALTER TABLE tl_calendar_events DROP COLUMN uuid";
//        $db->query($addUuidColumnQuery);
        // Check if the 'uuid' column exists
//        $checkUuidColumnQuery = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tl_calendar_events' AND COLUMN_NAME = 'uuid'";
//        $stmtCheckUuidColumn = $db->query($checkUuidColumnQuery);

        // Check if the 'uuid' column exists
        $checkUuidColumnQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tl_calendar_events' AND COLUMN_NAME = 'uuid'";
        $stmtCheckUuidColumn = $db->query($checkUuidColumnQuery);
        $missingUuid = $stmtCheckUuidColumn->fetchAssoc() === false;

        // If the 'uuid' column doesn't exist, add it to the table
        if ($missingUuid) {
            $addUuidColumnQuery = "ALTER TABLE tl_calendar_events ADD COLUMN uuid VARCHAR(255) DEFAULT NULL";
            $db->query($addUuidColumnQuery);
        }

        $insertQuery = "INSERT INTO tl_calendar_events (id, pid, tstamp, title, startDate,startTime, description,
        teaser, subscriptionTypes,sendDoublePn, pnSendDate, published, uuid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?)";

        $stmtInsert = $db->prepare($insertQuery);

        // Get the maximum existing ID from tl_calendar_events
        $maxIdQuery = "SELECT MAX(id) AS maxId FROM tl_calendar_events";
        $maxIdResult = $db->query($maxIdQuery);
        $maxIdRow = $maxIdResult->fetchAssoc();
        $maxId = $maxIdRow['maxId'];
        $counter = $maxId ? $maxId + 1 : 1;

        $currentEQuery = "SELECT uuid FROM tl_calendar_events";
        $result = $db->query($currentEQuery);

        $existingUuids = array();
        while ($row = $result->fetchAssoc()) {
            $existingUuids[] = $row['uuid'];
        }

        // Iterate over the events and insert them into the table
        foreach ($events as $event) {
            $uuid = $event['uuid'];

            if (in_array($uuid, $existingUuids) || $missingUuid) {
                continue;
            }

            $id = $counter;
            $pid = $cal['id'];
            $tstamp = $currentDate;
            $title = $event['name'];
            $startDate = $event['beginDate'];
            $startTime = $startDate + $event['beginTime'];
            $description = $event['description'];
            $teaser = $event['shortDescription'];
            $subscriptionTypes = $cal['subscriptionTypes'];
            $pnSendDate = $startDate + 21600; // at 6:00
            $sendDoublePn = 1;
            $published = 1;

            $stmtInsert->execute($id, $pid, $tstamp, $title, $startDate,
                $startTime, $description,$teaser, $subscriptionTypes,$sendDoublePn,
                $pnSendDate, $published, $uuid);

            $counter++;
        }

        // Delete events that are older than currentDate in current calendar
        $this->removePastEvents($db, $currentDate,$events,$cal);
    }

    private function removePastEvents($db, $currentDate, $events, $cal): void
    {

        // Delete entries from tl_calendar_events where the date is older than currentDate and in the current calendar
        $deleteQuery = "DELETE FROM tl_calendar_events WHERE pid = ? AND startDate < ?";
        $stmtDelete = $db->prepare($deleteQuery);
        $stmtDelete->execute([$cal['id'], $currentDate]);
    }

}
