<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once('Baseline_controller.php');

class Reporting extends Baseline_controller {
  function __construct() {
    parent::__construct();
    $this->load->model('Reporting_model','rep');
    $this->load->helper(array('network'));
  }



  public function index(){
    $this->rep->summarize_uploads_by_instrument(34218, '2015-12-01');
  }

  public function get_uploads_for_instrument($instrument_id,$start_date,$end_date = false){
    $results = $this->rep->summarize_uploads_by_instrument($instrument_id,$start_date,$end_date);
    $results_size = sizeof($results);
    $pluralizer = $results_size != 1 ? "s" : "";
    $status_message = '{$results_size} transaction{$pluralizer} returned';
    
    //send_json_array($results);
  }

}

?>
