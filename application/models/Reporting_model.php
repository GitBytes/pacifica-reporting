<?php

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
/*                                                                             */
/*     Reporting Model                                                         */
/*                                                                             */
/*             functionality for summarizing upload and activity data.         */
/*                                                                             */
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
class Reporting_model extends CI_Model
{
    private $debug;
    public function __construct()
    {
        parent::__construct();
        define('TRANS_TABLE', 'transactions');
        define('FILES_TABLE', 'files');
        $this->load->database();
        $this->load->helper(array('item'));
        $this->debug = $this->config->item('debug_enabled');
    }

    public function detailed_transaction_list($transaction_list)
    {
        // $eus_info = $this->get_info_for_transactions(array_combine($transaction_list, $transaction_list));
        // $item_list = $group_info['item_list'];
        // $group_type = $group_info['group_type'];
        // $time_basis = str_replace("_time","_date",$group_info['time_basis']);
        // $start_date_object = is_object($start_date) ? $start_date : new DateTime($start_date);
        // $end_date_object = is_object($end_date) ? $end_date : new DateTime($end_date);
        $eus_select_array = array(
            'i.transaction','i.group_type as category',
            'MIN(g.name) as group_name',
            'MIN(g.type) as group_type'
        );
        $this->db->select($eus_select_array)->from(ITEM_CACHE." i");
        $this->db->join('groups g','g.group_id = i.group_id');
        $this->db->where_in('i.transaction',$transaction_list);
        $this->db->group_by('i.transaction, i.group_type')->order_by('i.group_type,i.transaction');
        $eus_query = $this->db->get();

        $eus_lookup = array();
        if($eus_query && $eus_query->num_rows() > 0){
            foreach($eus_query->result() as $row){
                if($row->category == 'instrument'){
                    $inst_id = false;
                    if($row->group_type == 'omics.dms.instrument_id'){
                        $id = intval($row->group_name);
                    }elseif(stristr($row->group_type,'instrument.')){
                        $id = intval(str_ireplace('instrument.','',$row->group_type));
                    }
                }elseif($row->group_type == 'proposal'){
                    $id = $row->group_name;
                }
                $eus_lookup[$row->transaction][$row->category] = $id;
            }
        }

        $select_array = array(
            'i.transaction as upload_id',
            'max(i.submit_date) as upload_date',
            'min(i.modified_date) as file_date_start',
            'max(i.modified_date) as file_date_end',
            'min(i.submitter) as uploaded_by_id',
            'sum(i.size_in_bytes) as bundle_size',
            'count(i.item_id) as file_count'
        );

        // $where_array = array(
        //     "{$time_basis} >=" => $start_date_object->format('Y-m-d'),
        //     "{$time_basis} <=" => $end_date_object->format('Y-m-d')
        // );
        $this->db->select($select_array)->group_by('i.transaction');
        $this->db->from(ITEM_CACHE." i")->where_in('i.transaction',$transaction_list);
        $query = $this->db->get();

        $results = array();
        if($query && $query->num_rows() > 0){
            foreach($query->result_array() as $row){
                $row['proposal_id'] = $eus_lookup[$row['upload_id']]['proposal'];
                $row['instrument_id'] = $eus_lookup[$row['upload_id']]['instrument'];
                $results[$row['upload_id']] = $row;
            }
        }

        // $this->db->from('files f')->join('transactions t', 'f.transaction = t.transaction');
        // $this->db->where_in('f.transaction', $transaction_list);
        // $this->db->group_by('f.transaction')->order_by('f.transaction desc');

        // $file_info = array();
        // if ($query && $query->num_rows() > 0) {
        //     foreach ($query->result_array() as $row) {
        //         $file_info[$row['transaction']] = $row;
        //     }
        // }

        // foreach ($file_info as $transaction_id => $file_entry) {
        //     $eus_entry = $eus_info[$transaction_id];
        //     foreach ($eus_entry as $key => $value) {
        //         $file_entry[$key] = $value;
        //     }
        //     $results[$transaction_id] = $file_entry;
        // }

        return $results;
    }


    // private function get_transactions_for_user_list($eus_user_id_list, $start_date, $end_date, $unfiltered = false)
    // {
    //     extract($this->canonicalize_date_range($start_date, $end_date));
    //     $transactions = array();
    //     $where_clause = array('stime >=' => $start_time_object->format('Y-m-d H:i:s'));
    //     if ($end_time) {
    //         $where_clause['stime <'] = $end_time_object->format('Y-m-d H:i:s');
    //     }
    //     $this->db->select(array('t.transaction', 't.stime as submit_time', 'ing.person_id'))->where($where_clause);
    //     $this->db->from('transactions as t')->join('ingest_state as ing', 't.transaction = ing.trans_id');
    //     $this->db->where('ing.message', 'completed')->where_in('ing.person_id', $eus_user_id_list);
    //     $this->db->order_by('t.transaction desc');
    //     $transaction_query = $this->db->get();
    //     if ($transaction_query && $transaction_query->num_rows() > 0) {
    //         foreach ($transaction_query->result() as $row) {
    //             $stime = date_create($row->submit_time);
    //             $transactions[$row->transaction] = array(
    //                 'submit_time' => $stime->format('Y-m-d H:i:s'),
    //             );
    //         }
    //     }
    //
    //     return $transactions;
    // }

    private function get_files_for_user_list($eus_user_id_list, $start_date, $end_date, $unfiltered = false, $time_basis)
    {
        extract($this->canonicalize_date_range($start_date, $end_date));
        switch ($time_basis) {
      case 'create_time':
        $time_field = 'f.ctime';
        break;
      case 'modified_time':
        $time_field = 'f.mtime';
        break;
      case 'submit_time':
        $time_field = 't.stime';
        break;
      default:
        $time_field = 't.stime';
    }
        $files = array();
        if ($end_time) {
            $this->db->where("{$time_field} < ", $end_time_object->format('Y-m-d H:i:s'));
        }
        $this->db->select(array(
            'f.item_id',
            't.transaction',
            'date_trunc(\'minute\',t.stime) as submit_time',
            'date_trunc(\'minute\',f.ctime) as create_time',
            'date_trunc(\'minute\',f.mtime) as modified_time',
            'size as size_bytes',
        ));
        $this->db->from('transactions as t');
        $this->db->join('ingest_state as ing', 't.transaction = ing.trans_id');
        $this->db->join('files as f', 'f.transaction = t.transaction');
        $this->db->where('ing.message', 'completed')->where_in('ing.person_id', $eus_user_id_list);
        $this->db->order_by('t.transaction desc');
        $query = $this->db->get();
        if ($query && $query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $stime = date_create($row->submit_time);
                $ctime = date_create($row->create_time);
                $mtime = date_create($row->modified_time);
                $files[$row->item_id] = array(
                    'submit_time' => $stime->format('Y-m-d H:i:s'),
                    'create_time' => $ctime->format('Y-m-d H:i:s'),
                    'modified_time' => $mtime->format('Y-m-d H:i:s'),
                    'transaction' => $row->transaction,
                    'size_bytes' => $row->size_bytes,
                );
            }
        }

        return $files;
    }
}
