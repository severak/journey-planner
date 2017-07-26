<?php

namespace JourneyPlanner\Lib\Journey\Repository;

use DateTime;
use JourneyPlanner\Lib\Journey\CallingPoint;
use JourneyPlanner\Lib\Journey\FixedLeg;
use JourneyPlanner\Lib\Journey\TimetableLeg;
use JourneyPlanner\Lib\Cache\Cache;
use PDO;

/**
 * @author Linus Norton <linusnorton@gmail.com>
 */
class TimetableLegRepository {
    const CACHE_KEY = "|TIMETABLE_LEG|";
    const TYPES = [
        0 => TimetableLeg::TRAM,
        1 => TimetableLeg::TUBE,
        2 => TimetableLeg::TRAIN,
        3 => TimetableLeg::BUS,
        4 => TimetableLeg::FERRY,
        5 => TimetableLeg::CABLE ,
        6 => TimetableLeg::REPLACEMENT_BUS,
        7 => TimetableLeg::FUNICULAR
    ];

    private $db;
    private $cache;

    /**
     * @param PDO $pdo
     * @param \JourneyPlanner\Lib\Cache\Cache $cache
     */
    public function __construct(PDO $pdo, Cache $cache) {
        $this->db = $pdo;
        $this->cache = $cache;
    }

    /**
     * Returns an array of TimetableLegs
     *
     * @param string $origin
     * @param string $destination
     * @param DateTime $dateTime
     * @return array|FixedLeg[]
     */
    public function getTimetableLegs(string $origin, string $destination, DateTime $dateTime): array {
        $date = $dateTime->format("Y-m-d");
        $dow = $dateTime->format("l");
        $key = self::CACHE_KEY.$date.$origin.$destination;

        return $this->cache->cacheMethod($key, [$this, 'getLegsFromDB'], $origin, $destination, $date, $dow);
    }

    /**
     * @param string $origin
     * @param string $destination
     * @param string $date
     * @param string $dow
     * @return array
     */
    public function getLegsFromDB(string $origin, string $destination, string $date, string $dow): array {
        $stmt = $this->db->prepare("            
            SELECT 
                trip_headsign as service,
                stop.stop_id as station,
                TIME_TO_SEC(stop.departure_time) as departure_time, 
                TIME_TO_SEC(stop.arrival_time) as arrival_time,
                TIME_TO_SEC(dept.departure_time) as leg_departure_time, 
                TIME_TO_SEC(arrv.arrival_time) as leg_arrival_time,
                agency_name AS operator,
                route_type AS type
            FROM stop_times AS dept
            JOIN stop_times AS arrv ON arrv.trip_id = dept.trip_id AND arrv.stop_sequence > dept.stop_sequence
            JOIN stop_times AS stop ON stop.trip_id = dept.trip_id AND stop.stop_sequence BETWEEN dept.stop_sequence AND arrv.stop_sequence
            JOIN trips ON dept.trip_id = trips.trip_id
            JOIN routes USING (route_id)
            JOIN agency USING (agency_id)
            JOIN calendar USING(service_id)
            WHERE dept.stop_id = :origin
            AND arrv.stop_id = :destination
            AND :startDate BETWEEN start_date AND end_date
            AND {$dow} = 1
            AND NOT EXISTS (SELECT * FROM calendar_dates WHERE date = :startDate AND calendar_dates.service_id = trips.service_id)
            ORDER BY arrv.arrival_time, stop.trip_id, stop.stop_sequence            
        ");

        $stmt->execute([
            'startDate' => $date,
            'origin' => $origin,
            'destination' => $destination
        ]);

        $result = [];
        $callingPoints = [];
        $prev = null;

        if ($stmt->rowCount() === 0) {
            return $result;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($prev !== null && $prev["service"] !== $row["service"]) {
                $result[] = new TimetableLeg(
                    $origin,
                    $destination,
                    self::TYPES[$prev["type"]],
                    $prev["leg_departure_time"],
                    $prev["leg_arrival_time"],
                    $callingPoints,
                    $prev["service"],
                    $prev["operator"]
                );

                $callingPoints = [];
            }

            $prev = $row;
            $callingPoints[] = new CallingPoint($prev["station"], $prev["arrival_time"], $prev["departure_time"]);
        }

        if ($prev !== null) {
            $result[] = new TimetableLeg(
                $origin,
                $destination,
                $prev["type"],
                $prev["leg_departure_time"],
                $prev["leg_arrival_time"],
                $callingPoints,
                $prev["service"],
                $prev["operator"]
            );
        }

        return $result;
    }

}