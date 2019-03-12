<?php

function health_changes($pid, $record_id) {

    global $module;

    include $module->getModulePath() . "datasource/p" . $pid . "/config.php";
    $module->emLog("In health changes: diary config: " . json_encode($diary_configs));

    if (!empty($diary_configs)) {
        $hc_helper = new health_changesClass($pid, $record_id, $diary_configs);

        list($survey_data, $date_map) = $hc_helper->loadSurveyData();

        $data = $hc_helper->findHealthChanges($survey_data, $date_map);
    } else {
        $data = array();
    }

    $title = "Health Changes";
    $header = array('Link', 'Day Number', 'Date', 'Health Change Type', 'Describe Health Change', 'Date of Last Dose', 'Time of Last Dose');
    $return_array = array("title" => $title,
                          "header" => $header,
                          "data" => $data);

    return $return_array;
}


class health_changesClass
{
    private $pid;
    private $record_id;
    private $configs = array();
    private $data_dict = array();

    function __construct($pid, $record_id, $diary_configs)
    {
        global $main_pid;

        // This initialization routine needs to initialize all project specific parameters necessary to
        // retrieve this data
        if ($pid == $diary_configs["MAIN_PID"]) {
            $this->configs = $diary_configs;
            $this->pid = $pid;
            $this->record_id = $record_id;
        }
    }

    public function loadSurveyData() {

        global $module;

        $survey_data = REDCap::getData($this->pid, 'array', null, null, array($this->configs["DIARY_EVENT"]));

        $date_map = $this->makeDateMap($survey_data);

        return array($survey_data, $date_map);
    }

    private function makeDateMap($project_data) {

        global $module;
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

    function findHealthChanges($survey_data, $map_data) {

        global $module;

        $this->data_dict = REDCap::getDataDictionary($this->pid, 'array');
        $health_change_type_list = $this->getDataOptions($this->data_dict["health_change_type"]);

        //change request: Health Change -> Link, Day Number, Date, Health Change Type, Describe Health Change, Date of Last Dose, Time of Last Dose.
        $select = array('rsp_survey_day_number','rsp_survey_date','health_change_type');
        $health_change_fields = array('health_change_ill','health_change_inj','health_change_ai','health_change_ndras',
            'health_change_hcpvisit','health_change_newdiag','health_change_resolve','health_change_o');

        foreach ($survey_data[$this->record_id]["repeat_instances"][$this->configs["DIARY_EVENT"]][""] as $rec_id => $current) {

            //Skip the record if health_change is not set to YES
            if (($current['health_change']) != 1) {
                continue;
            }

            // add a custom link field
            $survey_link = APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $this->pid . "&page=" . $this->configs["DIARY_FORM"] . "&id=" . $rec_id;
            $survey_link .= "&event_id=" . $this->configs["DIARY_EVENT"];


            $table_data[$rec_id]['link'] = "<a href='" . $survey_link . "'>" . $rec_id . "</a>";

            // iterate over the rest of the selected fields
            foreach ($select as $key) {
                if ($key == "health_change_type") {
                    $table_data[$rec_id][$key] = $this->getCheckboxLabel($health_change_type_list, $current['health_change_type']);
                } else {
                    $table_data[$rec_id][$key] = $current[$key];
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
            $table_data[$rec_id]['describe_health_change'] = $describe_health_field;

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

            $table_data[$rec_id]['last_dose_date'] = $last_dose_date;
            $table_data[$rec_id]['last_dose_time'] = $candidate_time;
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

    private function getCheckboxLabel($optionList, $value) {
        global $module;

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