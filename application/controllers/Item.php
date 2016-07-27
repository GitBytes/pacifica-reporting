<?php

defined('BASEPATH') or exit('No direct script access allowed');
require_once 'Baseline_controller.php';

class Item extends Baseline_controller
{
    public $last_update_time;
    public $accepted_object_types;
    public $accepted_time_basis_types;
    public $local_resources_folder;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Reporting_model', 'rep');
        $this->load->library('EUS', '', 'eus');
        $this->load->helper(array('network', 'file_info', 'inflector', 'time', 'item', 'search_term', 'cookie'));
        $this->last_update_time = get_last_update(APPPATH);
        $this->accepted_object_types = array('instrument', 'user', 'proposal');
        $this->accepted_time_basis_types = array('submit_time', 'create_time', 'modified_time');
        $this->local_resources_folder = $this->config->item('local_resources_folder');
    }

    public function view($object_type, $group_id, $time_range = '1-month', $start_date = false, $end_date = false)
    {
        $object_type = singular($object_type);
        $accepted_object_types = array('instrument', 'proposal', 'user');
        if (!in_array($object_type, $accepted_object_types)) {
            redirect('reporting/view/instrument/{$time_range}');
        }
        $this->page_data['page_header'] = 'MyEMSL Uploads per '.ucwords($object_type);
        $this->page_data['my_object_type'] = $object_type;
        $this->page_data['css_uris'] = array(
      '/resources/stylesheets/status_style.css',
      '/resources/scripts/select2/select2.css',
      '/resources/scripts/bootstrap/css/bootstrap.css',
      '/resources/scripts/bootstrap-daterangepicker/daterangepicker.css',
      APPPATH.'resources/stylesheets/reporting.css',
    );
        $this->page_data['script_uris'] = array(
      '/resources/scripts/spinner/spin.min.js',
      '/resources/scripts/spinner/jquery.spin.js',
      '/resources/scripts/moment.min.js',
      // "/resources/scripts/bootstrap/js/bootstrap.min.js",
      '/resources/scripts/bootstrap-daterangepicker/daterangepicker.js',
      '/resources/scripts/jquery-typewatch/jquery.typewatch.js',
      '/resources/scripts/highcharts/js/highcharts.js',
      APPPATH.'resources/scripts/reporting.js',
    );
        $this->page_data['js'] = "var object_type = '{$object_type}'; var time_range = '{$time_range}'";
        $time_range = str_replace(array('-', '_', '+'), ' ', $time_range);

        $my_object_list = $this->rep->get_selected_objects($this->user_id, $object_type, $group_id);
        if (empty($my_object_list)) {
            $examples = add_objects_instructions($object_type);
            $this->page_data['examples'] = $examples;
            $this->page_data['js'] .= "
            $(function(){
              $('#object_search_box').focus();
            });
            ";
            $this->page_data['content_view'] = 'object_types/select_some_objects_insert.html';
        } else {
            $this->page_data['my_objects'] = '';

            $object_list = array_map('strval', array_keys($my_object_list[$object_type]));
            if (!empty($default_object_id) && in_array($default_object_id, $object_list)) {
                $object_list = array(strval($default_object_id));
            }

      // $transaction_info = array();
      $object_info = $this->eus->get_object_info($object_list, $object_type);
      // $transaction_retrieval_func = "summarize_uploads_by_{$object_type}";
      foreach ($object_list as $object_id) {
          $valid_date_range = $this->rep->earliest_latest_data($object_type, $object_id);
          $my_times = $this->fix_time_range($time_range, $start_date, $end_date, $valid_date_range);
          $latest_available_date = new DateTime($valid_date_range['latest']);
          $earliest_available_date = new DateTime($valid_date_range['earliest']);

          $valid_range = array(
          'earliest' => $earliest_available_date->format('Y-m-d H:i:s'),
          'latest' => $latest_available_date->format('Y-m-d H:i:s'),
          'earliest_available_object' => $earliest_available_date,
          'latest_available_object' => $latest_available_date,
        );
          if ($my_times['start_time_object']->getTimestamp() < $valid_range['earliest_available_object']->getTimestamp()) {
              $my_times['start_time_object'] = clone $valid_range['earliest_available_object'];
          }
          if ($my_times['end_time_object']->getTimestamp() > $valid_range['latest_available_object']->getTimestamp()) {
              $my_times['end_time_object'] = clone $valid_range['latest_available_object'];
          }
          $my_times = array_merge($my_times, $valid_range);
        // var_dump(array_merge($times,$valid_range));

        // $transaction_info[$object_id] = $this->rep->$transaction_retrieval_func($object_id,'2015-10-01','2015-12-01');
        $this->page_data['placeholder_info'][$object_id] = array(
          'object_type' => $object_type,
          'object_id' => $object_id,
          'time_range' => $time_range,
          'times' => $my_times,
        );
        // var_dump($my_times);
      }
      // var_dump($object_info);
      $this->page_data['my_objects'] = $object_info;
            $this->page_data['content_view'] = 'object_types/object.html';
        }
    // $this->page_data['default_time_range'] = $times;
    // $this->page_data['transaction_info'] = $transaction_info;

    $this->load->view('reporting_view.html', $this->page_data);
    }

    public function get_reporting_info($object_type, $object_id, $time_range = '1-week', $start_date = false, $end_date = false, $with_timeline = true)
    {
        $this->get_reporting_info_base($object_type, $object_id, $time_range, $start_date, $end_date, true);
    }

    public function get_reporting_info_no_timeline($object_type, $object_id, $time_range = '1-week', $start_date = false, $end_date = false)
    {
        $this->get_reporting_info_base($object_type, $object_id, $time_range, $start_date, $end_date, false);
    }

  // Call to retrieve fill-in HTML for reporting block entries
  private function get_reporting_info_base($object_type, $object_id, $time_range = '1-week', $start_date = false, $end_date = false, $with_timeline = true, $full_object = false)
  {
      $this->page_data['object_id'] = $object_id;
      $this->page_data["{$object_type}_id"] = $object_id;
      $this->page_data['object_type'] = $object_type;
      $available_time_range = $this->rep->earliest_latest_data($object_type, $object_id);
      $latest_data = is_array($available_time_range) && array_key_exists('latest', $available_time_range) ? $available_time_range['latest'] : false;
      if (!$latest_data) {
          //no data available for this object
      $this->page_data['results_message'] = 'No Data Available for this '.ucwords($object_type);
          $this->load->view('object_types/object_body_insert.html', $this->page_data);

          return;
      }
      $latest_data_object = new DateTime($latest_data);
      $time_range = str_replace(array('-', '_', '+'), ' ', $time_range);
      $this->page_data['results_message'] = '&nbsp;';
      $valid_tr = strtotime($time_range);
      $valid_st = strtotime($start_date);
      $valid_et = strtotime($end_date);
      if (!$valid_tr) {
          if ($time_range == 'custom' && $valid_st && $valid_et) {
              //custom date_range, just leave them. Canonicalize will fix them
        $earliest_available_object = new DateTime($available_time_range['earliest']);
              $latest_available_object = new DateTime($available_time_range['latest']);
              $start_date_object = new DateTime($start_date);
              $end_date_object = new DateTime($end_date);
              if ($start_date_object->getTimestamp() < $earliest_available_object->getTimestamp()) {
                  $start_date_object = clone $earliest_available_object;
                  $start_date = $start_date_object->format('Y-m-d');
              }
              if ($end_date_object->getTimestamp() > $latest_available_object->getTimestamp()) {
                  $end_date_object = clone $latest_available_object;
                  $end_date = $end_date_object->format('Y-m-d');
              }
              $times = array(
          'start_date' => $start_date_object->format('Y-m-d H:i:s'),
          'end_date' => $end_date_object->format('Y-m-d H:i:s'),
          'earliest' => $earliest_available_object->format('Y-m-d H:i:s'),
          'latest' => $latest_available_object->format('Y-m-d H:i:s'),
          'start_date_object' => $start_date_object,
          'end_date_object' => $end_date_object,
          'time_range' => $time_range,
          'earliest_available_object' => $earliest_available_object,
          'latest_available_object' => $latest_available_object,
          'message' => '<p>Using '.$end_date_object->format('Y-m-d').' as the new origin time</p>',
        );
          } else {
              //looks like the time range is borked, pick the default
        $time_range = '1 week';
              $times = time_range_to_date_pair($time_range, $available_time_range);
          }
      } else { //time_range is apparently valid
      if (($valid_st || $valid_et) && !($valid_st && $valid_et)) {
          //looks like we want an offset time either start or finish
        $times = time_range_to_date_pair($time_range, $available_time_range, $start_date, $end_date);
      } else {
          $times = time_range_to_date_pair($time_range, $available_time_range);
      }
      }
      extract($times);

      $transaction_retrieval_func = "summarize_uploads_by_{$object_type}";
      $transaction_info = array();
      $transaction_info = $this->rep->$transaction_retrieval_func($object_id, $start_date, $end_date, $with_timeline);
    // echo "<pre>";
    // var_dump($transaction_info);
    // echo "</pre>";
    // exit;
    $this->page_data['transaction_info'] = $transaction_info;
      $this->page_data['times'] = $times;
      $this->page_data['options_list'] = $options_list;
      $this->page_data['include_timeline'] = $with_timeline;
    // echo "<pre>";
    // var_dump($this->page_data);
    // echo "</pre>";
    // exit;

    if ($with_timeline) {
        $this->load->view('object_types/object_body_insert.html', $this->page_data);
    } else {
        $this->load->view('object_types/object_pie_scripts_insert.html', $this->page_data);
    }
  }

    public function get_timeline_data($object_type, $object_id, $start_date, $end_date)
    {
        if (!in_array($object_type, $this->accepted_object_types)) {
            //return an error
      return false;
        }

        $retrieval_func = "summarize_uploads_by_{$object_type}";
        $results = $this->rep->$retrieval_func($object_id, $start_date, $end_date, true);
        $downselect = $results['day_graph']['by_date'];
        $return_array = array(
      'file_volumes' => array_values($downselect['file_volume_array']),
      'transaction_counts' => array_values($downselect['transaction_count_array']),
    );
        send_json_array($return_array);
    }

    public function get_object_lookup($object_type, $filter = '')
    {
        $my_objects = $this->rep->get_selected_objects($this->user_id);
        if (!array_key_exists($object_type, $my_objects)) {
            $my_objects[$object_type] = array();
        }
        $filter = parse_search_term($filter);
        $results = $this->eus->get_object_list($object_type, $filter, $my_objects[$object_type]);
        $this->page_data['results'] = $results;
        $this->page_data['object_type'] = $object_type;
        $this->page_data['filter_text'] = $filter;
        $this->page_data['my_objects'] = $my_objects[$object_type];
        $this->page_data['js'] = '$(function(){ setup_search_checkboxes(); })';
        if (!empty($results)) {
            $this->load->view("object_types/search_results/{$object_type}_results.html", $this->page_data);
        } else {
            $filter_string = implode("' '", $filter);
            echo "<div class='info_message' style='margin-bottom:1.5em;'>No Results Returned for '{$filter_string}'</div>";
        }
    }
}