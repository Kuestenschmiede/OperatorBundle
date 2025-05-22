<?php

namespace gutesio\OperatorBundle\Classes\Services;

/**
 * Loads data for offers that don't have a type with additional data stored in other table.
 * That means that e.g. "service", and "arrangement" are loaded here.
 */
class SimpleOfferDataService
{
    public function getOfferData(
        string $searchTerm,
        int $offset,
        array $filterData,
        int $limit,
        bool $determineOrientation = false
    ) {


        return [];
    }
}