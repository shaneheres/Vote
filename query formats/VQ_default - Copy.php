<?php
class VQ_default
{
	public $UserID = 0;
	public $PageID = 0;
	public $AttributeID = 0;
	public $AttributeName = "";
	public $Style = "";
	
	public function __construct($page, $attribute) {
		global $wgUser, $wgVoteroAttributes;
		
		$this->UserID = $wgUser->getID();
		$this->PageID = $page;
		
		$this->AttributeID = $wgVoteroAttributes[$attribute]['id'];
		$this->AttributeName = $attribute;
		$this->Style = $wgVoteroAttributes[$attribute]['style'];
	}
	
	// Check if $key exists in $array. Returns $default otherwise.
	function get($array, $key, $default) {
		return array_key_exists($key, $array) ? $array[$key] : $default;
	}
	
	// Checks if $key is an $allowed value. Returns $allowed[0] otherwise.
	function restrain($allowed, $key) {
		return in_array ($key, $allowed) ? $key : $allowed[0];
	}
	
	// TODO: REWRITE ALL THIS
	function display($parser, $options) {
		global $wgVoteroQueryLimit;
		
		// How to format data.
		$format = $this->get($options, 'format', 'list');
		// Key to sort on.
		$sort = $this->restrain(array('total_votes', 'average', 'weighted_average'), $this->get($options, 'sort', 'average'));
		// Direction to sort on.
		$order = array_key_exists('reverse', $options) ? ' DESC' : '';
		// Limit of results. (Capped by $wgVoteroQueryLimit.)
		$limit = min($this->get($options, 'limit', $wgVoteroQueryLimit), $wgVoteroQueryLimit);
		// Seperator.
		$sep = $this->get($options, 'sep', ', ');
		
		// Get list.
		$dbr = wfGetDB(DB_SLAVE);
		$rows = $dbr->query("SELECT page, {$sort} AS value FROM votero_scores WHERE attribute={$this->AttributeID} ORDER BY value{$order} LIMIT {$limit}");
		
		$list = array();
		
		foreach ($rows as $row) {
			$title = $parser->internalParse('[[' . Title::newFromID($row->page) . ']]');
			$value = $row->value;
			
			$list[] = "{$title} ({$value})";
		}
		
		return join($list, $sep);
		
		// Special queries.
		if (array_key_exists('special', $options))
		{
			switch($options['special']) {
				// Shows similar, disimilar pages.
				case 'similar': return $this->displayRelated($parser, $options); break;
				case 'disimilar': return $this->displayRelated($parser, $options); break;
				// Shows most recent votes.
				case 'recent': return $this->displayRecent($parser, $options); break;
				// 
				default: return "";
			}
		}
		
		
		if ($row != null) {
			return "<span title='{$row->total_votes} votes'}>" . number_format($row->weighted_average) . "</span>";
		} else {
			return "<span title='0 votes'}>0</span>";
		}
		
		// Template to represent data. (optional)
		$template = array_key_exists('template', $options) ? $options['template'] : null;
		// Query limit. (optional)
		$limit = array_key_exists('limit', $options) && is_numeric($options['limit']) ? min($wgVoteroQueryLimit, $options['limit']) : $wgVoteroQueryLimit;
		// Sort on. (optional)
		$average = "weighted_average";
		$sorton = "weighted_average";
		if (array_key_exists('sorton', $options)) {
			switch($options['sorton']) {
				case "votes": $sorton = "total_votes"; break;
				case "average": $sorton = "average"; $average = "average"; break;
				case "weighted": $sorton = "weighted_average"; break;
				default: $sorton = "weighted_average";
			}
		}
		// Average format.
		$decimals = array_key_exists('decimals', $options) && is_numeric($options['decimals']) ? $options['decimals'] : 2;
		
		$output = "";
		$dbr = wfGetDB(DB_SLAVE);
		$rows = $dbr->query("SELECT page, average, weighted_average, total_votes FROM votero_scores WHERE attribute={$this->AttributeID} ORDER BY average DESC LIMIT {$limit}");
		
		foreach ($rows as $row) {
			// Page title.
			$title = Title::newFromID($row->page);
			// Weighted average.
			$formatedAverage = number_format($row->weighted_average, $decimals);
			
			if ($template != null) {
				$output .= $parser->internalParse("{{{$template}|{$title}|{$formatedAverage}|{$row->total_votes}}}");
			} else {
				$pageLink = $parser->internalParse("[[{$title}]]");
				$output .= "{$pageLink} {$formatedAverage}% ({$row->total_votes} votes)<br>";
			}
		}
		
		return $output;
	}
}