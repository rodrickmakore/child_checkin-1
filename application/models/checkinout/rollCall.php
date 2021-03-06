<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 *
 * @author Kedyr <deniskedyr@gmail.com>
 */
class Rollcall extends CI_Model {

    //todo amplify error reporting
    public function __construct() {
        parent::__construct();
    }

    function checkin($details) {
        $this->db->insert(TBL_CHECKINOUT, $details);
        return $this->db->insert_id();
    }

    function update($details, $checkinId) {
        return $this->db->update(TBL_CHECKINOUT, $details, array(COL_CHECKIN_ID => $checkinId));
    }

    /**
     * searches for all children who have a similar checkinUnderId and updates their handler data.
     * This assumes kids are checkin if first before a handler.
     * @param type $details
     * @param type $chekinUnderId
     */
    function updateChildrenCheckinWIthHandlerDetails($details, $chekinUnderId,$sibling_count) {
        $this->db->update(TBL_CHECKINOUT,array(COL_SIBLING_COUNT=>$sibling_count),array(COL_CHECKIN_ID=>$chekinUnderId));
        
        $this->db->where(COL_CHECKIN_ID, $chekinUnderId);
        $this->db->or_where(COL_CHECK_IN_UnderId, $chekinUnderId);
        return $this->db->update(TBL_CHECKINOUT, $details);
    }

    function checkIfChildCheckedIn($childId) {
        return $this->Dbwrapper->minedb_check_if_record_exists(TBL_CHECKINOUT, array(COL_CHILD_ID => $childId, COL_STATUS => $this->config->item('checkin_status_in')));
    }

    function get($checkinId) {
        $query = $this->db->get_where(TBL_CHECKINOUT, array(COL_CHECKIN_ID => $checkinId));
        return $this->Dbwrapper->summarize_get_and_select($query, TRUE);
    }

    function getSingleRollCallAttribute($checkin_id, $column) {
        $query = $this->db->get_where(TBL_CHECKINOUT, array(COL_CHECKIN_ID => $checkin_id));
        return $this->Dbwrapper->summarize_get_and_select($query, TRUE, $column);
    }

    function getCardNumbersForCheckedInChildren() {
        $this->db->select(COL_CHECK_IN_UnderId . ' , ' . COL_CHECK_IN_NUMBER)->from(TBL_CHECKINOUT);
        $this->db->where(COL_STATUS, $this->config->item('checkin_status_in'))->group_by(COL_CHECK_IN_NUMBER);
        $this->db->order_by(COL_CHECK_IN_NUMBER,'asc');
        $query = $this->db->get();
        $results = $this->Dbwrapper->summarize_get_and_select($query);
        $cards = array();
        foreach ($results as $child) {
            $cards[$child[COL_CHECK_IN_UnderId]] = $child[COL_CHECK_IN_NUMBER];
        }
        return $cards;
    }

    function getCheckedInSiblingsWithCheckinId($checkinId) {
        $query = $this->db->select(TBL_CHILDREN . '.*')->from(TBL_CHILDREN)->join(TBL_CHECKINOUT, TBL_CHECKINOUT . '.' . COL_CHILD_ID . ' = ' . TBL_CHILDREN . '.' . COL_CHILD_ID)->where(TBL_CHECKINOUT . '.' . COL_CHECK_IN_UnderId, $checkinId)->get();
        return $this->Dbwrapper->summarize_get_and_select($query);
    }

    function getCheckedInHandlerWithCheckinId($checkinId) {
        $query = $this->db->select(TBL_HANDLERS . '.*')->from(TBL_HANDLERS)->join(TBL_CHECKINOUT, TBL_CHECKINOUT . '.' . COL_HANDLER_ID . ' = ' . TBL_HANDLERS . '.' . COL_HANDLER_ID)->where(TBL_CHECKINOUT . '.' . COL_CHECK_IN_UnderId, $checkinId)->group_by(COL_HANDLER_ID)->get();
        return $this->Dbwrapper->summarize_get_and_select($query);
    }

    function getUnregisteredCheckedInHandlerWithCheckinId($checkin_id) {
        $query = $this->db->select(COL_HANDLER_NAME)->from(TBL_CHECKINOUT)->where(COL_CHECK_IN_UnderId, $checkin_id)->get();
        return $this->Dbwrapper->summarize_get_and_select($query, TRUE, COL_HANDLER_NAME);
    }

    function getNoChildrenCheckedIn($checkinId) {
        return $this->db->from(TBL_CHECKINOUT)->where(array(COL_CHECK_IN_UnderId => $checkinId))->count_all_results();
    }

    function getSiblingCountCheckedIn($checkIn_id) {
        $query = $this->db->select(COL_SIBLING_COUNT)->from(TBL_CHECKINOUT)->where(COL_CHECKIN_ID, $checkIn_id)->get();
        return $this->Dbwrapper->summarize_get_and_select($query, TRUE, COL_SIBLING_COUNT);
    }
    
    function chekoutSibling($childId,$checkin_id){
        return $this->db->update(TBL_CHECKINOUT,array(COL_STATUS=>$this->config->item('checkin_status_out')),array(COL_CHECK_IN_UnderId=>$checkin_id,COL_CHILD_ID=>$childId));
    }
    
    function completeCheckOut($checkinId){
        return $this->db->update(TBL_CHECKINOUT,array(COL_STATUS=>$this->config->item('checkin_status_out'),COL_TIME_OUT=>  getCurrentTime()),array(COL_CHECK_IN_UnderId=>$checkinId));
    }
    
    function checkIfCheckinNumberGivenOut($card_num){
        return $this->Dbwrapper->minedb_check_if_record_exists(TBL_CHECKINOUT,array(COL_STATUS=>$this->config->item('checkin_status_in'),COL_CHECK_IN_NUMBER=>$card_num));
    }
    
    function forceCheckout(){
        $this->db->query("UPDATE ". TBL_CHECKINOUT." SET ".COL_STATUS." = '".$this->config->item('checkin_status_force_out')."' WHERE datediff('".getCurrentTime()."' , ". COL_TIME_IN ." ) > ".$this->config->item('checkin_force_checkout_timeout'). " AND (".COL_STATUS." <> '".$this->config->item('checkin_status_in')."' OR ".COL_STATUS." <> '".$this->config->item('checkin_status_incomplete')."')" );
    }
}