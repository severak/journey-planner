<?php

namespace JourneyPlanner\Lib\TransferPattern;

use JourneyPlanner\Lib\Journey\FixedLeg;
use JourneyPlanner\Lib\Journey\Leg;
use JourneyPlanner\Lib\Planner\PlanningException;

/**
 * @author Linus Norton <linusnorton@gmail.com>

 * A PatternSegment stores multiple Legs for a particular portion of a TransferPattern. For example a TransferPattern
 * of TON->SEV,SEV->LBG,LBG->CHX has three PatternSegments. One PatternSegment will contain many Legs for the same
 * section, i.e.
 *
 * PatternSegment(SEV->LBG) = [
 *   SEV_10:00 -> LBG_10:45,
 *   SEV_10:30 -> LBG_11:15,
 *   SEV_11:00 -> LBG_11:45
 * ]
 */
class PatternSegment {

    private $legs;

    /**
     * @param Leg[] $legs
     */
    public function __construct(array $legs) {
        $this->legs = $legs;
    }

    /**
     * @return Leg[]
     */
    public function getLegs(): array {
        return $this->legs;
    }

    /**
     * @return string
     */
    public function getOrigin(): string {
        return $this->legs[0]->getOrigin();
    }

    /**
     * @return bool
     */
    public function isFixedLegSegment(): bool {
        return $this->legs[0] instanceof FixedLeg;
    }

    /**
     * Return the first leg that is available at the given time. If none are found an exception is thrown.
     *
     * @param int $time
     * @return Leg
     * @throws PlanningException
     */
    public function getFirstLegAvailableAt(int $time): Leg {
        foreach ($this->legs as $leg) {
            if ($leg->isAvailableAt($time)) {
                return $leg;
            }
        }

        throw new PlanningException("No leg available at {$this->getOrigin()} after {$time}");
    }

}