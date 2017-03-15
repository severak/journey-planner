<?php

namespace JourneyPlanner\Lib\TransferPattern;
use JourneyPlanner\Lib\Journey\FixedLeg;
use JourneyPlanner\Lib\Journey\Journey;
use JourneyPlanner\Lib\Journey\TimetableLeg;
use JourneyPlanner\Lib\Planner\PlanningException;

/**
 * @author Linus Norton <linusnorton@gmail.com>
 */
class TransferPattern {

    private $segments;
    private $interchangeTimes;

    /**
     * @param PatternSegment[] $segments
     * @param array $interchangeTimes
     */
    public function __construct(array $segments, array $interchangeTimes) {
        $this->segments = $segments;
        $this->interchangeTimes = $interchangeTimes;
    }

    /**
     * Attempt to return a journey for every service in the first timetabled PatternSegment.
     *
     * @return Journey[]
     */
    public function getJourneys(): array {
        $journeys = [];
        $originFixedSegment = $this->segments[0]->isFixedLegSegment() ? $this->segments[0] : null;
        $firstLegIndex = $originFixedSegment === null ? 0 : 1;

        /** @var TimetableLeg $firstLeg */
        foreach ($this->segments[$firstLegIndex]->getLegs() as $firstLeg) {
            try {
                $initialLegs = $this->getInitialJourneyLegs($firstLeg, $originFixedSegment);
                $journeyLegs = $this->getJourneyLegs($initialLegs, $firstLegIndex + 1, $firstLeg->getArrivalTime());
                $journeys[] = new Journey($journeyLegs);
            }
            catch (PlanningException $e) {
                continue; //either a missing FixedLeg or there are no more TimetableLegs
            }
        }

        return $journeys;
    }

    /**
     * Return the "seed" legs for the journey. If the first segment is a fixed leg then the seed legs will be an array
     * of [FixedLeg, TimetableLeg]. Otherwise the given Timetable leg is wrapped in an array and returned.
     *
     * @param TimetableLeg $leg
     * @param PatternSegment|null $originFixedSegment
     * @return array
     */
    private function getInitialJourneyLegs(TimetableLeg $leg, PatternSegment $originFixedSegment = null): array {
        // check if there is a fixed leg at the start of the journey
        if ($originFixedSegment !== null) {
            $firstAvailableFixedLeg = $originFixedSegment->getFirstLegAvailableAt($leg->getDepartureTime());

            return [$firstAvailableFixedLeg, $leg];
        }

        return [$leg];
    }

    /**
     * This method finds the first available Leg after the previous arrival time and then moves on to the next
     * PatternSegment until there are no more.
     *
     * @param array $legs
     * @param int $i
     * @param int $previousArrivalTime
     * @return array
     */
    private function getJourneyLegs(array $legs, int $i, int $previousArrivalTime): array {
        if (!isset($this->segments[$i])) {
            return $legs;
        }

        $departureTime = $previousArrivalTime + $this->interchange($this->segments[$i]->getOrigin());
        $leg = $this->segments[$i]->getFirstLegAvailableAt($departureTime);
        $legs[] = $leg;

        return $this->getJourneyLegs($legs, $i + 1, $leg->getEarliestArrivalTime($departureTime));
    }

    /**
     * @param  string $station
     * @return int
     */
    private function interchange($station): int {
        return isset($this->interchangeTimes[$station]) ? $this->interchangeTimes[$station] : 0;
    }

}