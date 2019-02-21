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
if (empty($record_id)) {
    echo "<h6>The displays are associated with a record. Please select a record and try again!</h6>";
    return;
}

DEFINE(PROJECT_PID, $pid);
$user = USERID;

function getDisplay($config_name)
{
    global $module, $record_id, $pid;

    // Retrieve the saved configurations
    list($config_name_list, $config_field_list, $config_info_list) = getConfigs();

    // Look for our config
    for ($icount = 0; $icount < count($config_name_list); $icount++) {
        if ($config_name_list[$icount] == $config_name) {
            $config_info = $config_info_list[$icount];
            break;
        }
    }

    $module->emLog("Config name: " . $config_name . ", and info: " . json_encode($config_info));

    if (empty($config_info)) {
        return;
    }

    // Retrieve the data dictionary in case we need to convert labels and field names
    $display = new CreateDisplay();
    $selectedProj = getProjDataDictionary($config_info["project_id"]);
    if (empty($selectedProj)) {
        $module->emError("Cannot retrieve project data dictionary for displays for pid " . $config_info["project_id"]);
    }

    // Figure out the arm number
    foreach($selectedProj->eventInfo as $eventId => $eventInfo) {
        if ($eventInfo["arm_id"] == trim($config_info["arm"])) {
            $arm_num = $eventInfo["arm_num"];
            break;
        }
    }

    // If this display type is a repeating form, use the repeating form utilities to create the table
    switch ($config_info["type"]) {
        case "repeatingForm":

            $module->emLog("In repeatingForm");

            // First do a query to see which record(s) in the data project fit our filter
            $filter = "[" . $config_info['key_field'] . "] = '$record_id'";
            $recordList = REDCap::getData($config_info["project_id"], 'array', null, array_keys($config_info["fields"]),
                            $config_info["event"], null, null, null, null, $filter);

            $repeating_form = new RepeatingFormsExt($config_info["project_id"], $config_info["form"]);

            // For the display add a link to the record so the user can go directly there from the display
            $displayData = array();
            foreach($recordList as $recordNum => $recordData) {
                $data = $repeating_form->getAllInstancesFlat($recordNum, array_keys($config_info["fields"]), $config_info['event']);
                foreach ($data as $one_record => $record_info) {
                    $one_record = $record_info;

                    // Replace the instance ID with the link to the record
                    if ($config_info["project_id"] != $pid) {
                        $record_link = "<a class='text-primary' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=" . trim($config_info["project_id"]) . "&page=" . $config_info['form'] . "&id=" . $recordNum . "&event_id=" . $config_info["event"] . "&instance=" . $record_info['instance'] . "'>$recordNum-" . $record_info['instance'] . "</a>";
                        $one_record["instance"] = $record_link;
                    }

                    $displayData[] = $one_record;
                }
            }

            // Generate the display
            $html = $display->renderTable($selectedProj, $config_info["fields"], $displayData);
            break;
        case "events":

            $module->emLog("In events");

            // Retrieve data for the display
            if (empty($config_info['key_field'])) {
                $records = array($record_id);
            } else {
                // First find the records that meet our criteria
                // We need to do this in 2 steps because Redcap::getData is weird where it only looks at the events that have the filter and
                // does not give me back the events with data.
                $filter = "[" . $config_info['key_field'] . "] = '$record_id'";
                $data = REDCap::getData($config_info["project_id"], 'array', null, array_keys($config_info["fields"]), null,
                    null, null, null, null, $filter);

                $records = array();
                foreach($data as $recordId => $recordInfo) {
                    $records[] = $recordId;
                }
            }

            // Now that we have the list of records we want to retrieve, get the fields
            $data = REDCap::getData($config_info["project_id"], 'array', $records, array_keys($config_info["fields"]));

            // Only display rows which have a value.  If the linking key is in a different event than the other data, there will be
            // records for events which have data even though these fields do not belong to them.
            $displayData = array();
            foreach ($data as $record => $recordInfo) {
                foreach ($recordInfo as $eventId => $eventInfo) {
                    $fieldNotNullCount = 0;
                    $fieldArray = array();

                    $fieldArray[""] = $selectedProj->eventInfo[$eventId]["name"];
                    foreach ($config_info["fields"] as $fieldname => $fieldlabel) {
                        $fieldArray[$fieldname] = $eventInfo[$fieldname];
                        if (!empty($fieldArray[$fieldname])) $fieldNotNullCount++;
                    }

                    // Add a link to the record if the data project is not the display project
                    if ($selectedProj->project_id != $pid) {
                        $record_link = "<a class='text-primary' href='" . APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=" . trim($config_info["project_id"]) . "&arm=" . $arm_num . "&id=" . $record . "'>" . $record . "</a>";
                        $fieldArray[$selectedProj->table_pk] = $record_link;
                    }

                    // We found some data that was not null so save it for display
                    if ($fieldNotNullCount > 1) {
                        $displayData[] = $fieldArray;
                    }
                }
            }

            // Add the event to the header so the user knows where the data is from and create display
            $header = array_merge(array("Event"), $config_info["fields"]);
            $html = $display->renderTable($selectedProj, $header, $displayData);

            return $html;
            break;

        case "primary_key":

            $module->emLog("In primary_key");

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
                        $one_record[$field_name] = $record[$field_name];
                    }

                    // Add a link to the record if the data project is not the display project
                    $record_id = $one_record[$selectedProj->table_pk];
                    $record_link = "<a class='text-primary' href='" . APP_PATH_WEBROOT . "DataEntry/record_home.php?pid=" . trim($config_info["project_id"]) . "&arm=" . $arm_num . "&id=" . $record_id . "'>" . $record_id . "</a>";
                    $one_record[$selectedProj->table_pk] = $record_link;

                    $formatted_data[] = $one_record;
                }
            }

            // Create the html for the display
            $html = $display->renderTable($selectedProj, $config_info["fields"], $formatted_data);

            break;
        default:
            $module->emLog("Don't understand display type " . $config_info['type']);
            $html = "No display - in default";
    }

    return $html;
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
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css">
        <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

        <script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js"></script>

    </head>
    <body>

        <h3>Configured Data Table Displays</h3>
        <div>
            This is the Display Page for configured tables in your project.
        </div>
        <br>

        <div class="container">

            <div class="accordion" id="accordionDisplays">

                <?php list($configNames, $config_fields, $config_info) = getConfigs();
                    $ncount = 0;
                    foreach($configNames as $config) {
                        $ncount++;
                ?>

                <div>
                    <button class="clickable"  data-target="#collapse_<?php echo $ncount; ?>" data-parent="#accordionDisplays" onclick="toggleButton('collapse_<?php echo $ncount; ?>')">
                        <?php echo $config; ?>
                    </button>
                    <div class="collapse" id="collapse_<?php echo $ncount; ?>" style="display:none;">
                        <?php echo getDisplay($config); ?>
                    </div>
                </div>

                <div></div>

                <?php
                    }
                ?>

            </div>

        </div>  <!-- END CONTAINER -->
    </body>
</html>

<script>

function toggleButton(buttonName) {
    var display = document.getElementById(buttonName);
    if (display.style.display === 'none') {
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}

</script>