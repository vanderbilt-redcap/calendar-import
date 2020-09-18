<?php
include_once('base.php');
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/css/select2.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/js/select2.min.js"></script>

<?php

$module = new \Vanderbilt\CalendarImportExternalModule\CalendarImportExternalModule();
$projectsForUser = $module->getAllProjectsForUser();
$selectedProject = $_POST['project_select'];
$resultString = "";

if ($selectedProject != "" && is_numeric($selectedProject)) {
    $thisProject = new Project($_GET['pid']);
    $records = \REDCap::getData($_GET['pid'],'array',array(),$thisProject->table_pk);

    $project = new Project($selectedProject);
    $calendarData = $module->getCalendarData($selectedProject);
    $recordList = array_keys($records);

    $mappedEvents = $module->mapEvents($project->events,$thisProject->events);
    $mappedDAGs = $module->mapDAGs($project->getUniqueGroupNames(),$thisProject->getUniqueGroupNames());

    $mappedCalendar = $module->mapCalendarData($calendarData,$mappedEvents,$mappedDAGs,array_keys($records),$thisProject->project_id);

    if ($thisProject->project_id == 7473) {
        $module->query("DELETE FROM redcap_events_calendar WHERE project_id=7473 AND (group_id NOT IN ('6329','3295') OR group_id IS NULL)");
    }
    $query = "";
    $totalImports = 0;
    foreach ($mappedCalendar as $calendarArray) {
        $columnToValue = "";
        foreach ($calendarArray as $cKey => $cValue) {
            if ($columnToValue != "") {
                $columnToValue .= " AND ";
            }
            $columnToValue .= $cKey.($cValue != "" ? " = '".db_real_escape_string($cValue)."'" : " IS NULL");
        }
        $selectQuery = "SELECT COUNT(cal_id) as count FROM redcap_events_calendar WHERE $columnToValue";
        $result = $module->query($selectQuery);
        $countRow = $result->fetch_assoc();

        if ($countRow['count'] == 0) {
            $fieldString = implode(",", array_keys($calendarArray));
            $valueString = "'" . implode("','", $calendarArray) . "'";
            $query = "INSERT INTO redcap_events_calendar ($fieldString) VALUES (";
            $phIndex = 0;
            foreach ($calendarArray as $ph) {
                if ($phIndex > 0) {
                    $query .= ",";
                }
                $query .= "?";
                $phIndex++;
            }
            $query .= ")";
            if ($query != "") {
                $result = $module->query($query, $calendarArray);
                $totalImports++;
            }
        }
    }
    $resultString = "<p style='font-weight:bold;'>Completed importing $totalImports calendar events from ".$projectsForUser[$selectedProject]."</p>";
}
?>
<form method="POST" action="<?php $module->getUrl('import_calendar.php') ?>" onsubmit='return confirm("Are you sure you want to import from project \""+document.getElementById("project_select").options[document.getElementById("project_select").selectedIndex].text+"\"?");'>
    <h2>Select Project With Calendar to Import</h2>
    <select class="select2-drop" id="project_select" name="project_select" style="text-overflow: ellipsis;">
        <?php
        foreach ($projectsForUser as $projectID => $projectName) {
            echo "<option value='$projectID' ".($selectedProject != "" && $selectedProject == $projectID ? "selected" : "").">$projectName</option>";
        }
        ?>
    </select><br/>
    <input style="margin-top:5px;" type="submit" value="Import Calendar" /><br/>
    <?php echo $resultString; ?>
</form>
<script>
    $(document).ready(function (){
        $('.select2-drop').select2();
    });
</script>
