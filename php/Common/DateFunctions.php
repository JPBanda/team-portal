<?php

namespace TeamPortal\Common;

use \DateTime;

class DateFunctions
{
    private static $DATE_FORMAT = 'Y-m-d';
    private static $TIME_FORMAT = 'H:i:s';

    static function GetDutchDate(\DateTime $date): string
    {
        if ($date) {
            return strftime('%e %B %Y', $date->getTimestamp());
        }
    }

    static function GetDutchDateLong(\DateTime $date): string
    {
        return strftime('%A %e %B %Y', $date->getTimestamp());
    }

    static function GetYmdNotation(\DateTime $timestamp): string
    {
        return $timestamp->format(DateFunctions::$DATE_FORMAT);
    }

    static function GetTime(\DateTime $timestamp): string
    {
        return $timestamp->format('G:i');
    }

    static function CreateDateTime(string $date, string $time = "00:00:00")
    {
        if (preg_match("/^\d{2}:\d{2}$/", $time)) {
            $time .= ":00";
        }
        $format = DateFunctions::$DATE_FORMAT . " " . DateFunctions::$TIME_FORMAT;
        $timestring = $date . " " . $time;
        $date = \DateTime::createFromFormat($format, $timestring);
        return $date !== false ? $date : null;
    }

    static function AreDatesEqual(\DateTime $date1, \DateTime $date2)
    {
        $dateFormat = DateFunctions::$DATE_FORMAT;
        return $date1->format($dateFormat) === $date2->format($dateFormat);
    }

    static function GetMySqlTimestamp(\DateTime $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    static function AddMinutes(\DateTime $date, int $minutes, bool $returnString = false)
    {
        $newDate = \DateTimeImmutable::createFromMutable($date);
        $interval = new \DateInterval("PT" . abs($minutes) . "M");
        if ($minutes < 0) {
            $interval->invert = 1;
        }
        $newDate = $newDate->add($interval);
        return $returnString ? $newDate->format("H:i") : \DateTime::createFromImmutable($newDate);
    }
}
