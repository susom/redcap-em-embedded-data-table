<?php
namespace Stanford\EDT;

/**
 * Created by PhpStorm.
 * User: LeeAnnY
 * Date: 2019-02-19
 * Time: 09:48
 */
use \Project;
use \Exception;
use \Redcap;

function getProjDataDictionary($selected_pid) {
    global $module;

    $selectedProj = null;
    if (!empty($selected_pid)) {
        try {
            $selectedProj = new Project($selected_pid, true);
            if ($selectedProj->project_id == $selected_pid) {
                $selectedProj->setRepeatingFormsEvents();
            } else {
                $module->emError("Error retrieving project data dictionary for project $selected_pid");
                $selectedProj = null;
            }
        } catch (Exception $exception) {
            $module->emError("Exception thrown when retrieving Project Data Dictionary for project $selected_pid");
        }
    }
    return $selectedProj;
}

function getConfigs() {
    global $module;
    $config_names = $module->getProjectSetting("config_name");
    $config_info = $module->getProjectSetting("config_info");
    return array($config_names, $config_info);
}

function setConfigs($config_names, $config_info) {
    global $module;
    $module->setProjectSetting("config_name", $config_names);
    $module->setProjectSetting("config_info", $config_info);
}

function getLabel($selectedProj, $field, $value)
{
    global $module;

    if (empty($field)) {
        $module->emError("The variable list is undefined so cannot retrieve data dictionary options.");
    }

    $fieldInfo = $selectedProj->metadata[$field];

    $label = null;
    switch ($fieldInfo["element_type"]) {
        case "select":
        case "radio":
        case "yesno":

            $optionList = $fieldInfo["element_enum"];
            $options = explode('\n', $optionList);
            foreach ($options as $optionKey => $optionValue) {

                $option = explode(',', $optionValue, 2);
                if (trim($option[0]) == $value) {
                    if (empty($label)) {
                        $label = trim($option[1]);
                    } else {
                        $label .= ', ' . trim($option[1]);
                    }
                }
            }

            break;
        case "checkbox":

            $optionList = $fieldInfo["element_enum"];
            $options = explode('\n', $optionList);
            foreach ($options as $optionKey => $optionValue) {
                $option = explode(',', $optionValue);
                if ($value[trim($option[0])] == 1) {
                    if (empty($label)) {
                        $label = trim($option[1]);
                    } else {
                        $label .= ', ' . trim($option[1]);
                    }
                }
            }
            break;
        default:
            $label = $value;
    }

    return $label;
}

function retrieveDataFromRepeatingForms($selectedProj, $config_info, $record_id) {

    global $module;

    // First do a query to see which record in the data project fit our filter
    // Since the record ID may not be in the same event as the data we are retrieving, don't use the event_id.
    // The record ID will be the key to returned data not matter if it is a classical or longitudinal project.
    if (empty($config_info['key_field'])) {
        $recordNum = $record_id;
    } else {
        $filter = "[" . $config_info['key_field'] . "] = '$record_id'";
        $recordList = REDCap::getData($config_info["project_id"], 'array', null, array_keys($config_info["fields"]),
            null, null, null, null, null, $filter);
        $recordNum = array_keys($recordList)[0];
    }

    // See if we are retrieving data from an event
    if (is_numeric($config_info["event"])) {
        $eventList = $config_info["event"];
    } else {
        $eventList = null;
    }

    // Instantiate the class to retrieve repeating form data
    $repeating_form = new RepeatingFormsExt($config_info["project_id"], $config_info["form"], $eventList);

    // Retrieve the data
    $displayData = array();
    $data = $repeating_form->getAllInstancesFlat($recordNum, array_keys($config_info["fields"]), $config_info['event']);

    // Retrieve the data and format it for the display
    foreach ($data as $one_row => $record_info) {

        // For each row, add the record/instance of this data first with a link
        $one_record = array();
        $record_link = "<a class='text-primary' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . trim($config_info["project_id"]) . "&page=" . $config_info['form'] . "&id=" . $recordNum . "&event_id=" . $config_info["event"] . "&instance=" . $record_info['instance'] . "'>$recordNum-" . $record_info['instance'] . "</a>";
        $one_record[$selectedProj->table_pk] = $record_link;

        foreach($config_info["fields"] as $key => $value) {
            // If the record primary key is in the list, don't add it because we are already adding it above as the first field
            if ($key != $selectedProj->table_pk) {
                $one_record[$key] = getLabel($selectedProj, $key, $record_info[$key]);
            }
        }

        $displayData[] = $one_record;
    }

    // Add the record/instance column to the header and take out the primary key if it was added  separately
    $fields = $config_info["fields"];
    unset($fields[$selectedProj->table_pk]);
    $header = extractHeaders($fields);
    $displayHeader = array_merge(array("Instance"), $header);

    $return_data = array("header" => $displayHeader,
        "data" => $displayData);

    return $return_data;
}


function retrieveDataAcrossEvents($selectedProj, $config_info, $record_id) {

    global $module, $Proj;

    // Retrieve data for the display
    if (empty($config_info['key_field'])) {
        $records = array($record_id);
    } else {
        // First find the records that meet our criteria
        $filter = "[" . $config_info['key_field'] . "] = '$record_id'";
        $data = REDCap::getData($config_info["project_id"], 'array', null, array_keys($config_info["fields"]), null,
            null, null, null, null, $filter);
        $records = array_keys($data);
    }

    // Figure out the arm number
    foreach($selectedProj->eventInfo as $eventId => $eventInfo) {
        if ($eventInfo["arm_id"] == trim($config_info["arm"])) {
            $arm_num = $eventInfo["arm_num"];
            break;
        }
    }

    // See if we are retrieving data from an event
    if (is_numeric($config_info["event"])) {
        $eventList = array($config_info["event"]);
    } else {
        $eventList = null;
    }

    // Now that we have the list of records we want to retrieve, get the fields
    if (empty($records)) {
        $displayData = array();
    } else {
        $data = REDCap::getData($config_info["project_id"], 'array', $records, array_keys($config_info["fields"]), $eventList);

        // Only display rows which have a value.  If the linking key is in a different event than the other data, there will be
        // records for events which have data even though these fields do not belong to them.
        $displayData = array();
        foreach ($data as $record => $recordInfo) {
            foreach ($recordInfo as $eventId => $eventInfo) {
                $fieldNotNullCount = 0;
                $fieldArray = array();

                // Look to see if this is a repeating instance or just one
                if ($eventId == 'repeat_instances') {
                    foreach ($eventInfo as $repeatEventId => $repeatEventInfo) {
                        foreach ($repeatEventInfo as $formName => $eventData) {
                            foreach ($eventData as $instanceId => $instanceInfo) {

                                $first_form = $selectedProj->eventsForms[$config_info["event"]][0];

                                // Add a link to the record/instance
                                $record_link = "<a class='text-primary' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . trim($config_info["project_id"]) .
                                    "&page=" . $first_form .
                                    "&event_id=" . $repeatEventId . "&id=" . $record . "&instance=" . $instanceId . "'>" . $record . "-" . $instanceId . "</a>";
                                $fieldArray[$selectedProj->table_pk] = $record_link;

                                // Add the rest of the requested fields
                                foreach ($config_info["fields"] as $fieldname => $fieldlabel) {
                                    if ($fieldname != $selectedProj->table_pk) {
                                        $fieldArray[$fieldname] = getLabel($selectedProj, $fieldname, $instanceInfo[$fieldname]);
                                        if (!empty($fieldArray[$fieldname])) $fieldNotNullCount++;
                                    }
                                }

                                // We found some data that was not null so save it for display
                                if ($fieldNotNullCount > 0) {
                                    $displayData[] = $fieldArray;
                                }
                            }
                        }
                    }

                } else {

                    // Add a link to the record in the data project
                    $record_link = "<a class='text-primary' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . trim($config_info["project_id"]) .
                        "&page=" . $config_info["form"] . "&event_id=" . $eventId . "&id=" . $record . "'>" . $record . "</a>";
                    $fieldArray[$selectedProj->table_pk] = $record_link;

                    foreach ($config_info["fields"] as $fieldname => $fieldlabel) {
                        if ($selectedProj->table_pk != $fieldname) {
                            $fieldArray[$fieldname] = getLabel($selectedProj, $fieldname, $eventInfo[$fieldname]);
                            if (!empty($fieldArray[$fieldname])) $fieldNotNullCount++;
                        }
                    }

                    // We found some data that was not null so save it for display
                    if ($fieldNotNullCount > 0) {
                        $displayData[] = $fieldArray;
                    }
                }
            }
        }
    }

    // Add the event to the header so the user knows where the data is from and create display
    $fields = $config_info["fields"];
    unset($fields[$selectedProj->table_pk]);
    $header = extractHeaders($fields);
    $display_headers = array_merge(array("Record"), $header);

    $return_data = array("header" => $display_headers,
        "data" => $displayData);

    return $return_data;
}


function retrieveDataUsingPrimaryKey($selectedProj, $config_info, $record_id) {

    // Use the primary key as a filter to retrieve data
    $filter = "[" . $config_info["key_field"] . "] = '$record_id'";
    $data = REDCap::getData($config_info["project_id"], 'array', null, array_keys($config_info["fields"]), null,
        null, null, null, null, $filter);

    // Reformat the data into a display array
    $formatted_data = array();
    foreach($data as $entry => $info) {
        foreach ($info as $record) {
            $one_record = array();
            foreach (array_keys($config_info["fields"]) as $field_name) {
                $one_record[$field_name] = getLabel($selectedProj, $field_name, $record[$field_name]);
            }

            // Add a link to the record if the data project is not the display project
            $record_id = $one_record[$selectedProj->table_pk];
            $record_link = "<a class='text-primary' href='" . APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=" . trim($config_info["project_id"]) . "&arm=" . $arm_num . "&id=" . $record_id . "'>" . $record_id . "</a>";
            $one_record[$selectedProj->table_pk] = $record_link;

            $formatted_data[] = $one_record;
        }
    }

    $header = extractHeaders($config_info["fields"]);
    $return_data = array("header" => $header,
        "data" => $formatted_data);
    return $return_data;
}


function retrieveDataUsingFile($config_info, $record_id) {

    global $module, $pid;

    $header = array();
    $data = array();
    $title = "";
    $returnData = array();

    // There is a restriction that the function called must be the same name as the file
    $filename = $config_info["file"];
    $classname = "Stanford\\EDT\\" . explode(".", $filename)[0];
    $system_location = $module->getSystemSettings();
    $file_location =  $system_location["datasource_location"]["value"] . "/" . $filename;

    if (file_exists($file_location)) {
        try {
            // Include the file. The file must include a function of the same name. Then call the function to retrieve headers and data.
            require_once ($file_location);

            // Instantiate the class and retrieve the data.
            $newClass = new $classname($pid, $record_id);
            $title = $newClass->getTitle();
            $header = $newClass->getHeader();
            $data = $newClass->getData();

            // Retrieve data for data table
            $returnData["title"] = $title;
            $returnData["header"] = $header;
            $returnData["data"] = $data;

        } catch (Exception $ex) {
            $module->emError("Cannot instantiate class $classname");
        }

    } else {
        $module->emLog("File $filename.php is not found in directory $file_location");
        $header = array("File $filename.php is not found in the correct directory. Please make sure the file exists.");
    }

    return $returnData;
}

function extractHeaders($fields) {

    $header = array();
    // Split the field name from field label
    foreach ($fields as $field) {
        $field_pieces = explode(']', $field);
        $header[] = trim($field_pieces[1]);
    }

    return $header;
}

function getOneDisplay($id, $config_info)
{
    global $module, $record_id;
    $module->emDebug("In getOneDisplay with config info: " . json_encode($config_info));

    // Retrieve the data dictionary in case we need to convert labels and field names
    if (!empty($config_info["project_id"])) {
        $selectedProj = getProjDataDictionary($config_info["project_id"]);
        if (empty($selectedProj)) {
            $module->emError("Cannot retrieve project data dictionary for displays for pid " . $config_info["project_id"]);
        }
    }

    // If this display type is a repeating form, use the repeating form utilities to create the table
    $module->emDebug("Display $id: config info: " . json_encode($config_info));
    switch ($config_info["type"]) {
        case "repeatingForm":

            // Retrieve data and generate display
            $return_data = retrieveDataFromRepeatingForms($selectedProj, $config_info, $record_id);
            $header = $return_data["header"];
            $data = $return_data["data"];
            $display = new CreateDisplay();
            $html = $display->renderTable($id, $header, $data, $config_info["title"]);

            break;
        case "events":
        case "repeatingEvents":

            // Retrieve data and generate display
            $return_data = retrieveDataAcrossEvents($selectedProj, $config_info, $record_id);
            $header = $return_data["header"];
            $data = $return_data["data"];
            $display = new CreateDisplay();
            $html = $display->renderTable($id, $header, $data, $config_info["title"]);

            break;

        case "primary_key":

            $module->emDebug("Selected Proj $selectedProj, Record ID $record_id, Config Info: " . json_encode($config_info));
            // Retrieve data and generate display
            $return_data = retrieveDataUsingPrimaryKey($selectedProj, $config_info, $record_id);
            $header = $return_data["header"];
            $data = $return_data["data"];
            $display = new CreateDisplay();
            $module->emDebug("Header $header, Data: " . json_encode($data));
            $html = $display->renderTable($id, $header, $data, $config_info["title"]);
            $module->emDebug("HTML: " . $html);

            break;
        case "file":

            // Retrieve data and generate display
            $return_data = retrieveDataUsingFile($config_info, $record_id);
            $header = $return_data["header"];
            $data = $return_data["data"];
            $title = $return_data["title"];
            $display = new CreateDisplay();
            $html = $display->renderTable($id, $header, $data, $title);

            break;

        default:
            $module->emLog("Don't understand display type " . $config_info['type']);
            $html = "Display type unknown - can not locate data.";
    }

    return $html;
}

function getFirstNonBlankValue($data_dictionary, $field_name) {
    global $module, $pid, $record_id;

    $field_value = "";

    if ($field_name == $data_dictionary->table_pk) {
        $field_value = $record_id;
    } else {
        $return_data = REDCap::getData($pid, 'array', $record_id, array($field_name));
        foreach ($return_data[$record_id] as $event_num => $event_info) {
            if ($event_num == 'repeat_instances') {
                foreach($event_info as $repeat_id => $repeat_info) {
                    foreach ($repeat_info[""] as $instance_id => $instance_info) {
                        if (!empty($instance_info[$field_name])) {
                            $field_value = getLabel($data_dictionary, $field_name, $instance_info[$field_name]);
                            break 3;
                        }
                    }
                }
            } else {
                if (!empty($event_info[$field_name])) {
                    $field_value = getLabel($data_dictionary, $field_name, $event_info[$field_name]);
                    break;
                }
            }
        }
    }

    return $field_value;

}

function getTitle() {
    global $module, $title, $pid;

    $data_dictionary = new Project($pid);

    // Each field name will be enclosed in [] so loop until there are no more [
    $new_title = "";
    $pos = 0;
    $start_location = strpos($title, '[', $pos);
    if ($start_location == false) {
        $new_title = $title;
    } else {

        $pieces = explode('[', $title);
        foreach ($pieces as $piece => $text) {
            $close_bracket = strpos($text, ']');
            if ($close_bracket == false) {
                $new_title .= $text;
            } else {
                $field_name = substr($text, 0, $close_bracket);
                $value = getFirstNonBlankValue($data_dictionary, $field_name);
                $new_title .= $value;
                $new_title .= substr($text, $close_bracket+1, strlen($text));
            }
        }
    }

    return $new_title;
}


function getAllDisplays() {
    global $displayList, $config_info, $module;

    $html = '<div class="accordion" id="accordionDisplays">';

    // Loop over each desired config
    foreach($displayList as $display) {
        $config = $config_info[$display];
        $config_id = strtolower(str_replace(' ', '_', $display));

        $html .= '<div>';
        $html .=    '<button class="clickable" data-target="'.$config_id.'" data-parent="#accordionDisplays" onclick="toggleButton('."'".$config_id."')" . '">';
        $html .=    $display;
        $html .=    '</button>';
        $html .=    '<div class="collapse" id="'.$config_id.'_collapse" style="display:block;">';
        $html .=        '<div id="space">';
        $html .=        '</div>';
        $html .=        getOneDisplay($config_id, $config);
        $html .=    '</div>';
        $html .= '</div>';

        $html .= '<div></div>';
    }

    $html .= '</div>';
    return $html;
}
