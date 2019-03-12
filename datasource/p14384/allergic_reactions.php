<?php


function allergic_reactions($pid, $record_id) {
    global $module;

    // Include the configuration parameters
    include $module->getModulePath() . "datasource/p" . $pid . "/config.php";
    $module->emLog("In allergic reactions: " . json_encode($diary_configs));

    if (!empty($diary_configs)) {
        $ar_helper = new allergic_reactionsClass($pid, $record_id, $diary_configs);

        list($proj_data, $date_map, $dosing_data, $ae_data) = $ar_helper->loadSurveyData();

        $data = $ar_helper->findAllergicReactions($proj_data, $date_map, $dosing_data, $ae_data);
    } else {
        $data = array();
    }

    $title = "Allergic Reactions";
    $header = array('Link', 'Adverse Event Link', 'Day Number', 'Date', 'Symptom', 'Ongoing from Previous Day', 'Start Time of Symptom',
        'Is Symptom Ongoing or Resolved?', 'End Time of Symptom', 'Meds', 'Date of Last Dose',
        'Time of Last Dose', 'Dosage of protein');
    $return_data = array("title" => $title,
                         "header" => $header,
                         "data" => $data);

    return $return_data;
}


class allergic_reactionsClass
{
    private $pid;
    private $record_id;
    private $dosing_fields = array();
    private $ae_fields = array();
    private $data_dict = array();
    private $symptom_aliases = array();
    private $config = array();


    function __construct($pid, $record_id, $diary_configs)
    {
        global $module;

        // This initialization routine needs to initialize all project specific parameters necessary to
        // retrieve this data
        if ($pid == $diary_configs["MAIN_PID"]) {
            $this->config = $diary_configs;
            $this->pid = $pid;
            $this->record_id = $record_id;

            $this->dosing_fields = array('dose_newhome_total', 'visit_date');
            $this->ae_fields = array('diary_day_num_link', 'diary_day_symptom_link');
            $this->data_dict = REDCap::getDataDictionary($this->config["MAIN_PID"], 'array');
            $this->symptom_aliases = array(1 => 'edemaface', 2 => 'itchym', 3 => 'itchysk', 4 => 'abpain',
                5 => 'nascon', 6 => 'diarrh', 7 => 'tbreath', 8 => 'tgthroat',
                9 => 'cough', 10 => 'vomit', 11 => 'urtica', 12 => 'hoarse',
                13 => 'rash', 14 => 'lighthd', 15 => 'itchyeye', 16 => 'nausea',
                99 => 'othersx');
        }
    }

    public function loadSurveyData() {

        global $module;

        $diary_data = REDCap::getData($this->config["MAIN_PID"], 'array', $this->record_id, null, $this->config["DIARY_EVENT"]);

        $dosing_data = REDCap::getData($this->config["MAIN_PID"], 'array', $this->record_id, $this->dosing_fields);
        $ae_data = REDCap::getData($this->config["MAIN_PID"], 'array', $this->record_id, $this->ae_fields);

        $date_map = $this->makeDateMap($diary_data);

        // Dana would like the dosing amount that the person took when they had an allergic reaction on the display.
        // This value [dose_newhome_total] is in the main project in the dosing_form instrument
        // and the date of new dosage is in the clinic visit instrument in field [visit_date].  We need to
        // pick the clinic visit with dosage closest to the allergic reaction date. I will retrieve values when
        // the [visit_type] is not (1 (Xolair Administration Only (No Food Dose)), 7 (End of Study), and 9(Early Termination))
        $dose_reformatted_data = $this->retrievetDosingData($dosing_data);

        $ae_reformatted_data = $this->retrieveAERecords($ae_data);

        return array($diary_data, $date_map, $dose_reformatted_data, $ae_reformatted_data);
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

    private function retrievetDosingData($all_data) {

        global $module;

        $dosing_events = array();
        foreach ($all_data[$this->record_id] as $dose_event => $dose_info) {

            if ($dose_event == "repeat_instances") {
                foreach ($dose_info as $event => $event_info) {

                    foreach($event_info as $instance => $instance_info) {

                        $date = $instance_info["visit_date"];
                        $dosage = $instance_info["dose_newhome_total"];
                        if (!empty($dosage)) {
                            $dosing_events[strtotime($date)] = array("visit_date" => $date, "dose_newhome_total" => $dosage);
                        }
                    }
                }

            } else {

                $date = $dose_info["visit_date"];
                $dosage = $dose_info["dose_newhome_total"];
                if (!empty($dosage)) {
                    $dosing_events[strtotime($date)] = array("visit_date" => $date, "dose_newhome_total" => $dosage);
                }
            }
        }

        // Sort by visit date
        krsort($dosing_events, SORT_NUMERIC);

        return $dosing_events;
    }

    private function retrieveAERecords($all_data) {

        global $module;

        $ae_data = array();
        foreach ($all_data[$this->record_id]["repeat_instances"][$this->config["AE_EVENT"]][$this->config["AE_FORM"]] as $instance => $instance_info) {

            $diary_symptom_link = $instance_info["diary_day_symptom_link"];
            $diary_date_link = $instance_info["diary_day_num_link"];
            if (!empty($diary_date_link)) {
                $ae_data[$diary_date_link . "-" . $diary_symptom_link] =
                    array("diary_day_num_link" => $diary_date_link, "diary_day_symptom_link" => $diary_symptom_link, "instance" => $instance);
            }

        }

        return $ae_data;
    }

    public function findAllergicReactions($project_data, $map_data, $dosing_data, $ae_data) {

        global $module;

        $symptom_options = $this->getDataOptions($this->data_dict["symptoms"]);
        $dosing_options = $this->getDataOptions($this->data_dict["dose_newhome_total"]);

        //
        // * "Start" -> "Start Time of Symptom"
        //"End" -> "End Time of Symptom"
        //"Dose Time" -> "Time of Last Dose"
        //Then if we put in date of last dose -> "Date of Last Dose".
        //"Status" -> "Is Symptom Ongoing or Resolved?"
        //
        // rsp_survey_date (right??), diary_dose_time symptoms edemaface_ongoing *_start *_status *_end *_meds
        // array('survey_day_number','symptoms', 'rsp_survey_date', 'diary_dose_time',$allergy.'_ongoing',$allergy.'_start', $allergy.'_status', $allergy.'_end', $allergy.'_meds');
        //chagne request:  Link, Day Number, Date, Symptom, Ongoing (can we change this to "Ongoing from Previous Day?"), Start Time of Symptom, Is Symptom Ongoing or Resolved, End Time of Symptom, Med, Date of Last Dose, Time of Last Dose.

        $table_data = array();
        foreach ($project_data[$this->record_id]["repeat_instances"] as $event_id => $event_info) {
            foreach($event_info as $instance_id => $instance_data) {
                // check the symptom field
                // foreach checkbox checked get the AllergyRow
                $current = current($instance_data);
                $symptoms = $current['symptoms'];

                foreach ($symptoms as $symptom => $checked) {
                    if ($checked == '1') {

                        // Figure out the list of fields related to this particular symptom
                        $decoded = $this->getRadioLabel($symptom_options, $symptom);
                        $prefix = $this->getPrefix($symptom);

                        $row_data = $this->getAllergyRows($decoded, $current, $prefix, $ae_data);

                        $symptom_start_date = $row_data['rsp_survey_date'];
                        $symptom_start_time = $row_data[$prefix . '_start'];

                        $last_dose_date = null;
                        $candidate_time = null;
                        //if there is a symptom start time, look for previous dose
                        if (!empty($symptom_start_time)) {

                            //look through map_data until find equal or greater date
                            foreach ($map_data as $date => $candidate) {
                                foreach ($project_data[$candidate]["repeat_instances"][$this->config["DIARY_EVENT"]][""] as $instance_id => $instance_info) {
                                    //get dose time
                                    $candidate_time = $instance_info['diary_dose_time'];
                                    $dose_taken = $instance_info['dose_taken'];

                                    if ($dose_taken != '1') {
                                        $candidate_time = '';
                                        $last_dose_date = '';
                                        continue;
                                    }

                                    if ($date == $symptom_start_date) {
                                        //check if clinic dose: if clinic leave time and date of last dose blank (see emial 11jul17)
                                        $clinic_or_home = $instance_info['diary_dose_loc'];
                                        if ($clinic_or_home == '2') {
                                            $candidate_time = '';
                                            $last_dose_date = '';
                                            break;
                                        }

                                        if (empty($candidate_time)) {
                                            $candidate_time = '';
                                            $last_dose_date = '';
                                            //no time so get next date
                                            continue;
                                        } else {
                                            if (strtotime($symptom_start_time) >= strtotime($candidate_time)) {
                                                $last_dose_date = $date;
                                                break;
                                            } else {
                                                continue;
                                            }
                                        }

                                    } else if ($date < $symptom_start_date) {
                                        $last_dose_date = $date;
                                        break;
                                    }
                                }
                            }
                        }
                        $row_data['last_dose_date'] = $last_dose_date;
                        $row_data['last_dose_time'] = $candidate_time;

                        // Find the amount of food product they took right before this reaction
                        $row_data["dose_newhome_total"] = $this->findDoseBeforeReaction($dosing_options, $row_data["rsp_survey_date"], $dosing_data);

                        $table_data[] = $row_data;
                    }
                }
            }
        }

        return $table_data;
    }

    private function getAllergyRows($symptom, $row, $allergy, $ae_data) {
        global $module;

        //change request:  Link, Day Number, Date, Symptom, Ongoing (can we change this to "Ongoing from Previous Day?"), Start Time of Symptom, Is Symptom Ongoing or Resolved, End Time of Symptom, Med, Date of Last Dose, Time of Last Dose.
        $select = array('rsp_survey_day_number','rsp_survey_date','symptoms',$allergy . '_ongoing',$allergy . '_start',$allergy . '_status',$allergy . '_end',$allergy . '_meds');
        $newkey = array('ongoing','start','status','end','meds');

        $options_list[$allergy . '_ongoing'] = $this->getDataOptions($this->data_dict[$allergy.'_ongoing']);
        $options_list[$allergy . '_status'] = $this->getDataOptions($this->data_dict[$allergy.'_status']);
        $options_list[$allergy . '_meds'] = $this->getDataOptions($this->data_dict[$allergy.'_meds']);

        // add a custom link field
        $survey_link = APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $this->config["MAIN_PID"] . "&page=" . $this->config["DIARY_FORM"] . "&id=" . $this->record_id;
        if ($this->config["DIARY_EVENT"]!= null) {
            $survey_link .= "&event_id=" . $this->config["DIARY_EVENT"];
        }

        $display = array();
        $display['link'] = "<a href='" . $survey_link . "'>$this->record_id</a>";
        $display['adverse_event'] = $this->adverseEventLink($row['rsp_survey_day_number'], $allergy, $ae_data);
        foreach ($select as $key) {
            if ($key == 'symptoms') {
                $display[$key] = $symptom;
            } else if (!empty($options_list[$key])) {
                if (is_array($row[$key])) {
                    $display[$key] = $this->getCheckboxLabel($options_list[$key], $row[$key]);
                } else {
                    $display[$key] = $this->getRadioLabel($options_list[$key], $row[$key]);
                }
            } else {
                $display[$key] = $row[$key];
            }
        }

        return $display;
    }

    private function adverseEventLink($diary_day_number, $allergy, $ae_data) {
        global $module;

        // Find the coded value of the allergen from the prefix
        $allergy_index = array_search($allergy, $this->symptom_aliases);

        // See if this reaction already has an ae created and if so, add a link to it.
        $survey_link = null;
        $ae_entry = $diary_day_number . '-' . $allergy_index;

        foreach($ae_data as $key => $data) {

            if ($key == $ae_entry) {
                // Create a link to the adverse event form
                //$survey_link = '<a href="'.APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $main_pid . "&page=" . $ae_form . "&id=" . $this->record_id .
                $survey_link = '<a href="'.APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $this->config["MAIN_PID"] . "&page=" . $this->config["AE_FORM"] . "&id=" . $this->record_id .
                                "&event_id=" . $this->config["AE_EVENT"] . "&instance=" . $data["instance"] . '" target="_blank">#'.$data["instance"].'</a>';
                break;
            }
        }

        // If there is not an adverse advent instrument already created, give the option to create one.
        if (empty($survey_link)) {
            $survey_link = '<a href="'.$module->getUrl("datasource/p14435/createAE.php") .
                '&data_pid='. $this->config["MAIN_PID"] .'&id='.$this->record_id .
                '&diary_day='.$diary_day_number.'&symptom='.$allergy_index .
                '" target="_blank">New AE <img style="align:center" src="'.APP_PATH_IMAGES.'plus.png"></a>';
        }

        return $survey_link;
    }

    private function findDoseBeforeReaction($dosing_options, $survey_date, $dosing_date_list) {

        global $module;

        $dose_taken = "";
        $allergy_date = strtotime($survey_date);
        foreach ($dosing_date_list as $dose_date => $dosage) {
            if ($dose_date <= $allergy_date) {
                $dose_taken = $dosage["dose_newhome_total"];
                break;
            }
        }

        if (!empty($dose_taken)) {
            $dose_label = $dosing_options[$dose_taken];
        } else {
            $dose_label = "";
        }

        return $dose_label;
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

    private function getPrefix($value) {
        global $module;

        $prefix = $this->symptom_aliases[$value];

        return $prefix;
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
