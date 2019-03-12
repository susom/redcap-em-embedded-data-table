<?php
/**
 * Created by PhpStorm.
 * User: LeeAnnY
 * Date: 8/7/2018
 * Time: 12:48 PM
 */
namespace Stanford\EDT;
/** @var \Stanford\EDT\EDT $module */

use \REDCap;

$pid = isset($_GET['data_pid']) && !empty($_GET['data_pid']) ? $_GET['data_pid'] : null;
$record = isset($_GET['id']) && !empty($_GET['id']) ? $_GET['id'] : null;
$diary_day = isset($_GET['diary_day']) && !empty($_GET['diary_day']) ? $_GET['diary_day'] : null;
$symptom = isset($_GET['symptom']) && !empty($_GET['symptom']) ? $_GET['symptom'] : null;

require_once ($module->getModulePath() . "datasource/p14435/config.php");
//require_once ($module->getModulePath() . "datasource/p" . $pid . "/config.php");

if (empty($record)) {
    echo "<h6>The displays are associated with a record. Please select a record and try again!</h6>";
    return;
}

// Setup for this display
$primary_key = 'participant_id';
$ae_field_day = 'diary_day_num_link';
$ae_field_symptom = 'diary_day_symptom_link';
$ae_complete = 'adverse_event_complete';
$diary_day_num = 'rsp_survey_day_number';
$diary_symptoms = 'symptoms';
$visit_date = 'visit_date';
$symptom_aliases = array(1 => 'edemaface', 2 => 'itchym', 3 => 'itchysk', 4 => 'abpain',
    5 => 'nascon', 6 => 'diarrh', 7 => 'tbreath', 8 => 'tgthroat',
    9 => 'cough', 10 => 'vomit', 11 => 'urtica', 12 => 'hoarse',
    13 => 'rash', 14 => 'lighthd', 15 => 'itchyeye', 16 => 'nausea',
    99 => 'othersx');
$ae_term_possibilities = array(1=>804, 2=>7022, 3=>2320, 4=>702, 5=>2229, 6=>723,
                               7=>"", 8=>2264, 9=>2213, 10=>7017, 11=>2333, 12=> 2217,
                                13=>2340, 14=>1747, 15=>603, 16=>779, 99=>"");

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
    global $module, $pid, $record, $ae_complete, $ae_event_id, $ae_form, $main_pid;

    $instance_data = REDCap::getData($pid, 'array', $record, array($ae_complete));

    $instances = $instance_data[$record]["repeat_instances"][$ae_event_id][$ae_form];
    $instance_list = array();
    foreach($instances as $instance => $instance_info) {
        $instance_list[] = $instance;
    }

    $module->emLog("instance list: " . json_encode($instance_list) . ", and next instance " . (max($instance_list) + 1));
    return max($instance_list) + 1;
}

function getDiaryData() {

    global $module, $pid, $record, $diary_day, $diary_event_id, $diary_day_num;

    $diary_data = REDCap::getData($pid, 'array', $record, null, $diary_event_id);
    foreach($diary_data[$record]["repeat_instances"][$diary_event_id][""] as $instance => $instance_info) {
        if ($instance_info[$diary_day_num] == $diary_day) {
            $diary_entry = $instance_info;
            break;
        }
    }

    return $diary_entry;
}

function getClinicVisits() {

    global $module, $pid, $record, $visit_date;

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
    global $module, $symptom_aliases, $symptom, $record, $pid, $ae_form, $ae_event_id,
           $diary_day, $ae_term_possibilities, $ae_field_day, $ae_field_symptom, $survey_date_field;

    $data_dict = REDCap::getDataDictionary($pid, 'array');

    $prefix = $symptom_aliases[$symptom];
    $survey_date = $diary_data[$survey_date_field];
    $data_to_save = array();

    $data_to_save["participant_id"] = $record;

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
    $data_to_save["ae_oit_dt"] = $diary_data[$survey_date_field] . ' ' . $diary_data["diary_dose_time"];

    // ae_oit_dose (get from clinic_visit?)????

    // ae_start_dt, ae_end_dt (use survey_date + abpain_start and survey_date + abpain_end
    // If this reaction started on a previous day
    if ($diary_data[$prefix . "_ongoing"][1] == 1) {
        $data_to_save["ae_start_dt"] = "";
        $data_to_save["ae_outcome"] = 3;
    } else {
        $data_to_save["ae_start_dt"] = $diary_data[$survey_date_field] . ' ' . $diary_data[$prefix . "_start"];
    }

    if ($diary_data[$prefix . "_status"] == 2) {
        $data_to_save["ae_end_dt"] = $diary_data[$survey_date_field] . ' ' . $diary_data[$prefix . "_end"];
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
    $new_ae_instance_data[$record]['repeat_instances'][$ae_event_id][$ae_form][$new_ae_instance] = $data_to_save;

    $saved_items = REDCap::saveData($pid, 'array', $new_ae_instance_data);

    $module->emDebug("Errors: " . json_encode($saved_items["errors"]));
    $module->emDebug("Warnings: " . json_encode($saved_items["warnings"]));
    $module->emDebug("IDs: " . json_encode($saved_items["ids"]));
    $module->emDebug("Item Count: " . json_encode($saved_items["item_coun"]));

    // Create a link to the new ae
    $link = APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . $pid . "&page=" . $ae_form . "&id=" . $record .
        "&event_id=" . $ae_event_id . "&instance=" . $new_ae_instance;

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
