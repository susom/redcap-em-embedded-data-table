<?php
namespace Stanford\EDT;

require_once ($module->getModulePath() . "classes/CustomTableInterface.php");

use \REDCap;

class motif_clinical_times implements \Stanford\EDT\CustomTableInterface
{
    private $pid;
    private $record_id;
    private $configs = array();
    private $data_dict = array();
    private $fields = array();
    private $return_data = array();

    function __construct($pid, $record_id)
    {
        // Include the configuration parameters
    	include "motif_config.php";

    	if (!empty($diary_configs)) {
        	// This initialization routine needs to initialize all project specific parameters necessary to
        	// retrieve this data
        	$this->configs = $diary_configs;
        	$this->pid = $pid;
        	$this->record_id = $record_id;
        	$this->data_dict = REDCap::getDataDictionary($this->pid, 'array');
        	$this->fields = array('visit_date','visit_type','clinic_dose_amt','dose_newhome_total');

        	$main_data = $this->loadData();
        	$this->retrieveClinicVisits($main_data);
        }
    }

	public function getTitle() {
		return "Clinic Visits";
	}
	
	public function getHeader() {
		return array('Link', 'Event Type', 'Visit Date', 'Dosing Days since last Visit', 'Phase', 'Dose Given in Clinic', 'New Home Dose');
	}
	
	public function getData() {
		return $this->return_data;
	}

    private function loadData() {

        $data = REDCap::getData($this->pid, 'array', $this->record_id, $this->fields);

        return $data;
    }


    private function retrieveClinicVisits($main_data) {

        $options_list['visit_type'] = $this->getDataOptions($this->data_dict['visit_type']);

        /**
         * Array (
         * [test04] => Array
         * (
        [784] => Array
        (
        [visit_date] => 2017-06-22
        [visit_type] =>
        [clinic_dose_amt] =>
        [dose_newhome_total] =>
        )

        [785] => Array ...
        )

        [repeat_instances] => Array
        (
        [793] => Array
        (
        [] => Array
        (
        [1] => Array
        (
        [visit_date] => 2017-06-22
        [visit_type] => 4
        [clinic_dose_amt] =>
        [dose_newhome_total] => 45
        )

        [2] => Array ...
        (
        ...
         */

        $events = $this->getEvents();
        $display_data = array();

        foreach ($main_data[$this->record_id] as $event_id => $clinic_dose_data) {

            if ($event_id == "repeat_instances") {

                // This is a repeating form
                foreach ($clinic_dose_data as $repeat_event_id => $form) {
                    foreach ($form[""] as $instance_id => $instance_data) {

                        // The label will be the event name and instance number
                        $event_display = $events[$repeat_event_id] ."_". $instance_id;
                        $survey_link = APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $this->pid . "&page=" . $this->configs["CV_FORM"] . "&id=" . $this->record_id .
                            "&event_id=" . $repeat_event_id . "&instance=".$instance_id;

                        $table_data['link'] = "<a href='" . $survey_link . "'>" . $this->record_id . "</a>";
                        $table_data['event_type'] = $event_display;

                        // add the rest of the fields
                        foreach ($this->fields as $field_name) {
                            if (empty($options_list[$field_name])) {
                                $table_data[$field_name] = $instance_data[$field_name];
                                if ($field_name == 'visit_date') {
                                    $table_data['num_days'] = '0';
                                }
                            } else {
                                $table_data[$field_name] = $this->getRadioLabel($options_list[$field_name], $instance_data[$field_name]);
                            }
                        }

                        if (!empty($table_data["visit_date"])) {
                            $display_data[strtotime($table_data["visit_date"])] = $table_data;
                        }
                    }
                }
            } else {

                // LINK: add a custom link field: picked Clinic Form to redirect to, not dosing_form
                $survey_link = APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $this->pid . "&page=" . $this->configs["CV_FORM"] . "&id=" . $this->record_id. "&event_id=" . $event_id;

                // EVENT: Using REDCAp::getEventNames
                $event_name = $events[$event_id];

                $table_data['link'] = '<a href="' . $survey_link . '">' . $this->record_id . "</a>";
                $table_data['event_type'] = $event_name;

                // add the rest of the fields
                foreach ($this->fields as $field_name) {
                    if (empty($options_list[$field_name])) {
                        $table_data[$field_name] = $clinic_dose_data[$field_name];
                        if ($field_name == 'visit_date') {
                            $table_data['num_days'] = 0;
                        }
                    } else {
                        $table_data[$field_name] = $this->getRadioLabel($options_list[$field_name], $clinic_dose_data[$field_name]);
                    }
                }

                if (!empty($table_data["visit_date"])) {
                    $display_data[strtotime($table_data["visit_date"])] = $table_data;
                }
            }
        }

        // Sort by visit date
        ksort($display_data, SORT_NUMERIC);

        // Now calculate the number of days between clinic visits
        $first_visit = true;
        $previous_visit_date = '';
        $final_data_array = array();
        foreach ($display_data as $each_visit => $visit_info) {
            if ($first_visit) {
                $visit_info['num_days'] = 'N/A';
                $previous_visit_date = $visit_info['visit_date'];
                $first_visit = false;
            } else {
                // We are subtracting 1 day from visits since we don't count the day the dosing was changed
                // since that is the day after the clinic visit
                $interval = $this->calculate_num_days($previous_visit_date, $visit_info['visit_date']) - 1;
                //Plugin::log("Interval since last clinic visit: " . $interval);
                $visit_info['num_days'] = $interval;
                $previous_visit_date = $visit_info['visit_date'];
            }
            $final_data_array[] = $visit_info;
        }


		$this->return_data = $final_data_array;
        return;
    }

    private function calculate_num_days($date1, $date2) {
        $dateFormat1 = date_create($date1);
        $dateFormat2 = date_create($date2);
        return date_diff($dateFormat1, $dateFormat2)->format('%R%a');
    }

    private function getEvents() {

        $events = array();
        $event_names = REDCap::getEventNames();
        foreach ($event_names as $event_id => $event_name) {
            $events[$event_id] = $event_name;
        }

        return $events;
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

    private function getRadioLabel($optionList, $value) {

        return $optionList[$value];
    }

    private function getCheckboxLabel($optionList, $value) {

        $checkbox_labels = '';
        foreach ($value as $key => $set) {
            if ($set == "1") {
                if (empty($checkbox_labels)) {
                    $checkbox_labels .= $optionList[$key];
                } else {
                    $checkbox_labels .= ', ' . $optionList[$key];
                }
            }
        }

        return $checkbox_labels;
    }

}