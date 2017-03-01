<?php

use JourneyPlanner\Lib\Journey\CallingPoint;
use JourneyPlanner\Lib\Journey\FixedLeg;
use JourneyPlanner\Lib\Journey\Journey;
use JourneyPlanner\Lib\Journey\Leg;
use JourneyPlanner\Lib\Journey\TimetableLeg;
use JourneyPlanner\Lib\Planner\TransferPatternPlanner;
use JourneyPlanner\Lib\TransferPattern\PatternSegment;
use JourneyPlanner\Lib\TransferPattern\TransferPattern;

class TransferPatternPlannerTest extends PHPUnit_Framework_TestCase {

    public function testBasicJourney() {
        $pattern = new TransferPattern([
            new PatternSegment([
                new TimetableLeg("A", "B", Leg::TRAIN, 1000, 1015, [new CallingPoint("A", null, 1000), new CallingPoint("B", 1015)], "LN1111", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1100, 1115, [new CallingPoint("A", null, 1100), new CallingPoint("B", 1115)], "LN1113", "LN"),
            ]),
            new PatternSegment([
                new TimetableLeg("B", "C", Leg::TRAIN, 1020, 1045, [], "LN1121", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1100, 1145, [], "LN1122", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1200, 1215, [], "LN1123", "LN"),
            ]),
            new PatternSegment([
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1300, 1315, [], "LN1133", "LN"),
            ])
        ], []);

        $journeys = $pattern->getJourneys();

        $this->assertEquals([
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1000, 1015, [new CallingPoint("A", null, 1000), new CallingPoint("B", 1015)], "LN1111", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1020, 1045, [], "LN1121", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
            ]),
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1100, 1145, [], "LN1122", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
            ]),
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1100, 1115, [new CallingPoint("A", null, 1100), new CallingPoint("B", 1115)], "LN1113", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1200, 1215, [], "LN1123", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1300, 1315, [], "LN1133", "LN"),
            ])
        ], $journeys);
    }

    public function testJourneyWithFixedLeg() {
        $pattern = new TransferPattern([
            new PatternSegment([
                new TimetableLeg("A", "B", Leg::TRAIN, 1000, 1015, [new CallingPoint("A", null, 1000), new CallingPoint("B", 1015)], "LN1111", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1100, 1115, [new CallingPoint("A", null, 1100), new CallingPoint("B", 1115)], "LN1113", "LN"),
            ]),
            new PatternSegment([
                new FixedLeg("B", "C", Leg::WALK, 5, 0, 999999)
            ]),
            new PatternSegment([
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1300, 1315, [], "LN1133", "LN"),
            ])
        ], []);

        $journeys = $pattern->getJourneys();

        $this->assertEquals([
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1000, 1015, [new CallingPoint("A", null, 1000), new CallingPoint("B", 1015)], "LN1111", "LN"),
                new FixedLeg("B", "C", Leg::WALK, 5, 0, 999999),
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
            ]),
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new FixedLeg("B", "C", Leg::WALK, 5, 0, 999999),
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
            ]),
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1100, 1115, [new CallingPoint("A", null, 1100), new CallingPoint("B", 1115)], "LN1113", "LN"),
                new FixedLeg("B", "C", Leg::WALK, 5, 0, 999999),
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
            ]),
        ], $journeys);
    }

    public function testCantMakeUnreachableConnectionsWithTransfer() {
        $pattern = new TransferPattern([
            new PatternSegment([
                new TimetableLeg("A", "B", Leg::TRAIN, 1000, 1015, [new CallingPoint("A", null, 1000), new CallingPoint("B", 1015)], "LN1111", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1100, 1115, [new CallingPoint("A", null, 1100), new CallingPoint("B", 1115)], "LN1113", "LN"),
            ]),
            new PatternSegment([
                new FixedLeg("B", "C", Leg::WALK, 15, 0, 999999)
            ]),
            new PatternSegment([
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1300, 1315, [], "LN1133", "LN"),
            ])
        ], []);

        $journeys = $pattern->getJourneys();

        $this->assertEquals([
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1000, 1015, [new CallingPoint("A", null, 1000), new CallingPoint("B", 1015)], "LN1111", "LN"),
                new FixedLeg("B", "C", Leg::WALK, 15, 0, 999999),
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
            ]),
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new FixedLeg("B", "C", Leg::WALK, 15, 0, 999999),
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
            ]),
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1100, 1115, [new CallingPoint("A", null, 1100), new CallingPoint("B", 1115)], "LN1113", "LN"),
                new FixedLeg("B", "C", Leg::WALK, 15, 0, 999999),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
            ]),
        ], $journeys);
    }

    public function testJourneyWithUnreachableTimetableLegs() {
        $pattern = new TransferPattern([
            new PatternSegment([
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1500, 1515, [], "LN1113", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1700, 1715, [], "LN1114", "LN"),
            ]),
            new PatternSegment([
                new TimetableLeg("B", "C", Leg::TRAIN, 1020, 1045, [], "LN1121", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1100, 1145, [], "LN1122", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1200, 1215, [], "LN1123", "LN"),
            ]),
            new PatternSegment([
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1300, 1315, [], "LN1133", "LN"),
            ])
        ], []);

        $journeys = $pattern->getJourneys();

        $this->assertEquals([
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1100, 1145, [], "LN1122", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
            ])
        ], $journeys);
    }

    public function testJourneyWithFirstTimetableLegUnreachable() {
        $pattern = new TransferPattern([
            new PatternSegment([
                new TimetableLeg("A", "B", Leg::TRAIN, 1400, 1415, [], "LN1111", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1520, 1545, [], "LN1112", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1600, 1615, [], "LN1113", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1700, 1715, [], "LN1114", "LN"),
            ]),
            new PatternSegment([
                new TimetableLeg("B", "C", Leg::TRAIN, 1020, 1045, [], "LN1121", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1100, 1145, [], "LN1122", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1200, 1215, [], "LN1123", "LN"),
            ]),
            new PatternSegment([
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1300, 1315, [], "LN1133", "LN"),
            ])
        ], []);

        $journeys = $pattern->getJourneys();

        $this->assertEquals([], $journeys);
    }

    public function testJourneyWithTransferForFirstTimetableLeg() {
        $pattern = new TransferPattern([
            new PatternSegment([
                new FixedLeg("A", "B", Leg::WALK, 5, 0, 999999)
            ]),
            new PatternSegment([
                new TimetableLeg("B", "C", Leg::TRAIN, 1100, 1145, [], "LN1122", "LN"),
            ]),
            new PatternSegment([
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
            ])
        ], []);

        $journeys = $pattern->getJourneys();

        $this->assertEquals([
            new Journey([
                new FixedLeg("A", "B", Leg::WALK, 5, 0, 999999),
                new TimetableLeg("B", "C", Leg::TRAIN, 1100, 1145, [], "LN1122", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
            ])
        ], $journeys);
    }

    public function testJourneyWithCallingPoints() {
        $pattern = new TransferPattern([
            new PatternSegment([
                new TimetableLeg("A", "B", Leg::TRAIN, 1000, 1015, [new CallingPoint("A", null, 1000), new CallingPoint("B", 1015)], "LN1111", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1100, 1115, [new CallingPoint("A", null, 1100), new CallingPoint("B", 1115)], "LN1113", "LN"),
            ]),
            new PatternSegment([
                new TimetableLeg("B", "D", Leg::TRAIN, 1020, 1045, [new CallingPoint("B", null, 1020), new CallingPoint("C", 1025, 1025), new CallingPoint("D", 1045)], "LN1121", "LN"),
                new TimetableLeg("B", "D", Leg::TRAIN, 1100, 1145, [new CallingPoint("B", null, 1100), new CallingPoint("C", 1125, 1125), new CallingPoint("D", 1145)], "LN1122", "LN"),
                new TimetableLeg("B", "D", Leg::TRAIN, 1200, 1215, [new CallingPoint("B", null, 1200), new CallingPoint("D", 1215)], "LN1123", "LN"),
            ])
        ], []);

        $journeys = $pattern->getJourneys();

        $this->assertEquals([
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1000, 1015, [new CallingPoint("A", null, 1000), new CallingPoint("B", 1015)], "LN1111", "LN"),
                new TimetableLeg("B", "D", Leg::TRAIN, 1020, 1045, [new CallingPoint("B", null, 1020), new CallingPoint("C", 1025, 1025), new CallingPoint("D", 1045)], "LN1121", "LN"),
            ]),
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new TimetableLeg("B", "D", Leg::TRAIN, 1100, 1145, [new CallingPoint("B", null, 1100), new CallingPoint("C", 1125, 1125), new CallingPoint("D", 1145)], "LN1122", "LN"),
            ]),
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1100, 1115, [new CallingPoint("A", null, 1100), new CallingPoint("B", 1115)], "LN1113", "LN"),
                new TimetableLeg("B", "D", Leg::TRAIN, 1200, 1215, [new CallingPoint("B", null, 1200), new CallingPoint("D", 1215)], "LN1123", "LN"),
            ])
        ], $journeys);
    }

    /**
     * This test demonstrates that the algorithm will work correctly when the legs are not ordered by departure time
     * but it also highlights the fact that they MUST be ordered by arrival time to get sensible results.
     */
    public function testJourneyWithOvertakenTimetableLeg() {
        $pattern = new TransferPattern([
            new PatternSegment([
                new TimetableLeg("A", "B", Leg::TRAIN, 1000, 1015, [new CallingPoint("A", null, 1000), new CallingPoint("B", 1015)], "LN1111", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new TimetableLeg("A", "B", Leg::TRAIN, 1100, 1115, [new CallingPoint("A", null, 1100), new CallingPoint("B", 1115)], "LN1113", "LN"),
            ]),
            new PatternSegment([
                new TimetableLeg("B", "C", Leg::TRAIN, 1020, 1045, [], "LN1121", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1130, 1155, [], "LN1123", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1100, 1205, [], "LN1122", "LN"),
            ]),
            new PatternSegment([
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1300, 1315, [], "LN1133", "LN"),
            ])
        ], []);

        $journeys = $pattern->getJourneys();

        $this->assertEquals([
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1000, 1015, [new CallingPoint("A", null, 1000), new CallingPoint("B", 1015)], "LN1111", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1020, 1045, [], "LN1121", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1120, 1145, [], "LN1131", "LN"),
            ]),
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1020, 1045, [new CallingPoint("A", null, 1020), new CallingPoint("B", 1045)], "LN1112", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1130, 1155, [], "LN1123", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
            ]),
            new Journey([
                new TimetableLeg("A", "B", Leg::TRAIN, 1100, 1115, [new CallingPoint("A", null, 1100), new CallingPoint("B", 1115)], "LN1113", "LN"),
                new TimetableLeg("B", "C", Leg::TRAIN, 1130, 1155, [], "LN1123", "LN"),
                new TimetableLeg("C", "D", Leg::TRAIN, 1200, 1245, [], "LN1132", "LN"),
            ])
        ], $journeys);
    }

}
