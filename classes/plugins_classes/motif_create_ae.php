<?php
namespace Stanford\EDT;


use \REDCap;
use \Plugin;

require_once "../../redcap_connect.php";
require_once "motif_config.php";

$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? filter_var($_GET['pid'],FILTER_SANITIZE_NUMBER_INT) : null;
$record = isset($_GET['id']) && !empty($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_STRING) : null;
$diary_day = isset($_GET['diary_day']) && !empty($_GET['diary_day']) ? filter_var($_GET['diary_day'],FILTER_SANITIZE_STRING) : null;
$symptom = isset($_GET['symptom']) && !empty($_GET['symptom']) ? filter_var($_GET['symptom'], FILTER_SANITIZE_STRING) : null;

if (empty($record)) {
    echo "<h6>The displays are associated with a record. Please select a record and try again!</h6>";
    return;
}

// Setup for this display
$primary_key = 'participant_id';
$ae_field_day = 'diary_day_num_link';
$ae_field_symptom = 'diary_day_symptom_link';
$diary_day_num = 'rsp_survey_day_number';
$diary_symptoms = 'symptoms';
$visit_date = 'visit_date';

// Retrieve data from config file
if (!empty($diary_configs)) {
    $ae_complete = $diary_configs["AE_FORM"] . "_complete";
} else {
    $ae_complete = null;
}
if (!empty($symptom_aliases_diary)) {
    $symptom_aliases = $symptom_aliases_diary;
} else {
    $symptom_aliases = null;
}
if (!empty($ae_terms)) {
    $ae_term_possibilities = $ae_terms;
} else {
    $ae_term_possibilities = null;
}

// Figure out the next ae instance
$new_ae_instance = findNextInstance();

$diary_data = getDiaryData();

$clinic_visits = getClinicVisits();

$ae_url = createNewAE($new_ae_instance, $diary_data, $clinic_visits);

// Load the new AE instrument
header("Location: " . $ae_url);

// This needs to be after the api checks otherwise it gets added to the return data
require APP_PATH_DOCROOT . "ProjectGeneral/header.php";

function findNextInstance() {
    global $record, $ae_complete, $diary_configs, $pid;

    $instance_data = REDCap::getData($pid, 'array', $record, array($ae_complete));

    $instances = $instance_data[$record]["repeat_instances"][$diary_configs["AE_EVENT"]][$diary_configs["AE_FORM"]];
    $instance_list = array();
    foreach($instances as $instance => $instance_info) {
        $instance_list[] = $instance;
    }

    return max($instance_list) + 1;
}

function getDiaryData() {

    global $record, $diary_day, $diary_day_num, $diary_configs, $pid;

    $diary_data = REDCap::getData($pid, 'array', $record, null, $diary_configs["DIARY_EVENT"]);
    foreach($diary_data[$record]["repeat_instances"][$diary_configs["DIARY_EVENT"]][""] as $instance => $instance_info) {
        if ($instance_info[$diary_day_num] == $diary_day) {
            $diary_entry = $instance_info;
            break;
        }
    }

    return $diary_entry;
}

function getClinicVisits() {

    global $record, $visit_date, $pid;

    $visit_data = REDCap::getData($pid, 'array', $record, array($visit_date));

    $clinic_visits = array();
    foreach($visit_data[$record] as $event_id => $event_data) {
        if (!empty($event_data[$visit_date])) {
            $event_label = REDCap::getEventNames(false, false, $event_id);
            $clinic_visits[strtotime($event_data[$visit_date])] = array($visit_date =>$event_data[$visit_date], "event" => $event_label);
        }
    }

    krsort($clinic_visits);
    return $clinic_visits;
}

function createNewAE($new_ae_instance, $diary_data, $clinic_visits)
{
    global $primary_key, $symptom_aliases, $symptom, $record,
           $diary_day, $ae_term_possibilities, $ae_field_day, $ae_field_symptom,
           $diary_configs, $pid;

    $data_dict = REDCap::getDataDictionary($pid, 'array');

    $prefix = $symptom_aliases[$symptom];
    $survey_date = $diary_data[$diary_configs["SURVEY_DATE"]];
    $data_to_save = array();

    $data_to_save[$primary_key] = $record;

    //Find the ae_week (clinic dates are sorted desc)
    $survey_time = strtotime($survey_date);
    foreach ($clinic_visits as $clinic_date => $clinic_info) {
        if ($clinic_date < $survey_time) {
            $optionList = getDataOptions($data_dict["ae_week"]);
            $ae_week_index = array_search($clinic_info["event"], $optionList);
            $data_to_save["ae_week"] = $ae_week_index;
            break;
        }
    }

    // ae_event_type (diary_dose_time = dose taken time and reaction time
    $two_hours = 60 * 60 * 2;
    $dose_time = $diary_data["diary_dose_time"];
    $reaction_time = $diary_data[$prefix . "_start"];
    $delta = strtotime($reaction_time) - strtotime($dose_time);

    // if delta is > 2 hrs, reaction was not allergic(1), otherwise it is considered allergic(2)
    if ($delta > $two_hours) {
        $data_to_save["ae_event_type"] = 1;
    } else {
        $data_to_save["ae_event_type"] = 2;
    }

    // ae_terms
    if (!empty($diary_data["symptoms"])) {
        $data_to_save["ae_terms"] = $ae_term_possibilities[$symptom];
    }

    // ae_oit_dose_type
    //dose_taken_amt (1=full or 2=partial) and diary_dose_loc (1=home or 2=clinic) clinic dose
    // goes into ae_oit_dose_type(1=Full, 2=Partial, 3=Clinic
    if (!empty($diary_data["dose_taken_amt"])) {
        $data_to_save["ae_oit_dose_type"] = $diary_data["dose_taken_amt"];
    } else if ($diary_data["diary_dose_loc"] == 2) {
        $data_to_save["ae_oit_dose_type"] = 3;
    }

    // ae_oit_dt (diary_dose_time)
    $data_to_save["ae_oit_dt"] = date("Y-m-d H:i", strtotime($diary_data[$diary_configs["SURVEY_DATE"]] . ' ' . $diary_data["diary_dose_time"]));

    // ae_oit_dose (get from clinic_visit?)????

    // ae_start_dt, ae_end_dt (use survey_date + abpain_start and survey_date + abpain_end
    // If this reaction started on a previous day
    if ($diary_data[$prefix . "_ongoing"][1] == 1) {
        $data_to_save["ae_start_dt"] = "";
        $data_to_save["ae_outcome"] = 3;
    } else {
        $start_date = $diary_data[$diary_configs["SURVEY_DATE"]] . ' ' . $diary_data[$prefix . "_start"];
        $data_to_save["ae_start_dt"] = date("Y-m-d H:i", strtotime($start_date));
    }

    if ($diary_data[$prefix . "_status"] == 2) {
        $ending_time = $diary_data[$diary_configs["SURVEY_DATE"]] . ' ' . $diary_data[$prefix . "_end"];
        if (!empty($data_to_save["ae_start_dt"]) && !empty($ending_time)) {
            // If the ending date/time is before the starting date/time, the problem must have gone over
            // midnight so add a day to the ending time
            if (strtotime($data_to_save["ae_start_dt"]) > strtotime($ending_time)) {
               $data_to_save["ae_end_dt"] = date("Y-m-d H:i", strtotime("+1 day", strtotime($ending_time)));
            } else {
                $data_to_save["ae_end_dt"] = date("Y-m-d H:i", strtotime($ending_time));
            }
        } else {
            $data_to_save["ae_end_dt"] = date("Y-m-d H:i", strtotime($ending_time));
        }
    } else {
        $data_to_save["ae_end_dt"] = "";
    }

    // ae_intervention (1=yes, 0=no), ae_intervention_mode (concomitant medications = 0:1)
    if ($diary_data[$prefix . "_meds_yn"] == 1) {
        $data_to_save["ae_intervention"] = 1;
        $data_to_save["ae_intervention_mode"][0] = 1;
    }

    // Populate the reaction event that is prompting this ae record
    $data_to_save[$ae_field_day] = $diary_day;
    $data_to_save[$ae_field_symptom] = $symptom;
    $new_ae_instance_data[$record]['repeat_instances'][$diary_configs["AE_EVENT"]][$diary_configs["AE_FORM"]][$new_ae_instance] = $data_to_save;

    $saved_items = REDCap::saveData($pid, 'array', $new_ae_instance_data);
    if (!empty($saved_items["errors"])) {
        Plugin::log("Data to save: " . json_encode($new_ae_instance_data));
        Plugin::log("Errors: " . json_encode($saved_items["errors"]));
        Plugin::log("Warnings: " . json_encode($saved_items["warnings"]));
        Plugin::log("IDs: " . json_encode($saved_items["ids"]));
        Plugin::log("Item Count: " . json_encode($saved_items["item_count"]));
    }

    // Create a link to the new ae
    $link = APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $pid . "&page=" . $diary_configs["AE_FORM"] . "&id=" . $record .
        "&event_id=" . $diary_configs["AE_EVENT"] . "&instance=" . $new_ae_instance;

    return $link;
}

function getDataOptions($dd) {

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
