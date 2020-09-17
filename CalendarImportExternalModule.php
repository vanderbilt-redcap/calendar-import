<?php

namespace Vanderbilt\CalendarImportExternalModule;

use ExternalModules\ExternalModules;
use ExternalModules\AbstractExternalModule;

class CalendarImportExternalModule extends \ExternalModules\AbstractExternalModule
{
    function getAllProjectsForUser() {
        $returnProjects = array();
        $sql ="SELECT CAST(p.project_id as char) as project_id, p.app_title
					FROM redcap_projects p, redcap_user_rights u
					WHERE p.project_id = u.project_id
						AND u.username = ?";

        if(!ExternalModules::isSuperUser()){
            $sql .= " AND u.design = 1";
        }

        $result = ExternalModules::query($sql, USERID);
        while($row = $result->fetch_assoc()) {
            $projectName = fixUTF8($row["app_title"]);

            // Required to display things like single quotes correctly
            $projectName = htmlspecialchars_decode($projectName, ENT_QUOTES);

            $returnProjects[$row['project_id']] = $row['app_title'];
        }
        return $returnProjects;
    }

    function getCalendarData($projectID) {
        $calendar = array();
        if ($projectID != "" && is_numeric($projectID)) {
            /*
             * The columns for the redcap_events_calendar are the following: cal_id,record,project_id,event_id,baselin_date,group_id,event_date,event_time,event_status,note_type,notes,extra_notes
             */
            $result = $this->query("select c.* from redcap_events_metadata m right outer join redcap_events_calendar c on c.event_id = m.event_id
                where c.project_id = ?",array($projectID));
            while ($row = $result->fetch_assoc()) {
                $calendar[] = $row;
            }
        }
        return $calendar;
    }

    function mapDAGs($sourceDAGs, $currentDAGs) {
        $dags = array();
        foreach ($sourceDAGs as $srcID => $srcName) {
            if (in_array($srcName,$currentDAGs)) {
                $currentID = array_search($srcName,$currentDAGs);
                $dags[$srcID] = $currentID;
            }
        }
        return $dags;
    }

    function mapEvents($sourceEvents, $currentEvents) {
        $events = array();
        foreach ($sourceEvents as $arm => $armData) {
            foreach ($armData['events'] as $eventID => $eventData) {
                foreach ($currentEvents as $currentArm => $currentArmData) {
                    foreach ($currentArmData['events'] as $currentEventID => $currentEventData) {
                        if ($armData['name'] == $currentArmData['name'] && $eventData['descrip'] == $currentEventData['descrip']) {
                            $events[$eventID] = $currentEventID;
                        }
                    }
                }
            }
        }
        return $events;
    }

    function mapCalendarData($calendarData,$mappedEvents,$mappedDAGs,$existingRecords,$currentProject) {
        $newCalendar = array();
        foreach ($calendarData as $calID => $calData) {
            if (in_array($calData['record'],array_keys($existingRecords)) && ($calData['group_id'] == "" || isset($mappedDAGs[$calData['group_id']])) && ($calData['event_id'] == "" || isset($mappedEvents[$calData['event_id']]))) {
                $newCalendar[$calID]['record'] = $this->blankToNull($calData['record']);
                $newCalendar[$calID]['project_id'] = $this->blankToNull($currentProject);
                $newCalendar[$calID]['event_id'] = $this->blankToNull($mappedEvents[$calData['event_id']]);
                $newCalendar[$calID]['baseline_date'] = $this->blankToNull($calData['baseline_date']);
                $newCalendar[$calID]['group_id'] = $this->blankToNull($mappedDAGs[$calData['group_id']]);
                $newCalendar[$calID]['event_date'] = $this->blankToNull($calData['event_date']);
                $newCalendar[$calID]['event_time'] = $this->blankToNull($calData['event_time']);
                $newCalendar[$calID]['event_status'] = $this->blankToNull($calData['event_status']);
                $newCalendar[$calID]['note_type'] = $this->blankToNull($calData['note_type']);
                $newCalendar[$calID]['notes'] = $this->blankToNull($calData['notes']);
                $newCalendar[$calID]['extra_notes'] = $this->blankToNull($calData['extra_notes']);
            }
        }

        return $newCalendar;
    }

    function blankToNull($value) {
        return ($value != "" ? $value : null);
    }
}