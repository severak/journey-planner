<?php

namespace JourneyPlanner\Lib\Planner;

use DateTime;
use JourneyPlanner\Lib\Journey\Journey;
use JourneyPlanner\Lib\Journey\Repository\FixedLegRepository;
use JourneyPlanner\Lib\Station\Repository\InterchangeRepository;
use JourneyPlanner\Lib\Planner\Filter\JourneyFilter;
use JourneyPlanner\Lib\Station\Repository\StationRepository;
use JourneyPlanner\Lib\TransferPattern\Repository\TransferPatternRepository;

/**
 * @author Linus Norton <linusnorton@gmail.com>
 */
class GroupStationPlanner {

    private $patternRepository;
    private $stationRepository;
    private $filters;

    /**
     * @param TransferPatternRepository $scheduleProvider
     * @param StationRepository $stationRepository
     * @param JourneyFilter[] $filters
     */
    public function __construct(TransferPatternRepository $scheduleProvider,
                                StationRepository $stationRepository,
                                array $filters) {
        $this->patternRepository = $scheduleProvider;
        $this->stationRepository = $stationRepository;
        $this->filters = $filters;
    }

    /**
     * Get journeys for all the relevant origins and destinations, combine them and then filter them with the given
     * filters.
     *
     * @param string $origin
     * @param string $destination
     * @param DateTime $dateTime
     * @return Journey[]
     */
    public function getJourneys(string $origin, string $destination, DateTime $dateTime): array {
        $results = [];

        foreach ($this->stationRepository->getRelevantStations($origin) as $o) {
            foreach ($this->stationRepository->getRelevantStations($destination) as $d) {
                foreach ($this->patternRepository->getTransferPatterns($o, $d, $dateTime) as $pattern) {
                    $results = array_merge($results, $pattern->getJourneys());
                }
            }
        }

        foreach ($this->filters as $filter) {
            $results = $filter->filter($results);
        }

        return $results;
    }
}