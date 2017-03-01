<?php

namespace JourneyPlanner\Lib\TransferPattern\Repository;
use DateTime;
use JourneyPlanner\Lib\Journey\Repository\FixedLegRepository;
use JourneyPlanner\Lib\Journey\Repository\TimetableLegRepository;
use JourneyPlanner\Lib\Cache\Cache;
use JourneyPlanner\Lib\Station\Repository\InterchangeRepository;
use JourneyPlanner\Lib\Station\Repository\StationRepository;
use JourneyPlanner\Lib\TransferPattern\PatternSegment;
use JourneyPlanner\Lib\TransferPattern\TransferPattern;
use PDO;

/**
 * @author Linus Norton <linusnorton@gmail.com>
 */
class TransferPatternRepository {

    const NUM_PATTERNS = 10;

    private $db;
    private $timetableLegRepository;
    private $fixedLegRepository;
    private $interchangeRepository;

    /**
     * @param PDO $db
     * @param TimetableLegRepository $timetableLegRepository
     * @param FixedLegRepository $fixedLegRepository
     * @param InterchangeRepository $interchangeRepository
     */
    public function __construct(PDO $db,
                                TimetableLegRepository $timetableLegRepository,
                                FixedLegRepository $fixedLegRepository,
                                InterchangeRepository $interchangeRepository) {
        $this->db = $db;
        $this->timetableLegRepository = $timetableLegRepository;
        $this->fixedLegRepository = $fixedLegRepository;
        $this->interchangeRepository = $interchangeRepository;
    }

    /**
     * Lookup the transfer patterns and schedule separately in order to use
     * the cache.
     *
     * @param $origin
     * @param $destination
     * @param $dateTime
     * @return TransferPattern[]
     */
    public function getTransferPatterns(string $origin, string $destination, DateTime $dateTime): array {
        $interchange = $this->interchangeRepository->getInterchange();
        $fixedLegs = $this->fixedLegRepository->getFixedLegs($dateTime);
        $results = [];

        foreach ($this->getTransferPatternsFromDB($origin . $destination) as $transferPattern) {
            $segments = $this->getTransferPatternSegments($origin, $destination, $transferPattern, $dateTime, $fixedLegs);

            if (count($segments) > 0) {
                $results[] = new TransferPattern($segments, $interchange);
            }
        }
        return $results;
    }

    /**
     * @param string $journeyOrigin
     * @param string $journeyDestination
     * @param string $transferPattern
     * @param DateTime $dateTime
     * @param array $fixedLegs
     * @return array|PatternSegment[]
     */
    private function getTransferPatternSegments(string $journeyOrigin,
                                                string $journeyDestination,
                                                string $transferPattern,
                                                DateTime $dateTime,
                                                array $fixedLegs): array  {
        // split the pattern down into an array of stations, e.g. TON,SEV,SEV,LBG,MYB,BHM,BHM,WWW
        $pattern = str_split($transferPattern, 3);
        $legLength = count($pattern);
        $previousDestination = $journeyOrigin;
        $patternLegs = [];

        // iterate two at a time through each origin and destination pair
        for ($i = 0; $i < $legLength; $i += 2) {
            $origin = $pattern[$i];
            $destination = $pattern[$i + 1];

            // if the previous destination is not the origin then there must be a fixed leg in between
            if ($previousDestination !== $origin) {
                if (!isset($fixedLegs[$previousDestination][$origin])) { return []; }
                $patternLegs[] = new PatternSegment($fixedLegs[$previousDestination][$origin]);
            }

            $timetableLegs = $this->timetableLegRepository->getTimetableLegs($origin, $destination, $dateTime);

            // if any leg is missing services the whole pattern breaks down
            if (count($timetableLegs) === 0) { return []; }

            $patternLegs[] = new PatternSegment($timetableLegs);
            $previousDestination = $destination;
        }

        // check for a fixed leg at the end
        if ($previousDestination !== $journeyDestination) {
            if (!isset($fixedLegs[$previousDestination][$journeyDestination])) { return []; }
            $patternLegs[] = new PatternSegment($fixedLegs[$previousDestination][$journeyDestination]);
        }

        return $patternLegs;
    }

    /**
     * @param $journey
     * @return array
     */
    private function getTransferPatternsFromDB(string $journey): array {
        $stmt = $this->db->prepare("
          SELECT pattern 
          FROM transfer_patterns 
          WHERE journey = :journey
          ORDER BY LENGTH(pattern) 
          LIMIT ".self::NUM_PATTERNS
        );

        $stmt->execute(["journey" => $journey]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}