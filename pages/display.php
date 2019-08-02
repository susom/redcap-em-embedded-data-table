<?php
namespace Stanford\EDT;
/** @var \Stanford\EDT\EDT $module */


use \REDCap;
use \Project;

require_once ($module->getModulePath() . "classes/CreateDisplay.php");
require_once ($module->getModulePath() . "classes/RepeatingFormsExt.php");
require_once ($module->getModulePath() . "classes/Utilities.php");

// This needs to be after the api checks otherwise it gets added to the return data
require APP_PATH_DOCROOT . "ProjectGeneral/header.php";

$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
$record_id = isset($_GET['record']) && !empty($_GET['record']) ? $_GET['record'] : null;
$displays = isset($_GET['displays']) && !empty($_GET['displays']) ? $_GET['displays'] : null;
$title = isset($_GET['title']) && !empty($_GET['title']) ? $_GET['title'] : null;

DEFINE(PROJECT_PID, $pid);
$user = USERID;

if (empty($pid)) {
    echo "<h6>The displays are associated with a project.  Please enter a project and try again!</h6>";
    return;
}
if (empty($record_id)) {
    echo "<h6>The displays are associated with a record. Please select a record and try again!</h6>";
    return;
}

// Retrieve a list of all setups saved
list($configNames, $config_info) = getConfigs();

// If a display list was no given via GET, display all displays
if (empty($displays)) {
    $displayList = $configNames;
} else {
    $displayList = explode(',', $displays);
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

function getOneDisplay($id, $config_info)
{
    global $module, $record_id;

    // Retrieve the data dictionary in case we need to convert labels and field names
    if (!empty($config_info["project_id"])) {
        $selectedProj = getProjDataDictionary($config_info["project_id"]);
        if (empty($selectedProj)) {
            $module->emError("Cannot retrieve project data dictionary for displays for pid " . $config_info["project_id"]);
        }
    }

    // If this display type is a repeating form, use the repeating form utilities to create the table
    $module->emLog("Display $id");
    //$module->emLog("Display $id: config info: " . json_encode($config_info));
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

            // Retrieve data and generate display
            $return_data = retrieveDataUsingPrimaryKey($selectedProj, $config_info, $record_id);
            $header = $return_data["header"];
            $data = $return_data["data"];
            $display = new CreateDisplay();
            $html = $display->renderTable($id, $header, $data, $config_info["title"]);

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

function retrieveDataFromRepeatingForms($selectedProj, $config_info, $record_id) {

    global $module;

    // First do a query to see which record(s) in the data project fit our filter
    if (empty($config_info['key_field'])) {
        $recordList = array($record_id => $record_id);
    } else {
        $filter = "[" . $config_info['key_field'] . "] = '$record_id'";
        $recordList = REDCap::getData($config_info["project_id"], 'array', null, array_keys($config_info["fields"]),
            $config_info["event"], null, null, null, null, $filter);
    }

    // See if we are retrieving data from an event
    if (is_numeric($config_info["event"])) {
        $eventList = $config_info["event"];
    } else {
        $eventList = null;
    }

    // Instantiate the class to retrieve repeating form data
    $repeating_form = new RepeatingFormsExt($config_info["project_id"], $config_info["form"], $eventList);

    // For the display add a link to the record so the user can go directly there from the display
    $displayData = array();
    foreach($recordList as $recordNum => $recordData) {

        // Retrieve the data
        $data = $repeating_form->getAllInstancesFlat($recordNum, array_keys($config_info["fields"]), $config_info['event']);
        foreach ($data as $one_row => $record_info) {

            // For each row, add the record/instance of this data first
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
    }

    // Add the record/instance column to the header and take out the primary key if it was added  separately
    $header = extractHeaders($config_info["fields"]);
    $displayHeader = array_merge(array("Instance"), $header);
    unset($displayHeader[$selectedProj->table_pk]);
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
                foreach($eventInfo as $repeatEventId => $repeatEventInfo) {
                    foreach($repeatEventInfo as $formName => $eventData) {

                        foreach ($eventData as $instanceId => $instanceInfo) {

                            $first_form = $Proj->eventsForms[$repeatEventId][0];

                            // Add a link to the record/instance
                            $record_link = "<a class='text-primary' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . trim($config_info["project_id"]) .
                                "&page=" . $first_form .
                                "&event_id=" . $repeatEventId . "&id=" . $record .  "&instance=" . $instanceId ."'>" . $record . "-" . $instanceId . "</a>";
                            $fieldArray[$selectedProj->table_pk] = $record_link;

                            // Add the rest of the requested fields
                            foreach ($config_info["fields"] as $fieldname => $fieldlabel) {
                                $fieldArray[$fieldname] = getLabel($selectedProj, $fieldname, $instanceInfo[$fieldname]);
                                if (!empty($fieldArray[$fieldname])) $fieldNotNullCount++;
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
                $record_link = "<a class='text-primary' href='" . APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=".trim($config_info["project_id"]).
                    "&arm=".$arm_num."&event=".$eventId."&id=".$record."'>" . $record . "</a>";
                $fieldArray[$selectedProj->table_pk] = $record_link;

                foreach ($config_info["fields"] as $fieldname => $fieldlabel) {
                    $fieldArray[$fieldname] = getLabel($selectedProj, $fieldname, $eventInfo[$fieldname]);
                    if (!empty($fieldArray[$fieldname])) $fieldNotNullCount++;
                }

                // We found some data that was not null so save it for display
                if ($fieldNotNullCount > 0) {
                    $displayData[] = $fieldArray;
                }
            }
        }
    }

    // Add the event to the header so the user knows where the data is from and create display
    $header = extractHeaders($config_info["fields"]);
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

    // There is a restriction that the function called must be the same name as the file
    $filename = $config_info["file"];
    //$functionname = explode(".", $filename)[0];
    $classname = "Stanford\\EDT\\" . explode(".", $filename)[0];
    $system_location = $module->getSystemSettings();
    $file_location =  $system_location["datasource_location"]["value"] . "/" . $filename;

    if (file_exists($file_location)) {

        // Include the file. The file must include a function of the same name. Then call the function to retrieve headers and data.
        require_once($file_location);

        $newClass = new $classname($pid, $record_id);
        $title = $newClass->getTitle();
        $header = $newClass->getHeader();
        $data = $newClass->getData();

    } else {
        $module->emLog("File $filename.php is not found in directory $file_location");
        $header = array("File $filename.php is not found in the correct directory. Please make sure the file exists.");
    }

    $returnData = array();
    $returnData["title"] = $title;
    $returnData["header"] = $header;
    $returnData["data"] = $data;

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

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <title>Display table</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">

        <link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("pages/EDT.css") ?>" />
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.css"/>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.18/b-1.5.4/b-html5-1.5.4/b-print-1.5.4/datatables.min.js"></script>


        <script type="text/javascript" src="https://code.jquery.com/jquery-3.3.1.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.2/js/dataTables.buttons.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.flash.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.html5.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/buttons/1.5.2/js/buttons.print.min.js"></script>

    </head>
    <body>
        <div class="container">
            <button id="print_button" class="btn btn-primary pull-right hidden-print" onclick="window.print();"><span class="glyphicon glyphicon-print" aria-hidden="true"></span> Print</button>
            <br>
            <h3 style="text-align: center"><?php echo getTitle(); ?></h3>

            <?php echo getAllDisplays(); ?>

        </div>  <!-- END CONTAINER -->
    </body>
</html>

<script>

function toggleButton(buttonName) {
    var display = document.getElementById(buttonName + '_collapse');
    if (display.style.display === 'none') {
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}

$(document).ready(function() {

    var tables = document.getElementsByClassName("table");
    for (var ncnt = 0; ncnt < tables.length; ncnt++) {
        var tableElement = $('#' + tables[ncnt].id);

        tableElement.DataTable({
            "lengthMenu": [ [-1, 10, 25, 50], ["All",10, 25, 50] ],
            dom: 'Bftlp',
            buttons: {
                name: 'primary',
                buttons: ['copy', 'excel', 'pdf',
                    {
                        extend: 'print',
                        customize: function (win) {
                            $(win.document.body)
                                .css('font-size', '12pt');
                            $(win.document.body).find('table')
                                .addClass('compact')
                                .css('font-size', 'inherit');
                        }
                    }
                ]
            }
        });

        $(".dt-buttons").css("left", 30);
        $(".dt-buttons").addClass('hidden-print');
        $(".dataTables_filter").addClass('hidden-print');
        $(".dataTables_length").addClass('hidden-print');
    }
});

</script>