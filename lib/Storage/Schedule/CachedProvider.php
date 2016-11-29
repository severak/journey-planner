<?php

namespace JourneyPlanner\Lib\Storage\Schedule;

use JourneyPlanner\Lib\Network\Leg;
use JourneyPlanner\Lib\Network\NonTimetableConnection;
use JourneyPlanner\Lib\Network\TimetableConnection;
use JourneyPlanner\Lib\Network\TransferPatternLeg;
use JourneyPlanner\Lib\Network\TransferPatternSchedule;
use JourneyPlanner\Lib\Storage\Cache\Cache;
use JourneyPlanner\Lib\Storage\Schedule\DefaultProvider;
use JourneyPlanner\Lib\Storage\Schedule\ScheduleProvider;
use JourneyPlanner\Lib\Storage\Schedule\TransferPatternScheduleFactory;
use PDO;

/**
 * @author Linus Norton <linusnorton@gmail.com>
 */
class CachedProvider extends DefaultProvider implements ScheduleProvider {

    const TP_CACHE_KEY = "|TRANSFER_PATTERN|",
          TT_CACHE_KEY = "|TIMETABLE|",
          NT_CACHE_KEY = "|NON_TIMETABLE|",
          IN_CACHE_KEY = "|INTERCHANGE|";

    const NUM_PATTERNS = 10;

    /**
     * @var PDO
     */
    private $db;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @param PDO $pdo
     * @param Cache $cache
     */
    public function __construct(PDO $pdo, Cache $cache) {
        parent::__construct($pdo);

        $this->db = $pdo;
        $this->cache = $cache;
    }

    /**
     * Lookup the transfer patterns and schedule separately in order to use
     * the cache.
     *
     * @param $origin
     * @param $destination
     * @param $startTimestamp
     * @return TransferPatternSchedule[]
     */
    public function getTimetable($origin, $destination, $startTimestamp) {
        $dow = lcfirst(gmdate('l', $startTimestamp));
        $date = gmdate("Y-m-d", $startTimestamp);
        $results = [];

        foreach ($this->getTransferPatterns($origin . $destination) as $transferPattern) {
            $scheduleLegs = $this->getTransferPatternLeg($transferPattern, $date, $dow);

            if (count($scheduleLegs)) {
                $results[] = new TransferPatternSchedule($scheduleLegs);
            }
        }


        return $results;
    }

    private function getTransferPatternLeg($transferPattern, $date, $dow) {
        $pattern = str_split($transferPattern, 3);
        $legLength = count($pattern);
        $patternLegs = [];

        for ($i = 0; $i < $legLength; $i += 2) {
            $legs = $this->getScheduleSegment($pattern[$i], $pattern[$i + 1], $date, $dow);

            if (count($legs) > 0) {
                $patternLegs[] = new TransferPatternLeg($legs);
            }
        }

        return $patternLegs;
    }

    /**
     * @param $journey
     * @return array
     */
    private function getTransferPatterns($journey) {
        return $this->db->query("
          SELECT pattern 
          FROM transfer_patterns 
          WHERE journey = '{$journey}'
          ORDER BY LENGTH(pattern) 
          LIMIT ".self::NUM_PATTERNS
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param $origin
     * @param $destination
     * @param $date
     * @param $dow
     * @return array
     */
    private function getScheduleSegment($origin, $destination, $date, $dow) {
        $cacheKey = self::TT_CACHE_KEY.$origin.$destination.$date;
        $cachedValue = $this->cache->getObject($cacheKey);

        if ($cachedValue !== false) {
            return $cachedValue;
        }

        $stmt = $this->db->prepare("            
            SELECT 
                train_uid as service,
                ostation.parent_station as origin, 
                dstation.parent_station as destination, 
                TIME_TO_SEC(dept.departure_time) as departure_time, 
                TIME_TO_SEC(arrv.arrival_time) as arrival_time,
                atoc_code AS operator,
                IF (train_category='BS' OR train_category='BR', 'bus', 'train') AS type
            FROM stop_times AS dept
            JOIN stops AS ostation ON dept.stop_id = ostation.stop_id
            JOIN stop_times AS arrv ON arrv.trip_id = dept.trip_id AND arrv.stop_sequence > dept.stop_sequence
            JOIN stops AS dstation ON arrv.stop_id = dstation.stop_id
            JOIN trips ON dept.trip_id = trips.trip_id
            JOIN calendar USING(service_id)
            WHERE ostation.parent_station = :origin
            AND dstation.parent_station = :destination
            AND :startDate BETWEEN start_date AND end_date
            AND {$dow} = 1
            ORDER BY arrv.arrival_time, dept.trip_id, dept.stop_sequence            
        ");

        $stmt->execute([
            'startDate' => $date,
            'origin' => $origin,
            'destination' => $destination
        ]);

        $result = [];

        while ($service = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = new Leg([new TimetableConnection(
                $service["origin"],
                $service["destination"],
                $service["departure_time"],
                $service["arrival_time"],
                $service["service"],
                $service["operator"],
                $service["type"]
            )]);
        }

        $this->cache->setObject($cacheKey, $result);

        return $result;
    }

    /**
     * @param int $targetTimestamp
     * @return NonTimetableConnection[]
     */
    public function getNonTimetableConnections($targetTimestamp) {
        $result = $this->cache->getObject(self::NT_CACHE_KEY.$targetTimestamp);

        if ($result !== false) {
            return $result;
        }

        $result = parent::getNonTimetableConnections($targetTimestamp);
        $this->cache->setObject(self::NT_CACHE_KEY.$targetTimestamp, $result);

        return $result;
    }

    /**
     * @return array
     */
    public function getInterchangeTimes() {
        $result = $this->cache->getObject(self::IN_CACHE_KEY);

        if ($result !== false) {
            return $result;
        }

        $result = parent::getInterchangeTimes();
        $this->cache->setObject(self::IN_CACHE_KEY, $result);

        return $result;
    }
}