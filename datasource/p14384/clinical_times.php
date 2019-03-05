<?php

function clinical_times($pid, $record_id) {

    global $module;

    $ct_helper = new clinical_timesClass($pid, $record_id);

    $main_data = $ct_helper->loadData();

    $title = "Clinic Visits";
    $header = array('Link','Event Type','Visit Date','Phase','Dose Given in Clinic','New Home Dose');

    $data = $ct_helper->retrieveClinicVisits($main_data);

    $return_data = array("title" => $title,
                         "header" => $header,
                         "data" => $data);

    return $return_data;
}


class clinical_timesClass
{
    private $pid;
    private $record_id;
    private $params = array();
    private $data_dict = array();
    private $fields = array();

    function __construct($pid, $record_id)
    {
        global $main_pid;

        // This initialization routine needs to initialize all project specific parameters necessary to
        // retrieve this data
        if ($pid == $main_pid) {
            $this->pid = $pid;
            $this->record_id = $record_id;
            $this->data_dict = REDCap::getDataDictionary($main_pid, 'array');
            $this->fields = array('visit_date','visit_type','clinic_dose_amt','dose_newhome_total');
        }
    }

    public function loadData() {

        global $module, $main_pid;

        $data = REDCap::getData($main_pid, 'array', $this->record_id, $this->fields);

        return $data;
    }


    public function retrieveClinicVisits($main_data) {

        global $module, $main_pid, $clinic_visit_form;

        $options_list['visit_type'] = $this->getDataOptions($this->data_dict['visit_type']);
        $options_list['clinic_dose_amt'] = $this->getDataOptions($this->data_dict['clinic_dose_amt']);
        $options_list['dose_newhome_total'] = $this->getDataOptions($this->data_dict['dose_newhome_total']);

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
                foreach ($clinic_dose_data as $repeat_event_id => $blank_data) {
                    foreach ($blank_data[""] as $instance_id => $instance_data) {

                        // The label will be the event name and instance number
                        $event_display = $events[$repeat_event_id] ."_". $instance_id;
                        $survey_link = APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $main_pid . "&page=" . $clinic_visit_form . "&id=" . $this->record_id .
                            "&event_id=" . $repeat_event_id . "&instance=".$instance_id;

                        $table_data['link'] = "<a href='" . $survey_link . "'>" . $this->record_id . "</a>";
                        $table_data['event_type'] = $event_display;

                        // add the rest of the fields
                        foreach ($this->fields as $field_name) {
                            if (empty($options_list[$field_name])) {
                                $table_data[$field_name] = $instance_data[$field_name];
                            } else {
                                $table_data[$field_name] = $this->getRadioLabel($options_list[$field_name], $instance_data[$field_name]);
                            }
                       }

                        $display_data[strtotime($table_data["visit_date"])] = $table_data;
                    }
                }
            } else {

                // LINK: add a custom link field: picked Clinic Form to redirect to, not dosing_form
                $survey_link = APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $main_pid . "&page=" . $clinic_visit_form . "&id=" . $this->record_id. "&event_id=" . $event_id;

                // EVENT: Using REDCAp::getEventNames
                $event_name = $events[$event_id];

                $table_data['link'] = '<a href="' . $survey_link . '">' . $this->record_id . "</a>";
                $table_data['event_type'] = $event_name;

                // add the rest of the fields
                foreach ($this->fields as $field_name) {
                    if (empty($options_list[$field_name])) {
                        $table_data[$field_name] = $clinic_dose_data[$field_name];
                    } else {
                        $table_data[$field_name] = $this->getRadioLabel($options_list[$field_name], $clinic_dose_data[$field_name]);
                    }
                }

                $display_data[strtotime($table_data["visit_date"])] = $table_data;
            }
        }

        // Sort by visit date
        ksort($display_data, SORT_NUMERIC);

        return $display_data;
    }

    private function getEvents() {

        global $main_pid;
        $selectedProjDD = new Project($main_pid);
        $events = array();
        foreach ($selectedProjDD->eventInfo as $event_id => $event_info) {
            $events[$event_id] = $event_info["name"];
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
        global $module;

        return $optionList[$value];
    }

    private function getCheckboxLabel($optionList, $value) {
        global $module;

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