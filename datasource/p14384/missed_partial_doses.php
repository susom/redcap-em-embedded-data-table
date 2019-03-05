<?php

function missed_partial_doses($pid, $record_id) {

    $mpd_helper = new missed_partial_dosesClass($pid, $record_id);

    $survey_data = $mpd_helper->loadSurveyData();

    $title = "Missed/Partial Doses";
    $select = array('survey_day_number','survey_date','dose_taken','dose_taken_amt','diary_partial_dose','diary_no_dose','diary_partial_illness','diary_nodose_illness');
    $header = array('Link','Day Number','Date','Partial or None','If No/Partial Dose, Reason','If No/Partial Dose Due to Illness, Symptoms');

    $data = $mpd_helper->findMissingPartialDoses($survey_data);
    $return_data = array("title" => $title,
                         "header" => $header,
                         "data" => $data);

    return $return_data;
}


class missed_partial_dosesClass
{
    private $pid;
    private $record_id;

    function __construct($pid, $record_id)
    {
        global $main_pid;

        // This initialization routine needs to initialize all project specific parameters necessary to
        // retrieve this data
        if ($pid == $main_pid) {
            $this->pid = $pid;
            $this->record_id = $record_id;
        }
    }

    public function loadSurveyData() {

        global $module, $diary_event_id;

        $survey_data = REDCap::getData($this->pid, 'array', null, null, array($diary_event_id));

        return $survey_data;
    }

    public function findMissingPartialDoses($survey_data) {

        global $module, $diary_event_id, $diary_form;

        $data_dictionary = REDCap::getDataDictionary($this->pid, 'array');
        $partial_dose = $this->getDataOptions($data_dictionary["diary_partial_dose"]);
        $no_dose = $this->getDataOptions($data_dictionary["diary_no_dose"]);
        $partial_ill = $this->getDataOptions($data_dictionary["diary_partial_illness"]);
        $no_ill = $this->getDataOptions($data_dictionary["diary_nodose_illness"]);

        foreach ($survey_data[$this->record_id]["repeat_instances"][$diary_event_id][""] as $instance_id => $current) {
            // add a custom link field
           $survey_link = "<a class='text-primary' href='" . APP_PATH_WEBROOT."DataEntry/index.php?pid=".$this->pid."&page=".$diary_form."&id=".$this->record_id;
            $survey_link .= "&event_id=" . $diary_event_id . "&instance=" . $instance_id;
            $survey_link .= "'>$this->record_id</a>";

            // only missed or partial doses
            if (($current['dose_taken'] == '0') || (($current['dose_taken'] == '1') && ($current['dose_taken_amt'] == '2'))) {
                //link
                $table_data[$instance_id]['link'] = $survey_link;

                //Day Number
                $table_data[$instance_id]['survey_day_number'] = $current['survey_day_number'];

                //Survey Date
                $table_data[$instance_id]['survey_date'] = $current['survey_date'];

                //Merged 'dose_taken' U'dose_taken_amt'
                if (($current['dose_taken'] == '0')) {
                    $table_data[$instance_id]['merge_dose_taken'] = 'None';
                } elseif (($current['dose_taken'] == '1') && ($current['dose_taken_amt'] == '2')) {
                    $table_data[$instance_id]['merge_dose_taken'] = 'Partial';
                } else {
                    $table_data[$instance_id]['merge_dose_taken'] = '';
                }

                // merge 'diary_partial_dose','diary_no_dose',
                $display_partial = $partial_dose[$current['diary_partial_dose']];
                $display_no = $no_dose[$current['diary_no_dose']];
                $display_dose = $display_partial.$display_no;
                $table_data[$instance_id]['diary_partial_dose'] = $display_dose;

                // merge 'diary_partial_illness','diary_nodose_illness'
                //$display_ill_partial = $partial_ill[$current['diary_partial_illness']];
                $display_ill_partial = $this->getCheckboxValues($current['diary_partial_illness'], $partial_ill);
                $display_ill_no = $this->getCheckboxValues($current['diary_nodose_illness'], $no_ill);
                $illness = $display_ill_partial.$display_ill_no;
                $table_data[$instance_id]['diary_partial_illness'] = $illness;
            }
        }

        return $table_data;
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

    private function getCheckboxValues($currentValues, $no_ill) {

        global $module;

        $illness_list = '';
        foreach($currentValues as $illness => $set) {
            if ($set == '1') {
                if (empty($illness_list)) {
                    $illness_list .= $no_ill[$illness];
                } else {
                    $illness_list .= ', ' . $no_ill[$illness];
                }
            }
        }

        return $illness_list;
    }

}