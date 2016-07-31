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
	
	function getRow() {
		$dbr = wfGetDB(DB_SLAVE);
		return $dbr->fetchObject($dbr->query("SELECT total_votes, average, weighted_average FROM votero_scores WHERE attribute={$this->AttributeID} AND page={$this->PageID}"));
	}
	
	function getRows($limit=10) {
		$dbr = wfGetDB(DB_SLAVE);
		return $dbr->fetchObject($dbr->query("SELECT page, total_votes, average, weighted_average FROM votero_scores WHERE attribute={$this->AttributeID} LIMIT={$limit}"));
	}
	
	// Shows the average, total_votes, or weighted average.
	function display($parser, $options) {
		global $wgVoteroQueryLimit;
		
		// html
		$html = $this->get($options, 'html', 'AVG_0%');
		
		// Get object.
		$row = $this->getRow();
		
		if ($row != null) {
			if (strpos($html, 'AVG') !== false) {
				$html = str_replace('AVG_3', number_format($row->average, 3), $html);
				$html = str_replace('AVG_2', number_format($row->average, 2), $html);
				$html = str_replace('AVG_1', number_format($row->average, 1), $html);
				$html = str_replace('AVG_0', number_format($row->average, 0), $html);
				$html = str_replace('AVG', $row->average, $html);
			}
			
			if (strpos($html, 'VOTES') !== false) $html = str_replace('VOTES', number_format($row->total_votes), $html);
			
			if (strpos($html, 'WEIGHTED') !== false) {
				$html = str_replace('WEIGHTED_3', number_format($row->weighted_average, 3), $html);
				$html = str_replace('WEIGHTED_2', number_format($row->weighted_average, 2), $html);
				$html = str_replace('WEIGHTED_1', number_format($row->weighted_average, 1), $html);
				$html = str_replace('WEIGHTED_0', number_format($row->weighted_average, 0), $html);
				$html = str_replace('WEIGHTED', $row->weighted_average, $html);
			}
		}
		
		return $html;
	}
}