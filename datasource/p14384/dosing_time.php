<?php


function dosing_time($pid, $record_id) {
    global $module;

    $dt_helper = new dosing_timeClass($pid, $record_id);

    $survey_data = $dt_helper->loadSurveyData();

    $title = "Dosing Time";
    $header = array('Link', 'Day Number', 'Date', 'Time of Dose', 'Dose Taken', 'Full or Partial', 'Clinic or Home');

    $data = $dt_helper->generateDosingTimeTable($survey_data);

    $return_data = array("title" => $title,
                         "header" => $header,
                         "data" => $data);

    return $return_data;

}

class dosing_timeClass
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

        $survey_data = REDCap::getData($this->pid, 'array', $this->record_id, null, array($diary_event_id));

        return $survey_data;
    }

    public function generateDosingTimeTable($survey_data)
    {
        global $module, $diary_event_id, $diary_form;

        $select = array('survey_day_number', 'survey_date', 'diary_dose_time', 'dose_taken', 'dose_taken_amt', 'diary_dose_loc');
        $data_dict = REDCap::getDataDictionary($this->pid, 'array');
        $options_list["dose_taken"] = $this->getDataOptions($data_dict["dose_taken"]);
        $options_list["dose_taken_amt"] = $this->getDataOptions($data_dict["dose_taken_amt"]);
        $options_list["diary_dose_loc"] = $this->getDataOptions($data_dict["diary_dose_loc"]);

        $table_data = array();
        $sorting_data = array();
        $record = $survey_data[$this->record_id]["repeat_instances"][$diary_event_id][""];

        foreach ($record as $instance_id => $current) {
            $survey_link = APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $this->pid . "&page=" . $diary_form . "&id=" . $this->record_id .
                            "&event_id=" . $diary_event_id;

            //link
            $table_data['link'] = "<a href='" . $survey_link . "'>" . $this->record_id . '-'. $instance_id . "</a>";

            foreach ($select as $key) {
                if (!empty($options_list[$key])) {
                    $table_data[$key] = $this->getRadioLabel($options_list[$key], $current[$key]);
                } else {
                    $table_data[$key] = $current[$key];
                }
            }
            $sorting_data[$table_data["survey_day_number"]] = $table_data;
        }

        // Sort by survey_day_number
        ksort($sorting_data, SORT_NUMERIC);

        $display_data = array();
        foreach($sorting_data as $day => $info) {
            $display_data[] = $info;
        }

        return $display_data;
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
        global $module;

        return $optionList[$value];
    }

}


