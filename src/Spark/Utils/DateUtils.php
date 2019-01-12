<?php

namespace Spark\Utils;

use DateTime;
use Spark\Utils\Asserts;

class DateUtils {

    public static function now(): DateTime {
        return new DateTime();
    }

    public static function getLaterDate(DateTime $date1, DateTime $date2): DateTime {
        return self::isAfter($date1, $date2) ? $date1 : $date2;
    }

    public static function isAfter(DateTime $date1, DateTime $date2): bool {
        return $date1 > $date2;
    }

    public static function isAfterEqual(DateTime $date, DateTime $afterDate): bool {
        return $date >= $afterDate;
    }

    /**
     * $date is before $beforeDate
     *
     * @param DateTime $date
     * @param DateTime $beforeDate
     * @return bool
     */
    public static function isBefore(DateTime $date, DateTime $beforeDate): bool {
        return $date < $beforeDate;
    }

    public static function isBeforeEqual(DateTime $date, DateTime $beforeDate): bool {
        return $date <= $beforeDate;
    }

    public static function format(DateTime $date, $format): string {
        Asserts::notNull($date, 'Date cannot be null.');
        Asserts::notNull($format, 'Date Format cannot be null.');
        return $date->format($format);
    }

    public static function toDate($value, $format) {
        return DateTime::createFromFormat($format, $value);
    }

    public static function getDaysBetween(DateTime $from, DateTime $to) {
        $diff = $to->getTimestamp() - $from->getTimestamp();
        return floor($diff / (24 * 60 * 60));
    }

    public static function getYearsBetween(DateTime $from, DateTime $to) {
        return date_diff($from, $to)->y;
    }

    public static function setTime($hours, $minuts, $seconds): DateTime {
        return self::now()
            ->setTime($hours, $minuts, $seconds);
    }

    public static function compare(DateTime $date1, DateTime $date2): int {
        return $date1->getTimestamp() - $date2->getTimestamp();
    }

}
