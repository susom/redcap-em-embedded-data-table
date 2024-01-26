<?php
namespace Stanford\EDT;

require_once ($module->getModulePath() . "classes/CustomTableInterface.php");

use REDCap;
use \Plugin;

class motif_health_changes implements \Stanford\EDT\CustomTableInterface
{
    private $pid;
    private $record_id;
    private $configs = array();
    private $data_dict = array();
    private $return_data = array();
    function __construct($pid, $record_id)
    {
        // Include the configuration parameters
    	include "motif_config.php";

    	if (!empty($diary_configs)) {
            $this->configs = $diary_configs;
            $this->pid = $pid;
            $this->record_id = $record_id;
            
            list($survey_data, $date_map) = $this->loadSurveyData();

        	$this->findHealthChanges($survey_data, $date_map);
        }
    }

	public function getTitle() {
		return "Health Changes";
	}
	
	public function getHeader() {
		return array('Link', 'Day Number', 'Date', 'Health Change Type', 'Describe Health Change', 'Date of Last Dose', 'Time of Last Dose');
	}
	
	public function getData() {
		return $this->return_data;
	}

    private function loadSurveyData() {

        $survey_data = REDCap::getData($this->pid, 'array', null, null, array($this->configs["DIARY_EVENT"]));

        $date_map = $this->makeDateMap($survey_data);

        return array($survey_data, $date_map);
    }

    private function makeDateMap($project_data) {

        $map = array();
        foreach ($project_data[$this->record_id] as $diary_event => $diary_info) {

            if ($diary_event == "repeat_instances") {
                foreach ($diary_info as $event => $event_info) {
                    foreach($event_info[""] as $instance => $instance_info) {
                        $map[$instance_info["rsp_survey_date"]] = $instance;
                    }
                }
            }
        }

        krsort($map);

        return $map;
    }

    private function findHealthChanges($survey_data, $map_data) {

        $this->data_dict = REDCap::getDataDictionary($this->pid, 'array');
        $health_change_type_list = $this->getDataOptions($this->data_dict["health_change_type"]);

        //change request: Health Change -> Link, Day Number, Date, Health Change Type, Describe Health Change, Date of Last Dose, Time of Last Dose.
        $select = array('rsp_survey_day_number',$this->configs["SURVEY_DATE"],'health_change_type');
        $health_change_fields = array('health_change_ill','health_change_inj','health_change_ai','health_change_ndras',
            'health_change_hcpvisit','health_change_newdiag','health_change_resolve','health_change_o');

        $all_health_changes = array();
        foreach ($survey_data[$this->record_id]["repeat_instances"][$this->configs["DIARY_EVENT"]][""] as $instance_id => $current) {

            $table_data = array();
            //Skip the record if health_change is not set to YES
            if (($current['health_change']) != 1) {
                continue;
            }

            // add a custom link field
            $survey_link = APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $this->pid . "&page=" . $this->configs["DIARY_FORM"] . "&id=" . $this->record_id;
            $survey_link .= "&event_id=" . $this->configs["DIARY_EVENT"] . "&instance=" . $instance_id;

            //$table_data[$this->record_id]['link'] = "<a href='" . $survey_link . "'>" . $this->record_id . "</a>";
            $table_data['link'] = "<a href='" . $survey_link . "'>" . $this->record_id . "</a>";

            // iterate over the rest of the selected fields
            foreach ($select as $key) {
                if ($key == "health_change_type") {
                    $table_data[$key] = $this->getCheckboxLabel($health_change_type_list, $current['health_change_type']);
                } else {
                    $table_data[$key] = $current[$key];
                }
            }

            //ADD health change fields
            $describe_health_field = null;
            foreach ($health_change_fields as $delta) {
                $delta_val = $current[$delta];
                if (!empty($delta_val)) {
                    $describe_health_field .= $delta_val."<br>";
                }

            }
            $table_data['describe_health_change'] = $describe_health_field;

            //ADD date & time of last dose
            $health_start_date = $current['rsp_survey_date'];

            $last_dose_date = null;
            $candidate_time = null;

            //look through map_data until find equal or greater date
            foreach($map_data as $date => $candidate) {
                //get dose time
                //$candidate_time = $current[$candidate][$diary_event_id]['diary_dose_time'];
                //$dose_taken = $current[$candidate][$diary_event_id]['dose_taken'];
                $candidate_time = $current['diary_dose_time'];
                $dose_taken = $current['dose_taken'];

                if ($dose_taken != '1') {
                    $candidate_time = '';
                    $last_dose_date = '';
                    continue;
                }

                if ($date <= $health_start_date) {
                    $last_dose_date = $date;
                    break;
                } else {
                    $last_dose_date = '';
                    $candidate_time = '';
                }
            }

            $table_data['last_dose_date'] = $last_dose_date;
            $table_data['last_dose_time'] = $candidate_time;

            $all_health_changes[] = $table_data;
        }

		$this->return_data = $all_health_changes;
        return;
    }

    private function getDataOptions($dd) {

        $options = array();
        if ($dd['field_type'] == 'yesno') {
            $optionList = "1, Yes|0, No";
        } else {
            $optionList = $dd["select_choices_or_calculations"];
        }

        $optionArray = explode('|', $optionList);
        foreach($optionArray as $oneOption) {
            $key_value = explode(',', $oneOption);
            $options[trim($key_value[0])] = trim($key_value[1]);
        }

        return $options;
    }

    private function getCheckboxLabel($optionList, $value) {

        $checkbox_labels = '';
        foreach ($value as $key => $set) {
            if ($set == "1") {
                if (empty($checkbox_labels)) {
                    $checkbox_labels .= $optionList[$key];
                } else {
                    $checkbox_labels .= '<br>' . $optionList[$key];
                }
            }
        }

        return $checkbox_labels;
    }

}