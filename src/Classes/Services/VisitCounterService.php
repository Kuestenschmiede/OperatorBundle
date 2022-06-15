<?php

namespace gutesio\OperatorBundle\Classes\Services;

use con4gis\CoreBundle\Classes\C4GUtils;
use Contao\Database;

class VisitCounterService
{
    private $database;

    const SHOWCASE_TABLE_NAME = 'tl_gutesio_showcase_statistic';

    const OFFER_TABLE_NAME = 'tl_gutesio_offer_statistic';

    /**
     * VisitCounterService constructor.
     */
    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    public function countShowcaseVisit($showcaseId, $ownerId)
    {
        if ($showcaseId && $ownerId) {
            $date = $this->getTimestampForCurrentDay();
            $checkResult = $this->database
                ->prepare('SELECT * FROM ' . self::SHOWCASE_TABLE_NAME . ' WHERE `showcaseId` = ? AND `date` = ?')
                ->execute($showcaseId, $date)->fetchAllAssoc();
            if (count($checkResult) > 0) {
                // entry exists
                $counter = $checkResult[0]['visits'];
                $counter++;
                $sql = 'UPDATE ' . self::SHOWCASE_TABLE_NAME . ' SET `visits` = ? WHERE `date` = ? AND `showcaseId` = ?';
                $this->database->prepare($sql)->execute($counter, $date, $showcaseId);
            } else {
                $sql = 'INSERT INTO ' . self::SHOWCASE_TABLE_NAME . ' (`uuid`, `showcaseId`, `date`, `visits`, `ownerId`)  VALUES (?, ?, ?, ?, ?)';
                $this->database->prepare($sql)->execute(
                    C4GUtils::getGUID(),
                    $showcaseId,
                    $date,
                    1,
                    $ownerId
                );
            }
        }
    }

    public function countOfferVisit($offerId, $ownerId)
    {
        if ($offerId && $ownerId) {
            $date = $this->getTimestampForCurrentDay();
            $checkResult = $this->database
                ->prepare('SELECT * FROM ' . self::OFFER_TABLE_NAME . ' WHERE `offerId` = ? AND `date` = ?')
                ->execute($offerId, $date)->fetchAllAssoc();
            if (count($checkResult) > 0) {
                // entry exists
                $counter = $checkResult[0]['visits'];
                $counter++;
                $sql = 'UPDATE ' . self::OFFER_TABLE_NAME . ' SET `visits` = ? WHERE `date` = ? AND `offerId` = ?';
                $this->database->prepare($sql)->execute($counter, $date, $offerId);
            } else {
                $sql = 'INSERT INTO ' . self::OFFER_TABLE_NAME . ' (`uuid`, `offerId`, `date`, `visits`, `ownerId`)  VALUES (?, ?, ?, ?, ?)';
                $this->database->prepare($sql)->execute(
                    C4GUtils::getGUID(),
                    $offerId,
                    $date,
                    1,
                    $ownerId
                );
            }
        }
    }

    /**
     * Returns the timestamp for the current day at midnight.
     * @return false|int
     */
    private function getTimestampForCurrentDay()
    {
        return strtotime('today midnight');
    }
}
