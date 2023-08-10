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

$module->projectId = $pid;
//DEFINE(PROJECT_PID, $pid);
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

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <title>Display table</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">

        <link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("pages/EDT.css"); ?>" />
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
            <br>

<!--      Add filter  -->
            <table class="display compact" style="width:100%; margin-bottom: 30px; font-size: small;">
                <thead>
                    <td colspan="6">
                        <h6 style="text-align:center">Filter the Missed/Partial Doses, Allergic Reactions and Health Changes table by dates</h6>
                    </td>
                </thead>
                <tr>
                    <td class="col" style="width:27%">
                    </td>
                    <td class="col" style="width:15%">
                        <label for="start"><b>Start Date</b></label>
                        <input id="start_date" type="date">
                    </td>
                    <td class="col" style="width:15%">
                        <label for="end"><b>End Date</b></label>
                        <input id="end_date" type="date" size="10">
                    </td>
                    <td class="col" style="margin-right: 1px; width: 6%; vertical-align: bottom">
                        <button class="btn-sm btn-secondary" id="filter">Filter</button>
                    </td>
                    <td class="col" style="width:20%; vertical-align: bottom">
                        <button class="btn-sm btn-secondary" id="clearFilter">Clear Filter</button>
                    </td>
                    <td class="col" style="width:27%; vertical-align: bottom">
                    </td>
                </tr>
            </table>
<!--      end Filter    -->

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
            //dom: 'Bftlp',
            dom: 'ftlp'
        });
    }
});

$('#filter').on('click', function(e){
    e.preventDefault();
    var startDate = $('#start_date').val(),
        endDate = $('#end_date').val();

    var missedDoses = $('#missed_or_partial_doses');
    filterByDate(startDate, endDate); // We call our filter function
    missedDoses.dataTable().fnDraw(); // Manually redraw the table after filtering

    var allergicReactions = $('#allergic_reactions');
    filterByDate(startDate, endDate); // We call our filter function
    allergicReactions.dataTable().fnDraw(); // Manually redraw the table after filtering

    var healthChanges = $('#health_changes');
    filterByDate(startDate, endDate); // We call our filter function
    healthChanges.dataTable().fnDraw(); // Manually redraw the table after filtering

});

// Clear the filter. Unlike normal filters in Datatables,
// custom filters need to be removed from the afnFiltering array.
$('#clearFilter').on('click', function(e){
    $('#start_date').val("");
    $('#end_date').val("");
    $.fn.dataTableExt.afnFiltering.length = 0;
    $('#missed_or_partial_doses').dataTable().fnDraw();
    $('#allergic_reactions').dataTable().fnDraw();
    $('#health_changes').dataTable().fnDraw();
});

/* Our main filter function
 * We pass the column location, the start date, and the end date
 */
var filterByDate = function(startDate, endDate) {
    // Custom filter syntax requires pushing the new filter to the global filter array
    $.fn.dataTableExt.afnFiltering.push(
        function( oSettings, aData, iDataIndex ) {

            if (oSettings.nTable.id === "missed_or_partial_doses") {
                column = 2;
            } else if (oSettings.nTable.id === "allergic_reactions") {
                column = 3;
            } else if (oSettings.nTable.id === "health_changes") {
                column = 2;
            }

            //console.log("In filterByDate: table name: " + oSettings.nTable.id);
            var rowDate = normalizeDate(aData[column]),
                start = normalizeDate(startDate),
                end = normalizeDate(endDate);
            //console.log("RowDate: " + rowDate + ", start: " + start + ", end: " + end + ", non-normalized: " + aData[column] + ", column: " + column);

            // If our date from the row is between the start and end
            if (start <= rowDate && rowDate <= end) {
                return true;
            } else if (rowDate >= start && end === '' && start !== ''){
                return true;
            } else if (rowDate <= end && start === '' && end !== ''){
                return true;
            } else {
                return false;
            }
        }
    );
};

// converts date strings to a Date object, then normalized into a YYYYMMMDD format (ex: 20131220). Makes comparing dates easier. ex: 20131220 > 20121220
var normalizeDate = function(dateString) {
    var date = new Date(dateString);
    var normalized = date.getFullYear() + '' + (("0" + (date.getMonth() + 1)).slice(-2)) + '' + ("0" + date.getDate()).slice(-2);
    return normalized;
}

</script>