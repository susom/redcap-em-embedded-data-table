<?php
namespace Stanford\EDT;

require_once ($module->getModulePath() . "classes/CustomTableInterface.php");

use \REDCap;

class motif_missing_diary_entries implements \Stanford\EDT\CustomTableInterface
{
    const FIELDS_PER_ROW = 1;

    private $pid;
    private $record_id;
    private $end_of_study_value;
    private $early_termination_value;
    private $visit_date;
    private $visit_type;
    private $configs = array();
    private $return_data = array();

    function __construct($pid, $record_id) {

        // This initialization routine needs to initialize all project specific parameters necessary to
        // retrieve this data
        // Include the configuration parameters
    	include "motif_config.php";

    	if (!empty($diary_configs)) {
            $this->configs = $diary_configs;
            $this->pid = $pid;
            $this->record_id = $record_id;

            $this->visit_date = 'visit_date';
            $this->visit_type = 'visit_type';

            $this->end_of_study_value = 7;
            $this->early_termination_value = 9;
            
            // Retrieve diary entries that were completed
            $missing_diary = $this->loadSurveyData();

			// Find the missing entries
        	$this->findMissingEntries($missing_diary);
        }
    }

	public function getTitle() {
		return "Summary of Dates for Missing Diary Entries";
	}
	
	public function getHeader() {
		return array("Dates");
	}
	
	public function getData() {
		return $this->return_data;
	}


    private function non_breaking_hyphens($content)
    {
        return str_replace('-', '&#8209;', $content);
    }

    private function loadSurveyData() {

        // Retrieve Diary Entries
        $survey_data = REDCap::getData($this->pid, 'array', null, array($this->configs["SURVEY_DATE"]),
            array($this->configs["DIARY_EVENT"]));

        // Make a list of dates that diary entries were filled out
        $map = $this->makeDateMap($survey_data);

        // See if the participant finished the study.  If so, use the study end date as the last day of the diary entries
        $filter = "([".$this->visit_type."] = ".$this->end_of_study_value.") or ([".$this->visit_type."] = ".$this->early_termination_value.")";
        $study_data = REDCap::getData($this->pid, 'array', $this->record_id,
            array($this->visit_date, $this->visit_type), null, null, false, false, false, $filter);

        if (!empty($study_data)) {
            $event_id = array_keys($study_data[$this->record_id]);
            $ending_date = $study_data[$this->record_id][$event_id[0]][$this->visit_date];
        } else {
            $ending_date = date("Y-m-d");
        }

        // Create list of dates when diary entries were not filled out ending when study ends or today
        $missing_dates =  $this->makeMissingMap($map, $ending_date);
        return $missing_dates;
    }

    private function makeDateMap($survey_data) {

        $map = array();

        foreach ($survey_data[$this->record_id]["repeat_instances"][$this->configs["DIARY_EVENT"]][""] as $survey_id => $current) {
            $date = $current[$this->configs["SURVEY_DATE"]];
            $map[$date] = $survey_id;
        }
        
        krsort($map);

        return $map;
    }

    /**
     * Use any existing entries to interpolate the days and missing dates
     * If no diary entries for this Participant just report text: no survey entries
     */
    private function makeMissingMap($map_data, $ending_date) {

        $missing = array();
        if (!empty($map_data)) {
            ksort($map_data);

            //interpolate the first day from the first pair
            reset($map_data);
            $first_key = key($map_data);

            $re = '/.*-D(\d*)$/';
            preg_match_all($re, $map_data[$first_key], $matches, PREG_SET_ORDER, 0);

            $match_first_date = $first_key;
            $match_first_day_num = $matches[0][1];

            $global_first_date = date('Y-m-d', strtotime('-' . ($match_first_day_num - 1) . ' day', strtotime($match_first_date)));

            $global_last_day_num = floor((strtotime($ending_date) - strtotime($match_first_date)) / (60 * 60 * 24)) + $match_first_day_num;

            // This is an array of all dates from the first diary entry to the ending date
            $map = array();
            for ($i = 1; $i <= $global_last_day_num; $i++) {
                $map[] = date('Y-m-d', strtotime('+' . ($i - 1) . ' day', strtotime($global_first_date)));
            }

            // Find the dates of diary entries that have not already been filled out
            $missing = array_diff($map, array_keys($map_data));

            ksort($missing);

        } else {
            $missing[] = "No Survey Entries";
        }

        return $missing;
    }

    private function findMissingEntries($missing_diary) {

        // data tables requires even tables with the same number of columns.  If this list does not end on
        // a division of 9, add blanks at the end
        $num_of_entries = count($missing_diary);
        $leftover =  $num_of_entries % self::FIELDS_PER_ROW;

        if ($leftover != 0) {
            $add_fields = self::FIELDS_PER_ROW - $leftover;
            for ($ncnt=0; $ncnt < $add_fields; $ncnt++) {
                $missing_diary[$num_of_entries + $ncnt] = "";
            }
        }

        $data = array();
        $nrow = $ncol = 0;
        foreach($missing_diary as $date) {
            if ($ncol == self::FIELDS_PER_ROW) {
                $ncol = 0;
                $nrow++;
            }
            $data[$nrow][$ncol] = $this->non_breaking_hyphens($date);
            $ncol++;
        }

        $this->return_data = $data;
        return;
    }
}