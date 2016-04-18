<?php
class Votero
{
	public $UserID = 0;
	public $PageID = 0;
	public $AttributeID = 0;
	public $AttributeName = "";
	public $Style = "";
	
	public static function KeyAttributeExists($attribute) {
		global $wgVoteroAttributes;
		
		if (!in_array($attribute, array_keys($wgVoteroAttributes)))
			return "Votero is not set up for attribute '{$attribute}'";
		
		return "true";
	}
	
	public function __construct($page, $attribute) {
		global $wgUser, $wgVoteroAttributes;
		
		$this->UserID = $wgUser->getID();
		$this->PageID = $page;
		
		if ($attribute == "") // This is the case when initializing charts.
			return;
		
		$this->AttributeID = $wgVoteroAttributes[$attribute]['id'];
		$this->AttributeName = $attribute;
		$this->Style = $wgVoteroAttributes[$attribute]['style'];
	}
	
	function placeVote($vote)
	{
		global $wgVoteroStyles;
		$data = $wgVoteroStyles[$this->Style]['buttons'];
		$max = count($data);
		
		if (!is_numeric($vote)) {
			return "Vote has to be numeric. Please don't hack me.";
		}
		
		if ($vote < 0 || $vote >= $max) {
			return "Vote is out of allowable range. Please don't hack me.";
		}
		
		$time_pre = microtime(true);
		$alreadyVoted = (int)$this->getMyVote($this->AttributeID);
		$dbw = wfGetDB(DB_MASTER);
		
		// Delete old vote.
		if ($alreadyVoted != -1) {
			$dbw->begin();
			$dbw->query("DELETE FROM votero WHERE user={$this->UserID} AND page={$this->PageID} AND attribute={$this->AttributeID}");
			$dbw->commit();
		}
		// Create new vote.
		if ($alreadyVoted != $vote) {
			$time = time();
			$dbw->begin();
			$dbw->query("INSERT INTO votero (user, page, attribute, vote, date) VALUES({$this->UserID}, {$this->PageID}, {$this->AttributeID}, {$vote}, FROM_UNIXTIME({$time}))");
		}
		
		// Recalculate Attributes average based on all votes.
		$dbr = wfGetDB(DB_SLAVE);
		$row = $dbr->fetchObject($dbr->query("SELECT COUNT(vote) AS count, AVG(vote) AS average FROM votero WHERE (page = {$this->PageID} AND attribute = {$this->AttributeID})"));
		
		// Scale between 0.0 to 100.0. This way attributes can be compared.
		if ($row->count == null || $row->count == 0) {
			$average = 0;
			$total_votes = 0;
		} else {
			$average = ($max <= 1 ? 100 : ($row->average * 100.0) / ($max - 1));
			
			// The further left a button, the higher it's applied rating.
			// If reverse is true then the furthest right button is the highest rating.
			// Styles with only 1 button will always have an average of %100, but their bayes score works like any other.
			if (in_array('reverse', $wgVoteroStyles[$this->Style]))
				$average = 100.0 - $average;
			
			$total_votes = $row->count;
		}
		
		// Delete old.
		$dbw->begin();
		$dbw->query("DELETE FROM votero_scores WHERE page={$this->PageID} AND attribute={$this->AttributeID}");
		$dbw->commit();
		
		// Update new average, if there are votes.
		if ($total_votes > 0) {
			$dbw->query("INSERT INTO votero_scores (page, attribute, average, total_votes) VALUES ({$this->PageID}, {$this->AttributeID}, {$average}, {$total_votes})");
			
			// Get the average of averages. (Bayes.)
			$bay = $dbr->fetchObject($dbr->query("SELECT SUM(total_votes) AS total_votes, AVG(average) AS average FROM votero_scores WHERE attribute={$this->AttributeID}"));
			
			// Update each pages attribute bayesian score.
			$dbw->begin();
			$dbw->query("UPDATE votero_scores SET bayes=({$bay->total_votes} * {$bay->average} + total_votes * average) / ({$bay->total_votes} + total_votes) WHERE attribute={$this->AttributeID}");
			$dbw->commit();
			
			// This awful hack will edit the scores into the wiki page so that Semantic MediaWiki can access them.
			global $wgVoteroSMW;
			if ($wgVoteroSMW) {
				// Append semantic data to bottom of page. This will be invisible to readers, but not editors.
				$page = WikiPage::factory($pageName);
				$text = $page->getText();
				$subtext = ($count == "" ? "" : "{{#set:{$this->AttributeName}={$score}|{$this->AttributeName} votes={$count}}}");
				$tag = "Votero-" . $this->AttributeName;
				
				if (strpos($text, "<!--Votero-") === false) {
					$text .= "\n<!--Results from Votero extension for use by Semantic MediaWiki extension.-->";
				}
				$flag = EDIT_UPDATE|EDIT_MINOR|EDIT_SUPPRESS_RC|EDIT_FORCE_BOT;
				// Check if this section exists in the article.
				$pos = strpos($text, $tag);
				// Add it to the end if not.
				if ($pos === false) {
					$page->doEdit($text . "\n<!--{$tag}-->{$subtext}<!--end-->", '', $flag);
				}
				else {
					$page->doEdit($this->replace_between($text, "<!--{$tag}-->", "<!--end-->", $subtext), '', $flag);
				}
			}
		}
		
		return (microtime(true) - $time_pre);
	}
	
	function replace_between($str, $needle_start, $needle_end, $replacement) {
		$pos = strpos($str, $needle_start);
		$start = $pos === false ? 0 : $pos + strlen($needle_start);
		$pos = strpos($str, $needle_end, $start);
		$end = $start === false ? strlen($str) : $pos;
		return substr_replace($str,$replacement,  $start, $end - $start);
	}
	
	function getMyVote($attributeID) {
		$dbr = wfGetDB(DB_SLAVE);
		$row = $dbr->fetchObject($dbr->query("SELECT vote FROM votero WHERE user={$this->UserID} AND page={$this->PageID} AND attribute={$attributeID}"));
		return ($row === false ? -1 : $row->vote);
	}
	
	function getInitial($attributes) {
		global $wgVoteroAttributes, $wgVoteroStyles;
		
		$data = array();
		$dbr = wfGetDB(DB_SLAVE);
		
		$b = array();
		foreach($attributes as $attribute) {
			$attributeID = $wgVoteroAttributes[$attribute]['id'];
			$buttons = $wgVoteroStyles[$wgVoteroAttributes[$attribute]['style']]['buttons'];
			
			// Get total votes.
			$a = array();
			for($x = 0; $x < count($buttons); $x += 1) {
				$a[] = $dbr->fetchObject($dbr->query("SELECT COUNT(*) AS vote_count FROM votero WHERE page={$this->PageID} AND attribute={$attributeID} AND vote={$x}"))->vote_count;
			}
			
			// Convert to string.
			$d = $this->getMyVote($attributeID) . "."; // My vote.
			$d .= join("_", $a);
			$b[] = $d;
		}
		
		return join("|", $b);
	}
	
	function displayQuery($parser) {
		global $wgVoteroQueryLimit;
		
		$dbr = wfGetDB(DB_SLAVE);
		$rows = $dbr->query("SELECT page, bayes, total_votes FROM votero_scores WHERE attribute={$this->AttributeID} ORDER BY bayes DESC LIMIT {$wgVoteroQueryLimit}");
		
		$output = "";
		foreach ($rows as $row) {
			$output .= Title::newFromID($row->page) . " " . number_format($row->bayes, 2) . "% (" . $row->total_votes . " votes)<br>";
		}
		
		return $output;
	}
	
	function display($parser) {
		global $wgUser, $wgVoteroStyles;
		
		$parser->getOutput()->addModuleStyles('ext.votero.styles');
		$parser->getOutput()->addModules('ext.votero.scripts');
		
		$styleData = $wgVoteroStyles[$this->Style];
		$data = $styleData['buttons'];
		$display = array_key_exists('display', $styleData) ? $styleData['display'] : "";
		
		$count = count($data);
		$output = "";
		
		for($x = 0; $x < $count; $x += 1)
		{
			$id = "votero_{$this->AttributeName}_{$x}";
			$class = $data[$x]["class"];
			$backing = array_key_exists('classBack', $data[$x]) ? $data[$x]['classBack'] : $class;
			$color = array_key_exists('color', $data[$x]) ? $data[$x]["color"] : '#000000';
			
			switch($display) {
				case "stars":
					$output .= "<span id='{$id}_span' class='votero-span votero-stars' style='color:{$color}'>"
					. "<span id='{$id}_btn' class='fa-fw votero-button {$backing}' data-class='{$class}' data-backing='{$backing}' data-attribute='{$this->AttributeName}' data-vote='{$x}' data-toggle='tooltip' data-placement='top' title='{$data[$x]["title"]}'></span>"
					. "</span>";
					break;
				
				case "underneath":
					$output .= "<span id='{$id}_span' class='votero-span votero-underneath center' style='color:{$color}'>"
					. "<span id='{$id}_btn' class='fa-fw votero-button {$backing}' data-class='{$class}' data-backing='{$backing}' data-attribute='{$this->AttributeName}' data-vote='{$x}' data-toggle='tooltip' data-placement='top' title='{$data[$x]["title"]}'></span>"
					. "<br><span id='{$id}_txt' class='votero-text' data-voteCount='0'>0</span>"
					. "</span>";
					break;
				
				case "labels":
					$output .= "<span id='{$id}_span' class='votero-span votero-underneath center' style='color:{$color}; width:2.5em;'>"
					. "<span id='{$id}_btn' class='fa-fw votero-button {$backing}' data-class='{$class}' data-backing='{$backing}' data-attribute='{$this->AttributeName}' data-vote='{$x}' title='{$data[$x]["title"]}'></span>"
					. "<div style='line-height:2em; font-size:.4em;' class='votero-text'>{$data[$x]["title"]}</div>"
					. "<div style='line-height:0.6em; font-size:.4em;' id='{$id}_txt' class='votero-text' data-voteCount='0'>0</div>"
					. "</span>";
					break;
				
				default:
					$output .= "<span id='{$id}_span' class='votero-span' style='color:{$color}'>"
					. "<span id='{$id}_btn' class='fa-fw votero-button {$backing}' data-class='{$class}' data-backing='{$backing}' data-attribute='{$this->AttributeName}' data-vote='{$x}' data-toggle='tooltip' data-placement='top' title='{$data[$x]["title"]}'></span>"
					. "<span id='{$id}_txt' class='votero-text' data-voteCount='0'>0</span>"
					. "</span>";
			}
		}
		
		return $output;
	}
}