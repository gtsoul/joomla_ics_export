<?php

defined('_JEXEC') or die;

require_once("icalendar-master/zapcallib.php");

/**
 * iCagenda export.
 */
class iCagendaExport
{
	// Validator : http://ical-validator.herokuapp.com
    /*
        In /site/administrator/components/com_icagenda/icagenda.php L163
        JLoader::register('iCagendaExport', dirname(__FILE__) . '/helpers/icagenda_custom.php');
        In /site/administrator/components/com_icagenda/views/events/tmpl/default.php L81
        // Launch the ics Export
        iCagendaExport::generateStaticEventIcs();
        Copy icalendar-master and this in /site/administrator/components/com_icagenda/helpers/icagenda_custom.php
    */
	
	// Proprietes a rendre parametrable dans le plugin
	private static function getEventPageUri() 
	{
		return "index.php/calendrier/agenda-a-venir/";
	}
	
	private static function getNbMaxEvents()
	{
		return 100;
	}
	
	// OutputFile
	private static function getOutputFile()
	{
		return JPATH_ROOT."/../cey_events.ics";
	}

    public static function generateStaticEventIcs() 
    {
        // TODO : mettre un timer
        $db_events = self::loadEventsFromDb();
		$db_cats = self::loadCategoriesFromDb();
        $icalobj = new ZCiCal();
		// event timesteamp format : DTSTAMP:20190105T113249Z
		$dtstamp =  gmdate("Ymd\THis\Z");
		
        foreach ($db_events as $db_event){
            self::addEventNode($icalobj, $db_event, $db_cats, $dtstamp); 
        }

        $ics = $icalobj->export();
		
		file_put_contents(self::getOutputFile(), $ics);

        die($ics);
    }

    private static function addEventNode($icalobj, $db_event, $db_cats, $dtstamp) 
    {
        $uri = JURI::root() . self::getEventPageUri() . $db_event->id . "-" . $db_event->alias; // TODO : remove $dtstamp ?
        $uid = "evenement_cey_" . $db_event->id . "@excaliburyvelines.fr";

        $start = $db_event->startdate;
        $end = $db_event->enddate;

        if($start == "0000-00-00 00:00:00") {
            $start = $db_event->next;
        }
        if($end == "0000-00-00 00:00:00") {
            $end = $start;
        }
		$title = substr($db_event->title, 0, 55);

        $eventobj = new ZCiCalNode("VEVENT", $icalobj->curnode);
		
		self::setStartAndEndDate($eventobj, $db_event);
		
		$eventobj->addNode(new ZCiCalDataNode("DTSTAMP:" . $dtstamp));
		$eventobj->addNode(new ZCiCalDataNode("ORGANIZER:" . "excalibur.yvelines@gmail.com"));
        $eventobj->addNode(new ZCiCalDataNode("UID:" . $uid));
		//$eventobj->addNode(new ZCiCalDataNode("ATTENDEE:" . "excalibur.yvelines@gmail.com"));
		$eventobj->addNode(new ZCiCalDataNode("CREATED:" . ZCiCal::fromSqlDateTime($db_event->created)));
		$eventobj->addNode(new ZCiCalDataNode("LAST-MODIFIED:" . ZCiCal::fromSqlDateTime($db_event->modified)));
		$eventobj->addNode(new ZCiCalDataNode("DESCRIPTION:" . ZCiCal::formatContent(
			$db_event->shortdesc . ' | ' .
			"Plus d'infos sur " . $uri)));
        $eventobj->addNode(new ZCiCalDataNode("LOCATION:" . ZCiCal::formatContent($db_event->address)));
        $eventobj->addNode(new ZCiCalDataNode("STATUS:" . "CONFIRMED"));
        $eventobj->addNode(new ZCiCalDataNode("SUMMARY:" . ZCiCal::formatContent($title)));
		$eventobj->addNode(new ZCiCalDataNode("TRANSP:OPAQUE"));
        $eventobj->addNode(new ZCiCalDataNode("CATEGORIES:" . $db_cats[$db_event->catid]));
    }
	
	//ZDateHelper::fromiCaltoUnixDateTime(
	private static function setStartAndEndDate($eventobj, $db_event)
	{
		$start = ZCiCal::fromSqlDateTime($db_event->startdate);
		$end = ZCiCal::fromSqlDateTime($db_event->enddate);
		
        if($start == "00000000T000000Z") {
            $start = ZCiCal::fromSqlDateTime($db_event->next);
        }
		if($end == "00000000T000000Z") {
            $end = $start;
        }
		$dayStart = substr( $start, 0, 8);
		$dayEnd = substr($end, 0, 8);		
		if ($dayStart != $dayEnd || true) { // Multi-day event
			// Full day(s) event, format DTSTART;VALUE=DATE:20190127
			$dayEnd = (int)$dayEnd+1; // Weird ical norm
			$eventobj->addNode(new ZCiCalDataNode("DTSTART;VALUE=DATE:" . $dayStart));
			$eventobj->addNode(new ZCiCalDataNode("DTEND;VALUE=DATE:" . $dayEnd));
		}/* else { // TODO : manage timezones			
			$timeStart = substr($start, 9, 6);
			$timeEnd = substr($end, 9, 6);
			// Avoid events with no duration => default time
			if (($timeStart == "000000" AND $timeEnd == "000000") || $timeStart == $timeEnd) {
				$timeStart = "203000";
				$timeEnd = "223000";
			}		
			// On day event with datetime, format DTSTART:20190207T130000Z
			$eventobj->addNode(new ZCiCalDataNode("DTSTART:" . $dayStart . "T" . $timeStart . "Z"));
			$eventobj->addNode(new ZCiCalDataNode("DTEND:" . $dayEnd . "T" . $timeEnd . "Z"));
		}*/
	}
	

    private static function loadEventsFromDb() 
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        //SELECT `id`, `title`, `alias`, `image`, `shortdesc`, `address`, `startdate`, `enddate`, `next` FROM `fs48q_icagenda_events` WHERE `state` = 1 ORDER BY `startdate` ASC LIMIT 1000
        $query->select($db->quoteName(array('id', 'title', 'alias', 'image', 'shortdesc', 'address', 'startdate', 'enddate', 'next', 'created', 'modified', 'catid')));
        $query->from($db->quoteName('#__icagenda_events'));
        $query->where($db->quoteName('state') . ' = 1');
        $query->order('next DESC');
        $query->setLimit(self::getNbMaxEvents());
        $db->setQuery($query);
        $results = $db->loadObjectList();
        //print_r($results);
        return $results;
    }
	
	private static function loadCategoriesFromDb() 
    {
		$categories;
		
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        //SELECT `id`, `title` FROM `fs48q_icagenda_category` WHERE 1
        $query->select($db->quoteName(array('id', 'title')));
        $query->from($db->quoteName('#__icagenda_category'));
        $db->setQuery($query);
        $db_cats = $db->loadObjectList();	
		
		foreach ($db_cats as $db_cat){			
			$categories[$db_cat->id] = $db_cat->title;
		}
		
		return $categories;
	}
}
