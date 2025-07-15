<?php

namespace gutesio\OperatorBundle\Classes\Services;

use con4gis\CoreBundle\Classes\C4GUtils;
use Doctrine\DBAL\Connection;

class ShowcaseExportService
{


    public function __construct(private Connection $connection)
    {
    }

    public function getTypeOptions()
    {
        $types = $this->connection->prepare("SELECT name, uuid FROM tl_gutesio_data_type ORDER BY `name` ASC")
            ->executeQuery()->fetchAllAssociative();

        $options = [];

        foreach ($types as $type) {
            $options[$type['uuid']] = $type['name'];
        }

        return $options;
    }

    public function createExportData(array $types)
    {
        $query = "SELECT DISTINCT elem.* FROM tl_gutesio_data_element AS elem JOIN tl_gutesio_data_element_type AS elemType ON elem.uuid = elemType.elementId JOIN tl_gutesio_data_type AS type ON elemType.typeId = type.uuid ";

        $query .= "WHERE elem.published = 1 AND elemType.typeId " . C4GUtils::buildInString($types);

        $query .= " ORDER BY elem.name ASC";

        $showcaseData = $this->connection->prepare($query)
            ->executeQuery($types)->fetchAllAssociative();

        // TODO additional data cleaning if needed

        return $showcaseData;
    }
}