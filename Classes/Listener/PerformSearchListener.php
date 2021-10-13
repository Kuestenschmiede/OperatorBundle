<?php


namespace gutesio\OperatorBundle\Classes\Listener;


use con4gis\MapsBundle\Classes\Events\PerformSearchEvent;
use con4gis\MapsBundle\Resources\contao\models\C4gMapProfilesModel;
use con4gis\MapsBundle\Resources\contao\modules\api\SearchApi;
use Contao\Database;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

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
                ['name' => "name", 'weight' => 50],
                ['name' => "description", 'weight' => 20],
                ['name' => "contactName", 'weight' => 20],
                ['name' => "contactStreet", 'weight' => 5],
                ['name' => "contactCity", 'weight' => 5],
                ['name' => "locationStreet", 'weight' => 5],
                ['name' => "locationCity", 'weight' => 5],
                ['name' => "locationZip", 'weight' => 5],
            ];
            $arrDBResult = SearchApi::searchDatabase($arrParams['q'], $arrColums, "tl_gutesio_data_element", $this->Database);
            $arrResults = [];
            foreach ($arrDBResult as $dBResult) {
                $address = $dBResult['contactName'] ?: $dBResult['name'];
                if ($dBResult['contactStreet'] || $dBResult['locationStreet']) {
                    $address .= ", ";
                    $address .= $dBResult['contactStreet'] ?: $dBResult['locationStreet'];
                    $address .= " ";
                    $address .= $dBResult['contactStreetNumber'] ?: $dBResult['locationStreetNumber'];
                }
                if ($dBResult['contactZip'] || $dBResult['locationZip']) {
                    $address .= ", ";
                    $address .= $dBResult['contactZip'] ?: $dBResult['locationZip'];
                    $address .= " ";
                    $address .= $dBResult['contactCity'] ?: $dBResult['locationCity'];
                }
                $arrResults[] = [
                    'lat'           => $dBResult['geoy'],
                    'lon'           => $dBResult['geox'],
                    'display_name'  => $address
                ];
                $arrResults = array_merge($arrResults, $response);
                $arrResults = array_slice($arrResults, 0, $arrParams['limit'] ?: 10);
                $event->setResponse($arrResults);
            }
        }
    }
}