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
		return 300;
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
    }

    private static function addEventNode($icalobj, $db_event, $db_cats, $dtstamp) 
    {
        $uri = JURI::root() . self::getEventPageUri() . $db_event->id . "-" . $db_event->alias; // TODO : remove $dtstamp ?

        $start = $db_event->startdate;
        $end = $db_event->enddate;

        if($start == "0000-00-00 00:00:00") {
            $start = $db_event->next;
        }
        if($end == "0000-00-00 00:00:00") {
            $end = $start;
        }
		$title = substr($db_event->title, 0, 55);

		$event_dts = self::getStartAndEndDates($eventobj, $db_event);
		$i = 1;
		
		foreach ($event_dts as $event_dt){
			$uid = "evenement_cey_" . $db_event->id . "-" . $i . "contact@excaliburyvelines.fr";
			$eventobj = new ZCiCalNode("VEVENT", $icalobj->curnode);
					
			$eventobj->addNode(new ZCiCalDataNode("DTSTART;" . $event_dt->dtStart));
			$eventobj->addNode(new ZCiCalDataNode("DTEND;" . $event_dt->dtEnd));
			
			$eventobj->addNode(new ZCiCalDataNode("DTSTAMP:" . $dtstamp));
			$eventobj->addNode(new ZCiCalDataNode("ORGANIZER:" . "contact@excaliburyvelines.fr"));
			$eventobj->addNode(new ZCiCalDataNode("UID:" . $uid));
			//$eventobj->addNode(new ZCiCalDataNode("ATTENDEE:" . "contact@excaliburyvelines.fr"));
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
			$i++;
		}		
    }
	
	//ZDateHelper::fromiCaltoUnixDateTime(
	private static function getStartAndEndDates($eventobj, $db_event)
	{
		$dates = [];
		$start = ZCiCal::fromSqlDateTime($db_event->startdate);
		$end = ZCiCal::fromSqlDateTime($db_event->enddate);

		// Test sur : "s:<nb_events>:..."
		if (substr($db_event->dates, 2, 1) > 1 || substr($db_event->dates, 2, 1) != ":") { // Nb d'occurences superieur a 1 ou sur 2 chiffres
			// Repeating events on several days
			// On recupere les chaines, s:16:"2019-05-06 20:30"
			preg_match_all('/s\:16\:"([^"]+)";/', $db_event->dates, $str_dates);
			foreach($str_dates[1] as $str_date) {
				// Full day(s) event, format DTSTART;VALUE=DATE:20190127
				$occurence = null;
				$dayStart = preg_replace('/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2})\:([0-9]{2})/', '$1$2$3', $str_date);
				$dayEnd = (int)$dayStart+1; // Weird ical norm
				$occurence->dtStart = "VALUE=DATE:" . $dayStart;
				$occurence->dtEnd = "VALUE=DATE:" . $dayEnd;
				array_push($dates, $occurence);				
			}			
		} else { 	
			// Multi-day event and singletime event
			// On multi-day, info is stored on startdate/enddate
			if($start == "00000000T000000Z") {
				// On single date, info is stored on next
				$start = ZCiCal::fromSqlDateTime($db_event->next);
			}
			if($end == "00000000T000000Z") {
				$end = $start;
			}
			$dayStart = substr( $start, 0, 8);
			$dayEnd = substr($end, 0, 8);		
			// Full day(s) event, format DTSTART;VALUE=DATE:20190127
			$dayEnd = (int)$dayEnd+1; // Weird ical norm
			$date->dtStart = "VALUE=DATE:" . $dayStart;
			$date->dtEnd = "VALUE=DATE:" . $dayEnd;
			array_push($dates, $date);
		}
		// TODO : manage timezones
		// On day event with datetime, format DTSTART:20190207T130000Z
		/*$dtStart = $dayStart . "T" . $timeStart . "Z";
		$dtEnd = $dayEnd . "T" . $timeEnd . "Z";		*/
		return $dates;
	}
	

    private static function loadEventsFromDb() 
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        //SELECT `id`, `title`, `alias`, `image`, `shortdesc`, `address`, `startdate`, `enddate`, `next`, `dates` FROM `fs48q_icagenda_events` WHERE `state` = 1 ORDER BY `next` DESC LIMIT 100
        $query->select($db->quoteName(array('id', 'title', 'alias', 'image', 'shortdesc', 'address', 'startdate', 'enddate', 'dates', 'next', 'created', 'modified', 'catid')));
        $query->from($db->quoteName('#__icagenda_events'));
        $query->where($db->quoteName('state') . ' = 1'); // evenements publies
		$query->andWhere($db->quoteName(catid) . ' != 7'); // salle fermee => pas dans calendrier
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
