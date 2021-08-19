<?php
/**
 * This file belongs to gutes.io and is published exclusively for use
 * in gutes.io operator or provider pages.

 * @package    gutesio
 * @copyright  Küstenschmiede GmbH Software & Design (Matthias Eilers)
 * @link       https://gutes.io
 */
namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\CoreBundle\Classes\C4GUtils;
use con4gis\CoreBundle\Classes\Events\AdditionalImportProxyDataEvent;
use Contao\Database;
use Contao\FilesModel;
use Contao\StringUtil;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Contao\System;

class AdditionalImportProxyDataListener
{
    public function importProxyData(AdditionalImportProxyDataEvent $event, $eventName, EventDispatcherInterface $eventDispatcher)
    {
        $installedPackages = System::getContainer()->getParameter('kernel.packages');
        $operatorVersion = $installedPackages['gutesio/operator'];
        $dataModelVersion = $installedPackages['gutesio/data-model'];

        //check if all images for import are correctly linked
        $db = Database::getInstance();

        $dbImageFields = ['imageOffer', 'infoFile', ['array', 'imageGallery']];
        $slcDataChild = $db->prepare('SELECT * FROM tl_gutesio_data_child')
            ->execute();
        $missingFiles = [];
        while ($dataChild = $slcDataChild->fetchAssoc()) {
            foreach ($dbImageFields as $imageField) {
                if (is_array($imageField)) {
                    $galleryImages = unserialize($dataChild[$imageField[1]]);
                    $missingFileCheck = $this->checkMissingImage($galleryImages, $dataChild, $imageField, $missingFiles);
                } else {
                    $image = $dataChild[$imageField];
                    $missingFileCheck = $this->checkMissingImage($image, $dataChild, $imageField, $missingFiles);
                }
                if ($missingFileCheck) {
                    $missingFiles = $missingFileCheck;
                }
            }
        }
        if ($missingFiles) {
            $missingFiles['messageSend'] = 0;
            $missingFiles = serialize($missingFiles);
        } else {
            $missingFiles = 'a:0:{}';
        }

        $proxyData = [
            [
                'proxyKey' => 'operatorVersion',
                'proxyData' => $operatorVersion,
            ],
            [
                'proxyKey' => 'dataModelVersion',
                'proxyData' => $dataModelVersion,
            ],
            [
                'proxyKey' => 'missingImportImages',
                'proxyData' => $missingFiles,
            ],
        ];

        $event->setProxyData($proxyData);
    }

    private function checkMissingImage($image, $dataChild, $imageField, $missingFiles, $imageItem = false)
    {
        if (is_array($image)) {
            foreach ($image as $imageItem => $imageValue) {
                $missingFileCheck = $this->checkMissingImage($imageValue, $dataChild, $imageField, $missingFiles, $imageItem);
                if ($missingFileCheck) {
                    $missingFiles = $missingFileCheck;
                }
            }

            return $missingFiles;
        }
        if ($image != '' || $image != null) {
            if (!C4GUtils::isValidGUID($image)) {
                $imageUuid = StringUtil::binToUuid($image);
            } else {
                $imageUuid = $image;
            }
            if ($imageUuid == '00000000-0000-0000-0000-000000000000') {
                return false;
            }
            $fileModel = FilesModel::findByUuid($imageUuid);
            if (!$fileModel) {
                if ($imageItem !== false) {
                    $missingFiles[$dataChild['name']][$imageField[1]][$imageItem] = $imageUuid;
                } else {
                    $missingFiles[$dataChild['name']][$imageField] = $imageUuid;
                }

                return $missingFiles;
            }
        } else {
            return false;
        }

        return false;
    }
}
