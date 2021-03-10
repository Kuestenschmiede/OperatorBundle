<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes;

use Contao\Database;

class ImportInsertTags
{
    /**
     * Replaces Insert tags for showcases. The insert tag is expected to have the following format:
     * {{gutesio::version}}
     * @param string $strTag
     * @return string|bool|array
     */
    public function replaceImportTags(string $strTag)
    {
        $db = Database::getInstance();
        if ($strTag) {
            $arrSplit = explode('::', $strTag);

            if ($arrSplit && (($arrSplit[0] == 'gutes')) && isset($arrSplit[1])) {
                $fieldName = $arrSplit[1];
                switch ($fieldName) {
                    case 'version':
                        $import = $db->prepare("SELECT caption, importVersion FROM tl_c4g_import_data WHERE type='gutesio' && source='gutesio'")
                                    ->execute()->fetchAssoc();

                        return $import['importVersion'] ? $import['importVersion'] : 'not-installed';
                    default:
                        return 'unknown';
                }
            }
        }

        return false;
    }
}
