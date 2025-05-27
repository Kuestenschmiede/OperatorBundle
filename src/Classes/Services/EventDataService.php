<?php

namespace gutesio\OperatorBundle\Classes\Services;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\C4GVersionProvider;
use Contao\Database;
use Contao\StringUtil;
use gutesio\DataModelBundle\Classes\FileUtils;
use gutesio\OperatorBundle\Classes\Helper\OfferDataHelper;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;

class EventDataService
{
    public function __construct(private OfferDataHelper $helper)
    {
    }

    public function getEventData(
        string $searchTerm,
        int $offset,
        array $filterData,
        int $limit,
        array $tags
    ) {
        $database = Database::getInstance();

        $parameters = [];
        $termsSet = ($searchTerm !== "") && ($searchTerm !== "*");
        $strTagFieldClause = " tl_gutesio_data_child_tag_values.`tagFieldValue` LIKE ?";
        $sqlExtendedCategoryTerms = " OR tl_gutesio_data_child_type.extendedSearchTerms LIKE ?";

        $sql = 'SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
            'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                COALESCE(a.shortDescription) AS shortDescription, ' . '
                tl_gutesio_data_child_type.uuid AS typeId, tl_gutesio_data_child_type.type AS type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
                tl_gutesio_data_element.name as vendorName, ' . '
                tl_gutesio_data_element.alias as vendorAlias, ' . '
                e.beginDate,
                e.beginTime,
                e.endDate,
                e.endTime,
                e.entryTime,
                e.eventPrice,
                e.reservationContactEMail,
                e.reservationContactPhone,
                e.locationElementId,
                e.recurring,
                e.recurrences,
                e.repeatEach,
                e.appointmentUponAgreement,
                ' /* RAND() calculation for random sorting of recurring events, otherwise they all are at the start */ .'
                COALESCE(e.beginDate, e.beginTime, FLOOR(RAND()*(2000000000-1700000000+1)+1700000000)) AS beginDateTime,
                e.expertTimes, '.
                (
                $termsSet ?
                'match(a.fullTextContent) against(\'' . $searchTerm . '\' in boolean mode) as relevance, '
                : ""
                ) .
                'a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_event e ON e.childId = a.uuid ' . '
                WHERE a.published = 1 AND type = "event"'  .
                (
                $termsSet ?
                    ' AND (match(a.fullTextContent) against(\'' . $searchTerm . '\' in boolean mode) OR ' . $strTagFieldClause . $sqlExtendedCategoryTerms . ') '
                    : ""
                ) .
                ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())'
            ;

        if ($termsSet) {
            $searchTermParam = str_replace("*", "%", $searchTerm);
            $parameters[] = "%".$searchTermParam;
            $parameters[] = "%".$searchTermParam;
        }

        if ($filterData['tags']) {
            $sql .= " AND tl_gutesio_data_child_tag_values.tagId " . C4GUtils::buildInString($filterData['tags']);
            $parameters = array_merge($parameters, $filterData['tags']);
        }
        if ($filterData['categories']) {
            $sql .= " AND typeId " . C4GUtils::buildInString($filterData['categories']);
            $parameters = array_merge($parameters, $filterData['categories']);
        }

        if ($filterData['date']) {
            $sql .= " AND (e.beginDate IS NULL OR (e.beginDate >= ? AND e.beginDate <= ?) OR (e.beginDate <= ? AND e.endDate >= ?))";
            $parameters[] = $filterData['date']['from'];
            $parameters[] = $filterData['date']['until'];
            $parameters[] = $filterData['date']['from'];
            $parameters[] = $filterData['date']['until'];
        } else {
            // use today midnight as parameter to get all events from today
            $today = new \DateTime();
            $today->setTime(0, 0);
            $todayTstamp = $today->getTimestamp();
            $tomorrow = $today->modify("+1 day");
            $tomorrowStamp = $tomorrow->getTimestamp();
            $sql .= " AND e.expertTimes = 0 AND (e.beginDate IS NULL OR (e.beginDate >= ?) OR (e.beginDate <= ? AND e.endDate >= ?) OR e.recurring = 1 OR e.appointmentUponAgreement = 1)";
            $parameters[] = $todayTstamp;
            $parameters[] = $todayTstamp;
            $parameters[] = $tomorrowStamp;
        }

        $sql .= sprintf(" ORDER BY beginDateTime ASC LIMIT %s, %s", $offset, $limit);

        $eventData = $database->prepare($sql)->execute(...$parameters)->fetchAllAssoc();

        $offerTagRelations = $this->helper->loadOfferTagRelations($eventData);

        $formattedData = $this->formatEventData($eventData, $tags, $offerTagRelations);

        return $formattedData;
    }

    private function formatEventData(array $events, array $tagData, array $offerTagRelations)
    {
        $results = [];

        foreach ($events as $eventData) {
            $tooOld = false;
            $beginDateTime = new \DateTime();
            $beginDateTime->setTimestamp($eventData['beginDate']);
            // Add one day so events are still shown on the day they expire
            $beginDateTime->setDate(
                $beginDateTime->format('Y'),
                $beginDateTime->format('m'),
                (int)$beginDateTime->format('d') + 1
            );
            $endDateTime = new \DateTime();
            $endDateTime->setTimestamp($eventData['endDate']);

            $eventData['storeBeginDate'] = $eventData['beginDate'];
            $eventData['storeBeginTime'] = $eventData['beginTime'];

            if ($beginDateTime->getTimestamp() < time()) {
                if ($eventData['recurring']) {
                    $repeatEach = StringUtil::deserialize($eventData['repeatEach']);
                    if ($repeatEach && is_array($repeatEach)) {
                        $times = key_exists('recurrences', $eventData) && is_int($eventData['recurrences']) ? intval($eventData['recurrences']) : 0;
                        $value = key_exists('value', $eventData) && is_int($repeatEach['value']) ? intval($repeatEach['value']) : 0;

                        if ($times === 0) {
                            $times = 100;
                        }
                        while ($times > 0) {
                            $times -= 1;
                            switch ($repeatEach['unit']) {
                                case 'weeks':
                                    $beginDateTime->setDate(
                                        $beginDateTime->format('Y'),
                                        $beginDateTime->format('m'),
                                        ((int) $beginDateTime->format('d')) + ($value * 7)
                                    );
                                    if ($times > 0) {
                                        $nextDateTime = clone $beginDateTime;
                                        $nextDateTime->setDate(
                                            $nextDateTime->format('Y'),
                                            $nextDateTime->format('m'),
                                            ((int) $nextDateTime->format('d')) + ($value * 7)
                                        );
                                    }
                                    $endDateTime = $beginDateTime;
                                    break;
                                case 'months':
                                    $beginDateTime->setDate(
                                        $beginDateTime->format('Y'),
                                        ((int) $beginDateTime->format('m')) + $value,
                                        $beginDateTime->format('d')
                                    );
                                    if ($times > 0) {
                                        $nextDateTime = clone $beginDateTime;
                                        $nextDateTime->setDate(
                                            $nextDateTime->format('Y'),
                                            ((int) $nextDateTime->format('m')) + $value,
                                            $nextDateTime->format('d')
                                        );
                                    }
                                    $endDateTime = $beginDateTime;
                                    break;
                                case 'years':
                                    $beginDateTime->setDate(
                                        ((int) $beginDateTime->format('Y')) + $value,
                                        $beginDateTime->format('m'),
                                        $beginDateTime->format('d')
                                    );
                                    if ($times > 0) {
                                        $nextDateTime = clone $beginDateTime;
                                        $nextDateTime->setDate(
                                            ((int) $nextDateTime->format('Y')) + $value,
                                            $nextDateTime->format('m'),
                                            $nextDateTime->format('d')
                                        );
                                    }
                                    $endDateTime = $beginDateTime;
                                    break;
                                default:
                                    $beginDateTime->setDate(
                                        $beginDateTime->format('Y'),
                                        $beginDateTime->format('m'),
                                        ((int) $beginDateTime->format('d')) + $value
                                    );
                                    if ($times > 0) {
                                        $nextDateTime = clone $beginDateTime;
                                        $nextDateTime->setDate(
                                            $nextDateTime->format('Y'),
                                            $nextDateTime->format('m'),
                                            ((int) $nextDateTime->format('d')) + $value
                                        );
                                    }

                                    break;
                            }
                            if ($beginDateTime->getTimestamp() >= time()) {
                                break;
                            } elseif ($times === 0 && ($endDateTime > 0) && $endDateTime->getTimestamp() < time()) {
                                $tooOld = true;

                                break;
                            }
                        }
                    }
                }
            }

            if ($tooOld && !$eventData['expertTimes']) {
                continue;
            }

            // remove the extra day added previously
            $beginDateTime ? $beginDateTime->setDate(
                $beginDateTime->format('Y'),
                $beginDateTime->format('m'),
                (int) $beginDateTime->format('d') - 1
            ) : false;

            $beginDate = isset($beginDateTime) ? $beginDateTime->format('d.m.Y') : false;
            $beginDateShort = isset($beginDateTime) ? $beginDateTime->format('d.m') : false;
            $endDate = isset($endDateTime) ? $endDateTime->format('d.m.Y') : false;
            $nextDate = isset($nextDateTime) ? $nextDateTime->format('d.m.Y') : false;
            $beginTime = isset($eventData['beginTime']) ? gmdate('H:i', $eventData['beginTime']) : false;
            $endTime = isset($eventData['endTime']) ? gmdate('H:i', $eventData['endTime']) : false;

            if ($beginDate && $beginDate !== '01.01.1970') {
                if ($endDate && ($endDate !== $beginDate) && $endDate !== '01.01.1970') {
                    if ($beginTime && $endTime && ($beginTime != '00:00')) {
                        $eventData['beginDateDisplay'] = $beginDateShort.' - '.$endDate;
                        $eventData['endDateDisplay'] = '';
                        $eventData['beginTimeDisplay'] = $beginTime.' - '.$endTime.' Uhr';
                        $eventData['endTimeDisplay'] = '';
                    } else if ($beginTime && ($beginTime != '00:00')) {
                        $eventData['beginDateDisplay'] = $beginDateShort.' - '.$endDate;
                        $eventData['endDateDisplay'] = '';
                        $eventData['beginTimeDisplay'] = $beginTime.' Uhr';
                        $eventData['endTimeDisplay'] = '';
                    } else {
                        $eventData['beginDateDisplay'] = $beginDateShort.' - '.$endDate;
                        $eventData['endDateDisplay'] = '';
                        $eventData['beginTimeDisplay'] = '';
                        $eventData['endTimeDisplay'] = '';
                    }
                } else {
                    if ($beginTime && $endTime && ($beginTime != '00:00')) {
                        $eventData['beginDateDisplay'] = $beginDate;
                        $eventData['endDateDisplay'] = '';
                        $eventData['beginTimeDisplay'] = $beginTime.' - '.$endTime.' Uhr';
                        $eventData['endTimeDisplay'] = '';
                    } else if ($beginTime && ($beginTime != '00:00')) {
                        $eventData['beginDateDisplay'] = $beginDate;
                        $eventData['endDateDisplay'] = '';
                        $eventData['beginTimeDisplay'] = $beginTime.' Uhr';
                        $eventData['endTimeDisplay'] = '';
                    } else {
                        $eventData['beginDateDisplay'] = $beginDate;
                        $eventData['endDateDisplay'] = '';
                        $eventData['beginTimeDisplay'] = '';
                        $eventData['endTimeDisplay'] = '';
                    }
                }
            }

            $entryTime = $eventData['entryTime'] ? gmdate('H:i', $eventData['entryTime']) : false;
            if ($entryTime) {
                $eventData['entryTime'] = $entryTime.' Uhr';
            }

            $eventPrice = $eventData['eventPrice'] ? number_format(
                    $eventData['eventPrice'],
                    2,
                    ',',
                    ''
                ) . ' €' : false;
            if ($eventPrice) {
                $eventData['eventPrice'] = $eventPrice;
            }

            if ($eventData['appointmentUponAgreement']) {
                $fieldValue = $GLOBALS['TL_LANG']['offer_list']['appointmentUponAgreementContent'];
                if ($eventData['beginDate']) {
                    $fieldValue .= ' (';
                    if (!$eventData['endDate']) {
                        $fieldValue .= $GLOBALS['TL_LANG']['offer_list']['appointmentUponAgreement_startingAt'] . ' ';
                    }
                    $fieldValue .= $eventData['beginDate'];
                    if ($eventData['beginTime']) {
                        $fieldValue .= ' ' . $eventData['beginTime'];
                    }
                    if ($eventData['endDate']) {
                        $fieldValue .= ' - ' . $eventData['endDate'];
                        if ($eventData['endTime']) {
                            $fieldValue .= ' ' . $eventData['endTime'];
                        }
                    }
                    $fieldValue .= ')';
                }
                $eventData['beginDateDisplay'] = '';
                $eventData['beginTimeDisplay'] = '';
                $eventData['endDateDisplay'] = '';
                $eventData['endTimeDisplay'] = '';
                $eventData['appointmentUponAgreement'] = $fieldValue;
                $tooOld = false;
            } else {
                $eventData['appointmentUponAgreement'] = '';
            }

            if ($eventData['expertTimes']) {
                $expertBeginTimes = StringUtil::deserialize($eventData['expertBeginDateTimes'], true);
                $expertEndTimes = StringUtil::deserialize($eventData['expertEndDateTimes'], true);
                foreach ($expertBeginTimes as $timeKey => $value) {
                    if ($value <= time() && $expertEndTimes[$timeKey] >= time()) {
                        $tooOld = false;
                    } else {
                        $tooOld = true;
                    }
                }
            }

            $eventData['locationElementName'] = $eventData['vendorName'];

            //hotfix special char
            if (key_exists("locationElementName", $eventData)) {
                $eventData['locationElementName'] = str_replace('&#39;', "'", $eventData["locationElementName"]);
            }

            $eventData['tagLinks'] = $this->helper->generateTagLinks($tagData, $offerTagRelations[$eventData['uuid']]);

            if (!$tooOld || $eventData['appointmentUponAgreement']) {
                if ($eventData['vendorName'] && $eventData['vendorAlias']) {

                    $eventData = $this->helper->setImageAndDetailLinks($eventData);

                    if (!empty($eventData)) {
                        $results[] = $eventData;
                    }
                }
            }
        }

        return $results;
    }
}