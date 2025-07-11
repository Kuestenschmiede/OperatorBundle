<?php
/**
 * This file belongs to gutes.digital and is published exclusively for use
 * in gutes.digital operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.digital
 */
namespace gutesio\OperatorBundle\Classes\Cron;

use con4gis\CoreBundle\Resources\contao\models\C4gLogModel;
use Contao\Database;
use Contao\StringUtil;
use Contao\System;
use con4gis\CoreBundle\Classes\Callback\C4GImportDataCallback;
use gutesio\DataModelBundle\Classes\FileUtils;

class DownloadImagesCron
{
    public function onHourly()
    {
        $db = Database::getInstance();
        $c4gOperatorSettings = $db->prepare('SELECT * FROM tl_gutesio_operator_settings')->execute()->fetchAssoc();
        $cdnUrl = $c4gOperatorSettings['cdnUrl'];
        $fileUtils = new FileUtils();
        $imagePaths = [];
        $cropWidth = 0;
        $cropHeight = 0;

        $cdnElements = $db->prepare('SELECT imageCDN, logoCDN, imageGalleryCDN FROM tl_gutesio_data_element')->execute()->fetchAllAssoc() ?: [];

        foreach ($cdnElements as $element) {
            $image = $fileUtils->addUrlToPath($cdnUrl, $element['imageCDN'], $cropWidth, $cropHeight);
            $imagePaths[] = ['image' => $image, 'extendedParam' => ''];

            $listImage = $fileUtils->addUrlToPath($cdnUrl, $element['imageCDN'], 600);
            $imagePaths[] = ['image' => $listImage, 'extendedParam' => '-small'];

            if ($element['logoCDN']) {
                $image = $fileUtils->addUrlToPath($cdnUrl, $element['logoCDN'], 0, 150);
                $imagePaths[] = ['image' => $image, 'extendedParam' => ''];
            }

            $images = StringUtil::deserialize($element['imageGalleryCDN']) ?: [];
            $idx = 0;
            foreach ($images as $image) {
                $image = $fileUtils->addUrlToPath($cdnUrl, $image, 600);
                $imagePaths[] = ['image' => $image, 'extendedParam' => ''];
            }
        }

        $cdnChilds = $db->prepare('SELECT imageCDN, imageGalleryCDN FROM tl_gutesio_data_child')->execute()->fetchAllAssoc() ?: [];

        foreach ($cdnChilds as $child) {
            $imagePaths[] = $fileUtils->addUrlToPath($cdnUrl, $child['imageCDN'], $cropWidth, $cropHeight);

            $images = StringUtil::deserialize($child['imageGalleryCDN']) ?: [];
            $idx = 0;
            foreach ($images as $image) {
                $galleryImage = $fileUtils->addUrlToPath($cdnUrl, $image, 600);
                $imagePaths[] = $imagePaths[] = ['image' => $galleryImage, 'extendedParam' => '-small'];
            }
        }

        $fileUtils->getImages($imagePaths);

    }
}
