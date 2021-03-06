<?php
/**
 * Pacifica
 *
 * Pacifica is an open-source data management framework designed
 * for the curation and storage of raw and processed scientific
 * data. It is based on the [CodeIgniter web framework](http://codeigniter.com).
 *
 *  The Pacifica-Reporting module provides an interface for
 *  concerned and interested parties to view the current
 *  contribution status of any and all instruments in the
 *  system. The reporting interface can be customized and
 *  filtered streamline the report to fit any level of user,
 *  from managers through instrument operators.
 *
 *  This file contains a number of common functions related to
 *  file info and handling.
 *
 * PHP version 5.5
 *
 * @package Pacifica-reporting
 *
 * @author  Ken Auberry <kenneth.auberry@pnnl.gov>
 * @license BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @link http://github.com/EMSL-MSC/Pacifica-reporting
 */

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 *  Formats a time as a loose human readable approximation
 *  for display purposes ('a few minutes ago', 'about a month ago')
 *
 * @param datetime $datetime_object the object to format
 * @param datetime $base_time_obj   the time to which to
 *                                  compare the main datetime
 *                                  object
 * @param boolean  $use_ago         should we include the word
 *                                  ago in the returned value?
 *
 * @return string
 *
 * @author Ken Auberry <kenneth.auberry@pnnl.gov>
 */
function friendlyElapsedTime($datetime_object, $base_time_obj = false, $use_ago = true)
{
    date_default_timezone_set('America/Los_Angeles');

    if (!$base_time_obj) {
        $base_time_obj = new DateTime();
    }
    //convert to time object if string
    if (is_string($datetime_object)) {
        $datetime_object = new DateTime($time);
    }

    $nowTime = $base_time_obj;

    $diff = $nowTime->getTimestamp() - $datetime_object->getTimestamp();

    $result = "";

    //calc and subtract years
    $years = floor($diff/60/60/24/365);
    if ($years > 0) {
        $diff -= $years*60*60*24*365;
    }

    //calc and subtract months
    $months = floor($diff/60/60/24/30);
    if ($months > 0) {
        $diff -= $months*60*60*24*30;
    }

    //calc and subtract weeks
    $weeks = floor($diff/60/60/24/7);
    if ($weeks > 0) {
        $diff -= $weeks*60*60*24*7;
    }

    //calc and subtract days
    $days = floor($diff/60/60/24);
    if ($days > 0) {
        $diff -= $days*60*60*24;
    }

    //calc and subtract hours
    $hours = floor($diff/60/60);
    if ($hours >0) {
        $diff -= $hours*60*60;
    }

    //calc and subtract minutes
    $min = floor($diff/60);
    if ($min > 0) {
        $diff -= $min*60;
    }

    $qualifier = "about";



    if ($years > 0) {
        //      $qualifier = $months > 1 ? "over" : "about";
        $unit = $years > 1 ? "years" : "year";
        //      $years = $years == 1 ? "a" : $years;
        $result[] = "{$years} {$unit}";
    }
    if ($months > 0) {
        //      $qualifier = $weeks > 1 ? "just over" : "about";
        $unit = $months > 1 ? "months" : "month";
        //      $months = $months == 1 ? "a" : $months;
        $result[] = "{$months} {$unit}";
    }
    if ($weeks > 0) {
        ////      $qualifier = $days > 2 ? "about" : "about";
        $unit = $weeks > 1 ? "weeks" : "week";
        $result[] = "{$weeks} {$unit}";
    }
    if ($days > 0) {
        $unit = $days > 1 ? "days" : "day";
        //      $days = $days == 1 ? "a" : $days;
        $result[] = "{$days} {$unit}";
    }
    if ($hours > 0) {
        $unit = $hours > 1 ? "hrs" : "hr";
        //      $hours = $hours == 1 ? "a" : $hours;
        $result[] = "{$hours} {$unit}";
    }
    if ($min > 0) {
        //      $qualifier = $diff > 20 ? "a bit over" : "about";
        $unit = $min > 1 ? "min" : "min";
        //      $min = $min == 1 ? "a" : $min;
        $result[] = "{$min} {$unit}";
    }
    if ($diff > 0) {
        $unit = $diff > 1 ? "sec" : "sec";
        if (empty($result)) {
            $result[] = "{$diff} {$unit}";
        }
    } else {
        $result[] = "0 seconds";
    }
    $ago = $use_ago ? " ago" : "";
    //format string
    $result_string = sizeof($result) > 1 ? "~".array_shift($result)." ".array_shift($result)."{$ago}" : "~".array_shift($result)."{$ago}";
    return $result_string;
}

/**
 *  Generate an appropriate HTML5 time object containing
 *  a nicely formatted time string in the display area,
 *  and an ISO-formatted string in the datetime object
 *
 * @param datetime $time_obj object to be formatted
 *
 * @return string
 *
 * @author Ken Auberry <kenneth.auberry@pnnl.gov>
 */
function format_cart_display_time_element($time_obj)
{
    $elapsed_time = friendlyElapsedTime($time_obj);
    $formatted_time = $time_obj->format('d M Y g:ia');
    $iso_time = $time_obj->getTimestamp();

    return "<time title='{$formatted_time}' datetime='{$iso_time}'>{$elapsed_time}</time>";
}

/**
 *  For any two given dates, clean the up and format them
 *  as an array of start/end time objects and strings
 *
 * @param string $start_date starting date (YYYY-MM-DD)
 * @param string $end_date   ending date (YYYY-MM-DD)
 *
 * @return array
 *
 * @author Ken Auberry <kenneth.auberry@pnnl.gov>
 */
function canonicalize_date_range($start_date, $end_date)
{
    $start_date = convert_short_date($start_date);
    $end_date   = convert_short_date($end_date, 'end');
    $start_time = strtotime($start_date) ? date_create($start_date)->setTime(0, 0, 0) : date_create('1983-01-01 00:00:00');
    $end_time   = strtotime($end_date) ? date_create($end_date) : new DateTime();
    $end_time->setTime(23, 59, 59);

    if ($end_time < $start_time && !empty($end_time)) {
        $temp_start = $end_time ? clone $end_time : false;
        $end_time   = clone $start_time;
        $start_time = $temp_start;
    }

    return array(
            'start_time_object' => $start_time,
            'end_time_object'   => $end_time,
            'start_time'        => $start_time->format('Y-m-d H:i:s'),
            'end_time'          => $end_time ? $end_time->format('Y-m-d H:i:s') : false,
           );
}//end canonicalize_date_range()

/**
 *  Takes a short date format ('2014', '2015-12')
 *  and expands to the full form of the start or end
 *  of the range, i.e.
 *  ('2014','start') -> '2014-01-01'
 *  ('2015-12','end') -> '2015-12-31'
 *
 * @param string $date_string the short date to expand
 * @param string $type        'endedness' of the result
 *                            to return (start/end)
 *
 * @return string the expanded version of the date
 *
 * @author Ken Auberry <kenneth.auberry@pnnl.gov>
 */
function convert_short_date($date_string, $type = 'start')
{
    if (preg_match('/(\d{4})$/', $date_string, $matches)) {
        $date_string = $type == 'start' ? "{$matches[1]}-01-01" : "{$matches[1]}-12-31";
    } else if (preg_match('/^(\d{4})-(\d{1,2})$/', $date_string, $matches)) {
        $date_string = $type == 'start' ? "{$matches[1]}-{$matches[2]}-01" : "{$matches[1]}-{$matches[2]}-31";
    }

    return $date_string;
}//end _convert_short_date()

/**
 *  Given a starting and ending date, generate all of the
 *  available dates between them, inclusive
 *
 * @param string $start_date starting date (YYYY-MM-DD)
 * @param string $end_date   ending date (YYYY-MM-DD)
 *
 * @return array
 *
 * @author Ken Auberry <kenneth.auberry@pnnl.gov>
 */
function generate_available_dates($start_date, $end_date)
{
    $results           = array();
    $start_date_object = is_object($start_date) ? $start_date : new DateTime($start_date);
    $end_date_object   = is_object($end_date) ? $end_date : new DateTime($end_date);
    $current_date      = clone $start_date_object;
    while ($current_date->getTimestamp() <= $end_date_object->getTimestamp()) {
        $date_key           = $current_date->format('Y-m-d');
        $date_code          = $current_date->format('D M j');
        $results[$date_key] = $date_code;
        $current_date->modify('+1 day');
    }

    return $results;
}//end _generate_available_dates()

 /**
  *  Takes a passed time period specifier (1 week, 1-month, etc)
  *  and parses it into a date range array (with today's date
  *  as the latest date). If a start/end date are specified,
  *  those are used preferentially and are cleaned up and
  *  formatted properly into an array date pair.
  *
  * @param string $time_range       human-parsable time period
  *                                 (1-week, 1 month, 3_days)
  * @param array  $valid_date_range represents the earliest/latest
  *                                 available dates for the
  *                                 group under consideration
  * @param string $start_date       starting date (YYYY-MM-DD)
  * @param string $end_date         ending date (YYYY-MM-DD)
  *
  * @return array
  *
  * @author Ken Auberry <kenneth.auberry@pnnl.gov>
  */
function time_range_to_date_pair($time_range, $valid_date_range = false, $start_date = false, $end_date = false)
{
    // var_dump($valid_date_range);
    $latest_available_date = is_array($valid_date_range) && array_key_exists('latest', $valid_date_range) ? $valid_date_range['latest'] : false;
    if (!$latest_available_date) {
        $latest_available_date = new DateTime();
        $earliest_available_date = new DateTime('1991-01-01');
        $valid_date_range = array(
        'earliest' => $earliest_available_date->format('Y-m-d H:i'),
        'latest' => $latest_available_date->format('Y-m-d H:i'),
        );
    } else {
        $latest_available_date = new DateTime($valid_date_range['latest']);
        $earliest_available_date = new DateTime($valid_date_range['earliest']);
    }
    if (is_string($latest_available_date)) {
        $latest_available_date = new DateTime($latest_available_date);
    }

    //if start_date is valid, use time_range to go forward from that time
    //if end date is valid, use time_range to go back from that time
    $time_modifier = "-";
    if (strtotime($start_date)) {
        $time_modifier = "+";
        $today = new DateTime($start_date);
    } elseif (strtotime($end_date)) {
        $today = $latest_available_date->getTimestamp() < new DateTime($end_date) ? $latest_available_date : new DateTime();
    } else {
        $today = $latest_available_date;
    }


    $today->setTime(23, 59, 59);
    $earlier = clone($today);
    $earlier->modify("{$time_modifier}{$time_range}")->setTime(0, 0, 0);
    if ($earlier->getTimestamp() < $earliest_available_date->getTimestamp()) {
        $earlier = clone $earliest_available_date;
        $start_date = $earlier->format('Y-m-d');
    }
    if ($today->getTimestamp() > $latest_available_date->getTimestamp()) {
        $today = clone $latest_available_date;
        $end_date = $today->format('Y-m-d');
    }
    $times = array(
    'start_date' => $earlier->format('Y-m-d H:i:s'),
    'end_date' => $today->format('Y-m-d H:i:s'),
    'earliest' => $earliest_available_date->format('Y-m-d H:i:s'),
    'latest' => $latest_available_date->format('Y-m-d H:i:s'),
    'start_date_object' => $earlier,
    'end_date_object' => $today,
    'time_range' => $time_range,
    'earliest_available_object' => $earliest_available_date,
    'latest_available_object' => $latest_available_date,
    'message' => "<p>Using ".$today->format('Y-m-d')." as the new origin time</p>"
    );
    return $times;
}

/**
 *  Convert day-level information about file counts/volumes
 *  into a format that is readable by the HighCharts JS
 *  Charting module
 *
 * @param array  $day_graph_info day-level information to parse
 * @param string $start_date     starting date (YYYY-MM-DD)
 * @param string $end_date       ending date (YYYY-MM-DD)
 *
 * @return array
 *
 * @author Ken Auberry <kenneth.auberry@pnnl.gov>
 */
function day_graph_to_series($day_graph_info, $start_date = false, $end_date = false)
{
    $keys = array_keys($day_graph_info['by_date']);
    $fd = array_shift($keys);
    $fd_object = $start_date != false ? new DateTime($start_date) : new DateTime($fd);
    $ld = sizeof($keys) > 0 ? array_pop($keys) : clone $fd_object;
    $ld_object = $end_date != false ? new DateTime($end_date) : new DateTime($ld);
    // $ld_object = new DateTime($ld);

    $current_object = clone $fd_object;

    $results = array(
    'available_dates' => array(),
    'file_count' => array(),
    'file_volume' => array(),
    'transaction_count' => array()
    );
    while ($current_object->getTimestamp() <= $ld_object->getTimestamp()) {
        $date_key = $current_object->format('Y-m-d');
        $results['available_dates'][$date_key] = $current_object->format('D M j');
        if (array_key_exists($date_key, $day_graph_info['by_date'])) {
            $results['file_count'][$date_key] = $day_graph_info['by_date'][$date_key]['file_count'];
            $results['file_volume'][$date_key] = floatval($day_graph_info['by_date'][$date_key]['file_size']);
            $results['transaction_count'][$date_key] = $day_graph_info['by_date'][$date_key]['upload_count'];
            $results['file_volume_array'][$date_key] = array($current_object->getTimestamp() * 1000, floatval($day_graph_info['by_date'][$date_key]['file_size']));
            $results['transaction_count_array'][$date_key] = array($current_object->getTimestamp() * 1000, $day_graph_info['by_date'][$date_key]['upload_count']);
        } else {
            $results['file_count'][$date_key] = 0;
            $results['file_volume'][$date_key] = 0;
            $results['transaction_count'][$date_key] = 0;
            $results['file_volume_array'][$date_key] = array($current_object->getTimestamp() * 1000, 0.0);
            $results['transaction_count_array'][$date_key] = array($current_object->getTimestamp() * 1000, 0.0);
        }
        $current_object->modify("+1 day");
    }
    return $results;
}

/**
 *  Takes a passed time period specifier (1 week, 1-month, etc)
 *  and parses it into a date range array (with today's date
 *  as the latest date). If a start/end date are specified,
 *  those are used preferentially and are cleaned up and
 *  formatted properly into an array date pair.
 *
 * @param string $time_range       human-parsable time period
 *                                 (1-week, 1 month, 3_days)
 * @param string $start_date       starting date (YYYY-MM-DD)
 * @param string $end_date         ending date (YYYY-MM-DD)
 * @param array  $valid_date_range represents the earliest/latest
 *                                 available dates for the
 *                                 group under consideration
 *
 * @return array
 *
 * @author Ken Auberry <kenneth.auberry@pnnl.gov>
 */
function fix_time_range($time_range, $start_date, $end_date, $valid_date_range = false)
{
    if (!empty($start_date) && !empty($end_date)) {
        $times = canonicalize_date_range($start_date, $end_date);

        return $times;
    }

    $time_range = str_replace(array('-', '_', '+'), ' ', $time_range);
    if (!strtotime($time_range)) {
        if ($time_range == 'custom' && strtotime($start_date) && strtotime($end_date)) {
            // custom date_range, just leave them. Canonicalize will fix them
        } else {
            // looks like the time range is borked, pick the default
            $time_range = '1 week';
            $times      = time_range_to_date_pair($time_range, $valid_date_range);
            extract($times);
        }
    } else {
        $times = time_range_to_date_pair($time_range, $valid_date_range);
        extract($times);
    }

    $times = canonicalize_date_range($start_date, $end_date);

    return $times;
}//end fix_time_range()

/**
 * Verbose Beautified Date Range
 *
 * @access public
 * @param mixed $start_date
 * @param mixed $end_date
 * @return $date_range (beautified date range)
 * @license WTFPL
 *
 * @author Jon Brown <jb@9seeds.com---->
 * @since 1.0
 */

function verbose_date_range($start_date = '', $end_date = '')
{

    $date_range = '';

    // If only one date, or dates are the same set to FULL verbose date
    if (empty($start_date) || empty($end_date) || $start_date->format('FjY') == $end_date->format('FjY')) { // FjY == accounts for same day, different time
        $start_date_pretty = $start_date->format('F jS, Y');
        $end_date_pretty = $end_date->format('F jS, Y');
    } else {
         // Setup basic dates
        $start_date_pretty = $start_date->format('F j');
        $end_date_pretty = $end_date->format('jS, Y');

        // If years differ add suffix and year to start_date
        if ($start_date->format('Y') != $end_date->format('Y')) {
            $start_date_pretty .= $start_date->format('S, Y');
        }

        // If months differ add suffix and year to end_date
        if ($start_date->format('F') != $end_date->format('F')) {
            $end_date_pretty = $end_date->format('F ').$end_date_pretty;
        }
    }

    // build date_range return string
    if (! empty($start_date)) {
          $date_range .= $start_date_pretty;
    }

    // check if there is an end date and append if not identical
    if (! empty($end_date)) {
        if ($end_date_pretty != $start_date_pretty) {
              $date_range .= ' &ndash; ' . $end_date_pretty;
        }
    }
    return $date_range;
}
