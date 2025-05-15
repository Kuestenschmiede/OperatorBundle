<?php

namespace gutesio\OperatorBundle\Classes\Services;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\C4GVersionProvider;
use Contao\Database;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use gutesio\DataModelBundle\Resources\contao\models\GutesioDataElementModel;
use gutesio\OperatorBundle\Classes\Models\GutesioOperatorSettingsModel;

class EventDataService
{


    public function getEventData(
        string $searchTerm,
        int $offset,
        array $filterData,
        int $limit,
        bool $determineOrientation = false
    ) {

        $database = Database::getInstance();


        $parameters = [];
        $strTagFieldClause = ""; // TODO
        $sqlExtendedCategoryTerms = ""; // TODO

        $sql = 'SELECT DISTINCT a.id, a.parentChildId, a.uuid, ' .
            'a.tstamp, a.typeId, a.name, a.imageCDN, a.foreignLink, a.directLink, a.offerForSale, ' . '
                COALESCE(a.shortDescription) AS shortDescription, ' . '
                tl_gutesio_data_child_type.type, tl_gutesio_data_child_type.name as typeName, ' . '
                tl_gutesio_data_element.uuid as elementId, ' . '
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
                COALESCE(e.beginDate, e.beginTime) AS beginDateTime,
                e.expertTimes
                match(a.fullTextContent) against(\'' . $searchTerm . '\' in boolean mode) as relevance, ' . '
                a.uuid as alias, ' . '
                tl_gutesio_data_element.ownerGroupId as ownerGroupId, ' . '
                tl_gutesio_data_element.ownerMemberId as ownerMemberId ' . '
                
                FROM tl_gutesio_data_child a ' . '
                LEFT JOIN tl_gutesio_data_child_connection ON a.uuid = tl_gutesio_data_child_connection.childId ' . '
                LEFT JOIN tl_gutesio_data_element ON tl_gutesio_data_element.uuid = tl_gutesio_data_child_connection.elementId ' . '
                JOIN tl_gutesio_data_child_type ON tl_gutesio_data_child_type.uuid = a.typeId ' . '
                LEFT JOIN tl_gutesio_data_child_tag_values ON tl_gutesio_data_child_tag_values.childId = a.uuid ' . '
                LEFT JOIN tl_gutesio_data_child_event e ON e.childId = a.uuid ' . '
                WHERE a.published = 1 AND type = "event"'  .
            ' AND (match(a.fullTextContent) against(\'' . $searchTerm . '\' in boolean mode) OR ' . $strTagFieldClause . $sqlExtendedCategoryTerms . ') ' .
            ' AND (a.publishFrom = 0 OR a.publishFrom IS NULL OR a.publishFrom <= UNIX_TIMESTAMP()) AND (a.publishUntil = 0 OR a.publishUntil IS NULL OR a.publishUntil > UNIX_TIMESTAMP())'
            ;

        if ($filterData['tags']) {
            // TODO add tag id constraint to query
        }
        if ($filterData['categories']) {
            // TODO add category (type) id constraint to query
        }
        if ($filterData['date']) {
            // TODO filter event begin and end date
        }

        $sql .= sprintf(" ORDER BY relevance DESC LIMIT %s, %s", $offset, $limit);

        $eventData = $database->prepare($sql)->execute(...$parameters)->fetchAllAssoc();

        $formattedData = $this->formatEventData($eventData);

        return $formattedData;
    }

    private function formatEventData(array $events)
    {
        // do something
        $results = [];

        foreach ($events as $key => $eventData) {

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

            //ToDo fix recurring
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
                } elseif (($endDateTime > 0) && ($endDateTime->getTimestamp() < time())) {
                    $tooOld = true;
                }
            }

            if ($tooOld) {
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

            $elementModel = $eventData['locationElementId'] ? GutesioDataElementModel::findBy('uuid', $eventData['locationElementId']) : null;
            if ($elementModel !== null) {
                $eventData['locationElementName'] = html_entity_decode($elementModel->name);
            } else {
                $elementId = key_exists('elementId', $eventData) ? $eventData['elementId'] : false;
                if ($elementId) {
                    $elementModel = GutesioDataElementModel::findBy('uuid', $elementId);
                    if ($elementModel !== null) {
                        $eventData['locationElementName'] = html_entity_decode($elementModel->name);
                    }
                }
            }

            //hotfix special char
            if (key_exists("locationElementName", $eventData)) {
                $eventData['locationElementName'] = str_replace('&#39;', "'", $eventData["locationElementName"]);
            }

            if (!empty($eventData)) {
                $results[] = $eventData;
            }
        }

        // TODO prüfen was ich hier brauche, element müsste ja oben schon geladen sein
        // TODO link generierung unten vermutlich notwendig
//        if (!$tooOld || $eventData['appointmentUponAgreement'] || !$checkEventTime) {
//            $vendorUuid = $database->prepare(
//                'SELECT * FROM tl_gutesio_data_child_connection WHERE childId = ? LIMIT 1'
//            )->execute($row['uuid'])->fetchAssoc();
//
//            $vendor = $database->prepare(
//                'SELECT * FROM tl_gutesio_data_element WHERE uuid = ?'
//            )->execute($vendorUuid['elementId'])->fetchAssoc();
//
//            if ($vendor && count($vendor) && $vendor['name'] && $vendor['alias']) {
//                $childRows[$key]['elementName'] = $vendor['name'] ? html_entity_decode($vendor['name']) : '';
//
//                //hotfix special char
//                $childRows[$key]['elementName'] = str_replace('&#39;', "'", $childRows[$key]['elementName']);
//
//                $objSettings = GutesioOperatorSettingsModel::findSettings();
//                $elementPage = PageModel::findByPk($objSettings->showcaseDetailPage);
//                if ($elementPage !== null) {
//                    if ($isContao5) {
//                        $url = $elementPage->getAbsoluteUrl(['parameters' => "/" . $vendor['alias']]);
//                    } else {
//                        $url = $elementPage->getAbsoluteUrl();
//                    }
//
//                    if ($url) {
//                        $href = '';
//                        if (C4GUtils::endsWith($url, '.html')) {
//                            $href = str_replace('.html', '/' . strtolower(str_replace(['{', '}'], '', $vendor['alias'])) . '.html', $url);
//                        } else if ($vendor['alias']) {
//                            $href = $url . '/' . strtolower(str_replace(['{', '}'], '', $vendor['alias']));
//                        }
//                        $childRows[$key]['elementLink'] = $href ?: '';
//                    }
//                }
//                $childPage = match ($row['type']) {
//                    'product' => PageModel::findByPk($objSettings->productDetailPage),
//                    'jobs' => PageModel::findByPk($objSettings->jobDetailPage),
//                    'event' => PageModel::findByPk($objSettings->eventDetailPage),
//                    'arrangement' => PageModel::findByPk($objSettings->arrangementDetailPage),
//                    'service' => PageModel::findByPk($objSettings->serviceDetailPage),
//                    'person' => PageModel::findByPk($objSettings->personDetailPage),
//                    'voucher' => PageModel::findByPk($objSettings->voucherDetailPage),
//                    default => null,
//                };
//
//                if ($childPage !== null) {
//                    if (C4GVersionProvider::isContaoVersionAtLeast("5.0")) {
//                        $objRouter = System::getContainer()->get('contao.routing.content_url_generator');
//                        $url = $objRouter->generate($childPage, ['parameters' => "/" . $row['uuid']]);
//                    } else {
//                        $url = $childPage->getAbsoluteUrl();
//                    }
//
//                    if ($url) {
//                        if (C4GUtils::endsWith($url, '.html')) {
//                            $href = str_replace('.html', '/' . strtolower(str_replace(['{', '}'], '', $vendor['alias'])) . '.html', $url);
//                        } else {
//                            $href = $url . '/' . strtolower(str_replace(['{', '}'], '', $row['uuid']));
//                        }
//                        if (str_ends_with($href, "/href")) {
//                            $href = str_replace("/href", "", $href);
//                        }
//                        $childRows[$key]['childLink'] = $href ?: '';
//                    }
//                }
//
//
//
//                $this->addAdditionalDataToCache($childRows[$key]['uuid'], $childRows[$key]);
//            }
//        } else {
//            unset($childRows[$key]);
//        }

        return $results;
    }
}