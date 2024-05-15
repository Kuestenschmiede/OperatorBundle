<?php

namespace gutesio\OperatorBundle\Classes\Listener;

use con4gis\MapsBundle\Classes\Events\PerformSearchEvent;
use con4gis\MapsBundle\Resources\contao\models\C4gMapProfilesModel;
use con4gis\MapsBundle\Resources\contao\modules\api\SearchApi;
use Contao\Database;
use http\Env\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Contao\PageModel;

class PerformSearchListener
{
    private $Database;

    /**
     * LayerContentService constructor.
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
        $this->Database = Database::getInstance();
    }
    public function onPerformSearchDoIt(
        PerformSearchEvent $event,
        $eventName,
        EventDispatcher $eventDispatcher
    ) {
        $profileId = $event->getProfileId();
        $arrParams = $event->getArrParams();
        $response = $event->getResponse();
        $profile = C4gMapProfilesModel::findByPk($profileId);
        if ($profile && $profile->ownGeosearch) {
            $arrColums = [
                ['name' => 'name', 'weight' => 50],
                ['name' => 'description', 'weight' => 20],
                ['name' => 'contactName', 'weight' => 20],
                ['name' => 'contactStreet', 'weight' => 5],
                ['name' => 'contactCity', 'weight' => 5],
                ['name' => 'locationStreet', 'weight' => 5],
                ['name' => 'locationCity', 'weight' => 5],
                ['name' => 'locationZip', 'weight' => 5],
                ['name' => 'tl_gutesio_data_type.name', 'weight' => 40],
                ['name' => 'tl_gutesio_data_type.extendedSearchTerms', 'weight' => 40],
            ];
            $arrJoins = [
                ['table' => "tl_gutesio_data_element_type", 'columnLeft' => "tl_gutesio_data_element.uuid", 'columnRight' =>"tl_gutesio_data_element_type.elementId"],
                ['table' => "tl_gutesio_data_type", 'columnLeft' => "tl_gutesio_data_element_type.typeId", 'columnRight' =>"tl_gutesio_data_type.uuid"],
            ];
            $whereClause = "(releaseType = 'internal' OR releaseType = '')";
            $arrDBResult = SearchApi::searchDatabase($arrParams['q'], $arrColums, 'tl_gutesio_data_element', $this->Database, $whereClause, $arrJoins);
            $arrResults = [];
            foreach ($arrDBResult as $dBResult) {
                $address = $dBResult['contactName'] ?: $dBResult['name'];
                if ($dBResult['contactStreet'] || $dBResult['locationStreet']) {
                    $address .= ', ';
                    $address .= $dBResult['contactStreet'] ?: $dBResult['locationStreet'];
                    $address .= ' ';
                    $address .= $dBResult['contactStreetNumber'] ?: $dBResult['locationStreetNumber'];
                }
                if ($dBResult['contactZip'] || $dBResult['locationZip']) {
                    $address .= ', ';
                    $address .= $dBResult['contactZip'] ?: $dBResult['locationZip'];
                    $address .= ' ';
                    $address .= $dBResult['contactCity'] ?: $dBResult['locationCity'];
                }
                $arrResults[] = [
                    'uuid'          => $dBResult['uuid'],
                    'lat'           => $dBResult['geoy'],
                    'lon'           => $dBResult['geox'],
                    'display_name'  => $address,
                ];
            }

            if (count($arrResults)) {
                if (!$profile->preventGeosearch && $response && is_array($response)) {
                    $arrResults = array_merge($arrResults, $response);
                }
                if ($profile->linkGeosearch) {
                    $arrLinks = unserialize($profile->linkGeosearch);
                    $insertPosition = count($arrResults) > 5 ? 3 : count($arrResults);
                    $insertPosition = $insertPosition < 0 ? 0 : $insertPosition;
                    foreach ($arrLinks as $link) {
                        $pageModel = PageModel::findByPk($link['link']);
                        if ($pageModel && $link['linkText']) {
                            $elementLink = [
                                'display_name'  => $link['linkText'],
                                'href'          => $pageModel->getFrontendUrl()
                            ];
                            array_splice($arrResults, $insertPosition, 0, [$elementLink]);
                        }
                    }
                }
                if ($profile->geosearch_results) {
                    $arrResults = array_slice($arrResults, 0, $arrParams['limit'] ?: 10);
                }
                $event->setResponse($arrResults);
            }
        }
    }
}
