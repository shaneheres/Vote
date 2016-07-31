<?php
class Votero
{
	public $UserID = 0;
	public $PageID = 0;
	public $AttributeID = 0;
	public $AttributeName = "";
	public $Style = "";
	
	// Check if an attribute can be voted on.
	public static function AttributeExists($attribute) {
		global $wgVoteroAttributes;
		
		if (!in_array($attribute, array_keys($wgVoteroAttributes)))
			return "Votero is not set up for attribute '{$attribute}'";
		
		return "true";
	}
	
	// Get an attributes name from id.
	public static function GetAttributeName($attributeID) {
		global $wgVoteroAttributes;
		
		$keys = array_keys($wgVoteroAttributes);
		foreach ($keys as $key) {
			if ((int)$wgVoteroAttributes[$key]['id'] == $attributeID) {
				return $key;
			}
		}
		
		return "false";
	}
	
	public function __construct($page, $attribute) {
		global $wgUser, $wgVoteroAttributes;
		
		$this->UserID = 1;//rand(1, 5);//$wgUser->getID();
		$this->PageID = $page;
		
		$this->AttributeID = $wgVoteroAttributes[$attribute]['id'];
		$this->AttributeName = $attribute;
		$this->Style = $wgVoteroAttributes[$attribute]['style'];
	}
	
	function query($dbw, $query) {
		$dbw->begin();
		$dbw->query($query);
		$dbw->commit();
	}
	
	function placeVote($vote) {
		global $wgVoteroStyles, $wgVoteroSMW, $wgVoteroUpdatePage, $wgVoteroUpdateAttribute;
		$time_start = microtime(true);
		$style = $wgVoteroStyles[$this->Style];
		$display = $this->getExists($style, 'display', '');
		
		switch($display){
			// Range style doesn't use buttons.
			case 'range': $max = $style['max']+1; break;
			
			// Button max.
			default: $max = count($style['buttons']);
		}
		
		if (!is_numeric($vote)) {
			return "Vote has to be numeric.";
		}
		
		$removeVote = $vote == -1;
		
		if (!removeVote) {
			if ($vote < 0 || $vote >= $max) {
				return "Vote must be between -1 and {$max}. (Was {$vote}.)";
			}
			
			$alreadyVoted = $vote == -1 ? -1 : (int)$this->getMyVote($this->AttributeID);
		}
		
		$dbw = wfGetDB(DB_MASTER);
		
		// Delete old vote.
		if ($alreadyVoted != -1 || $removeVote) {
			$this->query($dbw, "DELETE FROM votero WHERE user={$this->UserID} AND page={$this->PageID} AND attribute={$this->AttributeID}");
		}
		//return $alreadyVoted." ".($alreadyVoted != -1 ? "true" : "false")."DELETE FROM votero WHERE user={$this->UserID} AND page={$this->PageID} AND attribute={$this->AttributeID}";
		// Create new vote.
		if ($alreadyVoted != $vote && !$removeVote) {
			$time = time();
			$this->query($dbw, "INSERT INTO votero (user, page, attribute, vote, date) VALUES({$this->UserID}, {$this->PageID}, {$this->AttributeID}, {$vote}, FROM_UNIXTIME({$time}))");
		}
		
		// Tell the database something has changed. (For the attribute, and the page itself.)
		$dbr = wfGetDB(DB_SLAVE);
		$rows = $dbr->query("SELECT new_votes, attribute FROM votero_scores WHERE page={$this->PageID} AND (attribute={$this->AttributeID} OR attribute=-1)");
		$new_votes_attribute = -1;
		$new_votes_page = -1;
		
		foreach($rows as $row) {
			if ($row->attribute == -1) {
				$new_votes_page = $row->new_votes;
			}
			else {
				$new_votes_attribute = $row->new_votes;
			}
		}
		
		// Update new vote count for Page.
		if ($new_votes_page == -1) {
			$new_votes_page = 1;
			$this->query($dbw, "INSERT INTO votero_scores (page, attribute, new_votes) VALUES ({$this->PageID}, -1, 1)");
		} else {
			$new_votes_page += 1;
			$this->query($dbw, "UPDATE votero_scores SET new_votes=new_votes+1 WHERE page={$this->PageID} AND attribute=-1");
		}
		
		// Update new vote count for Attribute.
		if ($new_votes_attribute == -1) {
			$new_votes_attribute = 1;
			$this->query($dbw, "INSERT INTO votero_scores (page, attribute, new_votes) VALUES ({$this->PageID}, {$this->AttributeID}, 1)");
		} else {
			$new_votes_attribute += 1;
			$this->query($dbw, "UPDATE votero_scores SET new_votes=new_votes+1 WHERE page={$this->PageID} AND attribute={$this->AttributeID}");
		}
		
		// Update averages.
		if ($new_votes_attribute >= $wgVoteroUpdateAttribute) {
			Votero::UpdateAttribute($this->AttributeName);
		}
		
		// Update page data.
		if ($new_votes_page >= $wgVoteroUpdatePage && $wgVoteroSMW) {
			Votero::UpdatePageSemanticData($this->PageID);
		}
		
		return microtime(true) - $time_start;
	}
	
	// Returns -1 if user hasn't voted for this attribute.
	function getMyVote($attributeID) {
		$dbr = wfGetDB(DB_SLAVE);
		$row = $dbr->fetchObject($dbr->query("SELECT vote FROM votero WHERE user={$this->UserID} AND page={$this->PageID} AND attribute={$attributeID}"));
		return ($row === false ? -1 : $row->vote);
	}
	
	function displayRelated($parser, $options) {
		global $wgVoteroStyles, $wgVoteroQueryLimit;
		$styleData = $wgVoteroStyles[$this->Style];
		$count = count($styleData['buttons']);
		$reverse = array_key_exists('reverse', $styleData);
		
		// Template to represent data. (optional)
		$template = array_key_exists('template', $options) ? $options['template'] : null;
		// Query limit. (optional)
		$limit = array_key_exists('limit', $options) && is_numeric($options['limit']) ? min($wgVoteroQueryLimit, $options['limit']) : $wgVoteroQueryLimit;
		// Relatedness.
		$relatedness = $options['special'];
		switch($relatedness) {
			case 'similar': $ideal = ($reverse ? 0 : $count-1); break;
			case 'disimilar': $ideal = ($reverse ? $count-1 : 0); break;
		}
		
		$dbr = wfGetDB(DB_SLAVE);
		$rows = $dbr->query("
			SELECT
				page,
				COUNT(user) AS rank FROM votero
			WHERE user IN
			(
				SELECT user
				FROM votero
				WHERE page={$this->PageID} AND attribute={$this->AttributeID}
			)
			AND page!={$this->PageID} AND attribute={$this->AttributeID} AND vote={$ideal}
			GROUP BY page
			ORDER BY rank DESC
			LIMIT {$limit}");
		
		if (!$template)
			$output = "Pages {$relatedness} to " . Title::newFromID($this->PageID) ." based on user vote data.<br>";
		
		foreach($rows as $row) {
			$title = Title::newFromID($row->page);
			
			if ($template){
				$output .= $parser->internalParse("{{{$template}|{$title}|{$row->rank}}}");
			} else {
				$pageLink = $parser->internalParse("[[{$title}]]");
				$output .= $pageLink . " " . $row->rank . "<br>";
			}
		}
		return $output;
	}
	
	function displayRecent($parser, $options) {
		return "";
	}
	
	// TODO: REWRITE ALL THIS
	function displayQuery($parser, $options) {
		global $wgVoteroQueryLimit;
		
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
		
		$dbr = wfGetDB(DB_SLAVE);
		$row = $dbr->fetchObject($dbr->query("SELECT average, weighted_average, total_votes FROM votero_scores WHERE attribute={$this->AttributeID} AND page={$this->PageID}"));
		if ($row != null) {
			return "<span title='{$row->total_votes} votes'}>" . number_format($row->weighted_average) . "</span>";
		} else {
			return "<span title='0 votes'}>0</span>";
		}
		// Render as stars.
		if (array_key_exists('stars', $options)) {
			$dbr = wfGetDB(DB_SLAVE);
			$row = $dbr->fetchObject($dbr->query("SELECT average, weighted_average, total_votes FROM votero_scores WHERE attribute={$this->AttributeID} AND page={$this->PageID}"));
			
			$size = array_key_exists('size', $options) ? $options['size'] : '1em';
			
			$output = "";
			if ($row->average != null) {
				for ($i = 0; $i < 5; $i += 1) {
					$x = ($i / 5.0) * 100.0;
					if ($x > $row->average) {
						$output .= "<i class='fa fa-star-o'></i>";
					} else {
						$output .= "<i class='fa fa-star'></i>";
					}
				}
			}
			
			return "<span title='{$row->votes} votes' style='font-size:{$size};'>" . $output . "</span>";
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
		$rows = $dbr->query("
			SELECT
				page,
				{$average},
				total_votes
			FROM
				votero_scores
			WHERE
				attribute={$this->AttributeID}
			ORDER BY
				{$sorton} DESC
			LIMIT
				{$limit}");
		
		
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
	
	function formatNumber($number) {
		if ($number >= 9940) return round($number / 1000.0) . "k";
		if ($number >= 1000) return number_format($number / 1000.0, 1) . "k";
		return $number;
	}
	
	/*function number_with_commas(x) {
		return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
	}*/

	/*function convert_microgram($x) {
		if ($x > 1000000000) return number_format($x / 1000000000, 2) . " kg";
		if ($x > 1000000) return number_format($x / 1000000, 2) . " g";
		if ($x > 1000) return number_format($x / 1000, 2) . " mg";
		return x . " mcg";
	}
	
	function convert_miligram($x) {
		if ($x > 1000000) return number_format($x / 1000000, 2) . " kg";
		if ($x > 1000) return number_format($x / 1000, 2) . " g";
		return x . " mg";
	}
	*/
	// Returns $key if it exists in $array, returns $default otherwise.
	function getExists($array, $key, $default) {
		return array_key_exists($key, $array) ? $array[$key] : $default;
	}
	
	function formatSlider($sliderLabel, $x, $max) {
		$vars = explode(',', Votero::get_between($sliderLabel, '[', ']'));
		$original = $x;
		
		if (count($vars) == 1) {
			$parts = explode('=', $vars[0]);
			$label = count($parts) == 2 ? $parts[1] : $parts[0];
			return Votero::replace_between($sliderLabel, '[', ']', "{$x}{$label}", true);
		}
		
		for ($i = count($vars)-1; $i >= 0; $i -= 1) {
			$parts = explode('=', $vars[$i]);
			$v = (int)$parts[0];
			$label = $parts[1];
			
			// If smaller than that, use current.
			if ($x >= $v || $i == 0) {
				$x = (float)$x / (float)$v;
				
				if ($i != 0) {
					if (round($x) == number_format($x, 1)) {
						$x = round($x);
					} else {
						$x = number_format($x, 1);
					}
				}
				
				if ($x != 1) {
					$label .= "'s";
				}
				
				if ($original == $max) {
					$x = "Over {$x}";
				}
				
				return Votero::replace_between($sliderLabel, '[', ']', "{$x}{$label}", true);
			}
		}
	}
	
	function display($parser) {
		global $wgUser, $wgVoteroStyles, $wgVoteroAttributes;
		
		$parser->getOutput()->addModuleStyles('ext.votero.styles');
		$parser->getOutput()->addModules('ext.votero.scripts');
		
		$styleData = $wgVoteroStyles[$this->Style];
		$display = $this->getExists($styleData, 'display', '');
		$labelDisplay = $this->getExists($styleData, 'label display', 'off');
		$countDisplay = $this->getExists($styleData, 'count display', 'right');
		// Get size from attribute, otherwise use style default.
		$size = $this->getExists($wgVoteroAttributes[$this->AttributeName], 'size', $this->getExists($styleData, 'size', '1em'));
		$width = $this->getExists($styleData, 'width', 'auto');
		$spanColor = $this->getExists($styleData, 'color', '#000000');
		
		$id = "votero_{$this->PageID}_{$this->AttributeID}_{$display}";
		$output = "<span id='{$id}' style='font-size:{$size};'>";
		
		// Get my vote.
		$dbr = wfGetDB(DB_SLAVE);
		$myVote = $this->getMyVote($this->AttributeID);
		
		// Range display. (No buttons.)
		switch($display) {
			case 'range':
				$max = $this->getExists($styleData, 'max', 100);
				$default = $myVote != -1 ? $myVote : $this->getExists($styleData, 'default', (int)$max / 2);
				$step = $this->getExists($styleData, 'step', 'any');
				$label = $this->getExists($styleData, 'label', '');
				$labelFormatted = $myVote == -1 ? "" : $this->formatSlider($label, (int)$default, $max);
				
				$label1 = $this->formatSlider($label, 0, $max);
				$label2 = $this->formatSlider($label, $max, $max);
				$output = "<span>{$label1}</span><span class='pull-right'>{$label2}</span>";
				// Range.
				$output .= "<input id='{$id}' style='width:{$width};display:inline;' type='range' min='0' max='{$max}' value='{$default}' step='{$step}' data-label=\"". $label ."\" oninput='voteroRangeStep(id,value)' onchange='voteroRangeSubmit(id,value)'>";
				// Delete input button.
				$deleteCSS = $myVote == -1 ? "style='display:none'" : "";
				$output .= "<span {$deleteCSS} id='{$id}_delete' data-toggle='tooltip' data-placement='top' title='Remove input.' class='fa fa-trash votero-delete'></span>";
				// Input label.
				$output .= "<span style='color:{$spanColor};' id='{$id}_txt'> {$labelFormatted}</span>";
				
				return $output;
				break;
		}
		
		$buttons = $styleData['buttons'];
		$count = count($buttons);
		$superClass = $this->getExists($styleData, 'class', '');
		$superClassBack = $this->getExists($styleData, 'classBack', '');
		
		for($x = 0; $x < $count; $x += 1)
		{
			$class = $this->getExists($buttons[$x], 'class', $superClass);
			$classBack = $this->getExists($buttons[$x], 'classBack', $superClassBack == '' ? $class : $superClassBack);
			$color = $this->getExists($buttons[$x], 'color', $spanColor);
			
			// If this button was my vote, highlight it. (If display type is 'stars' buttons to the left will also be highlighted.)
			$sel = $x == $myVote || ($display == 'stars' && $x < $myVote);
			$selected = $sel ? 'votero-selected' : '';
			$selectedClass = $sel ? $class : $classBack;
			$label = $this->getExists($buttons[$x], 'label', '');
			
			$output .= "<span style='color:{$color};'>";
			$output .= "<span id='{$x}' class='votero {$selected} {$selectedClass}'";
			
			// Button style.
			if ($count == 1 || $x != $count-1){
				$output .= "style='width:{$width};'";
			}
			
			// Classes used to change image when selected.
			$output .= "data-class='{$class}'";
			$output .= "data-backing='{$classBack}'";
			
			// Vote label.
			if ($labelDisplay == 'on') {
				$output .= "data-toggle='tooltip'";
				$output .= "data-placement='top'";
				$output .= "title='{$label}'";
			}
			
			// Vote count.
			if ($countDisplay == 'on'){
				$voteCount = $dbr->fetchObject($dbr->query("SELECT COUNT(*) AS vote_count FROM votero WHERE page={$this->PageID} AND attribute={$this->AttributeID} AND vote={$x}"))->vote_count;
				$output .= "data-count='{$voteCount}'";
			}
			
			$output .= ">";
			
			// Text representation of vote count.
			if ($countDisplay == 'on') {
				$fontSize = $this->getExists($styleData, 'font-size', $size);
				$output .= " <span style='font-size:{$fontSize};'>{$this->formatNumber($voteCount)}</span>";
			}
			$output .= "</span>";
			
			if ($display == 'radio') {
				$fontSize = $this->getExists($styleData, 'font-size', $size);
				$output .= " <span style='font-size:{$fontSize};color:black;'>{$label}</span><br>";
			}
			
			$output .= "</span>";
		}
		
		return $output . "</span>";
	}
	
	public static function UpdatePageSemanticData($pageID) {
		global $wgVoteroStyles, $wgVoteroAttributes;
		
		if (!is_numeric($pageID)) {
			return "Page must be in integer form.";
		}
		
		$time_pre = microtime(true);
		
		// Add date to pages, for semantic media wiki.
		$title = Title::newFromID($pageID);
		
		if ($title->exists()) {
			$dbr = wfGetDB(DB_SLAVE);
			$rows = $dbr->query("SELECT attribute, total_votes, average, weighted_average FROM votero_scores WHERE page={$pageID} AND attribute!=-1");
			
			if ($rows) {
				// Reset count.
				$dbw = wfGetDB(DB_SLAVE);
				$dbw->begin();
				$dbw->query("UPDATE votero_scores SET new_votes=0 WHERE page={$pageID} AND attribute=-1");
				$dbw->commit();
				
				// Combine ratings into a Semantic {{#set}} tag.
				$output = array();
				foreach($rows as $row) {
					// Attribute name.
					$attributeName = Votero::GetAttributeName($row->attribute);
					$attribute = $wgVoteroAttributes[$attributeName];
					// Style
					$style = $wgVoteroStyles[$attribute['style']];
					$max = array_key_exists('buttons', $style) ? count($style['buttons']) : 0;
					// Semantic forms.
					$smw = explode(',', array_key_exists('smw', $attribute) ? $attribute['smw'] : 'average AS percent2, votes AS format');
					
					foreach($smw as $atr) {
						$value = trim(explode('AS', $atr)[0]);
						$form = trim(explode('AS', $atr)[1]);
						
						switch($value) {
							// Votes.
							case 'votes': $value = $row->total_votes; break;
							// Averages.
							case 'average': $value = $row->average; break;
							case 'weighted': $value = $row->weighted_average; break;
							default: $value = $row->average;
						}
						
						switch($form) {
							// Votes.
							case 'int': $form = ' votes'; break;
							case 'format': $form = ' votes'; $value = number_format($value); break;
							// Average.
							case 'percent': $form = ' average'; $value = number_format($value); break;
							case 'percent1': $form = ' average'; $value = number_format($value, 1); break;
							case 'percent2': $form = ' average'; $value = number_format($value, 2); break;
							// Normal.
							case 'normal': $form = ' normal'; $value = number_format($value / 100.0, 2); break;
							// Index.
							case 'index': $form = ' index'; $value = round(($value / 100.0) * $max); break;
							case 'index_f': $form = ' index'; $value = floor(($value / 100.0) * $max); break;
							case 'index_c': $form = ' index'; $value = ceil(($value / 100.0) * $max); break;
							// Label.
							case 'label': $form = ' label'; $value = $style['buttons'][round(($value / 100.0) * $max)]['label']; break;
							case 'label_f': $form = ' label'; $value = $style['buttons'][floor(($value / 100.0) * $max)]['label']; break;
							case 'label_c': $form = ' label'; $value = $style['buttons'][ceil(($value / 100.0) * $max)]['label']; break;
							// Class.
							case 'class': $form = ' icon'; $value = $style['buttons'][round(($value / 100.0) * $max)]['class']; break;
							case 'class_f': $form = ' icon'; $value = $style['buttons'][floor(($value / 100.0) * $max)]['class']; break;
							case 'class_c': $form = ' icon'; $value = $style['buttons'][ceil(($value / 100.0) * $max)]['class']; break;
							// 
							default: $form = '';
						}
						
						$output[] = "{$attributeName}{$form}={$value}";
					}
					// Average.
					//$output[] = "{$attribute}={$row->weighted_average}";
					// Total votes.
					//$output[] = "{$attribute} votes={$row->total_votes}";
				}
				
				// Combine.
				$semanticString = "{{#set:" . join("|", $output) ."}}";
				
				// Get wiki page.
				$wikipage = WikiPage::factory($title);
				$text = $wikipage->getText();
				$tag = "<!--Votero-->";
				
				// Check if previous data exists.
				$pos = strpos($text, $tag);
				
				if ($pos === false) {
					// Add data.
					$wikipage->doEdit($text . "\n{$tag}{$semanticString}{$tag}", '', EDIT_UPDATE|EDIT_MINOR|EDIT_SUPPRESS_RC|EDIT_FORCE_BOT);
					$total_time = (microtime(true) - $time_pre);
					return "{$title} updated in {$total_time}.<br>";
				} else {
					// Modify data.
					$wikipage->doEdit(Votero::replace_between($text, $tag, $tag, $semanticString), '', EDIT_UPDATE|EDIT_MINOR|EDIT_SUPPRESS_RC|EDIT_FORCE_BOT);
					$total_time = (microtime(true) - $time_pre);
					return "{$title} updated in {$total_time}.<br>";
				}
			}
		}
		
		return "Bad";
	}
	
	public static function UpdateAttribute($attributeName) {
		global $wgVoteroStyles, $wgVoteroAttributes;
		$attributeID = $wgVoteroAttributes[$attributeName]['id'];
		$style = $wgVoteroStyles[$wgVoteroAttributes[$attributeName]['style']];
		$display = array_key_exists('display', $style) ? $style['display'] : '';
		$reverse = array_key_exists('reverse', $style);
		
		switch($display){
			case 'range': $max = $style['max']; break;
			default: $max = count($style['buttons']);
		}
		
		$time_pre = microtime(true);
		
		$dbw = wfGetDB(DB_MASTER);
		$dbr = wfGetDB(DB_SLAVE);
		
		// Get pages that have new votes.
		$pages = $dbr->query("SELECT DISTINCT(page) FROM votero_scores WHERE attribute={$attributeID} AND new_votes>0 ORDER BY page");
		
		$output = "Updating: {$attributeName}<br>";
		$pagesUpdated = 0;
		
		// Update each pages averages.
		foreach ($pages as $page) {
			$pagesUpdated += 1;
			$pageName = Title::newFromID($page->page);
			$pageID = $page->page;
			
			// Get vount count and average vote.
			$row = $dbr->fetchObject($dbr->query("SELECT COUNT(vote) AS total_votes, AVG(vote) AS average FROM votero WHERE (page={$pageID} AND attribute={$attributeID})"));
			
			// Scale between 0.0 to 100.0. This way attributes can be compared.
			if ($row->total_votes == null || $row->total_votes == 0) {
				$average = 0;
				$total_votes = 0;
			} else {
				$average = ($max <= 1 ? 100.0 : ($row->average * 100.0) / ($max - 1));
				
				if ($reverse)
					$average = 100.0 - $average;
				
				$total_votes = $row->total_votes;
			}
			
			$output .= "----{$pageName} updated to {$average}% with {$total_votes} votes.<br>";
			$dbw->begin();
			// Update average.
			$dbw->query("UPDATE votero_scores SET average={$average}, total_votes={$total_votes}, new_votes=0 WHERE page={$pageID} AND attribute={$attributeID}");
			// Update votes for page, so it knows semantic data is old.
			$dbw->query("UPDATE votero_scores SET new_votes=new_votes+1 WHERE page={$pageID} AND attribute=-1");
			$dbw->commit();
		}
		
		// Updated weighted average.
		$bay = $dbr->fetchObject($dbr->query("SELECT SUM(total_votes) as total_votes, AVG(average) AS average FROM votero_scores WHERE attribute={$attributeID}"));
		$bay_total_votes = (float)$bay->total_votes;
		$bay_average = (float)$bay->average;
		$dbw->begin();
		$dbw->query("UPDATE votero_scores SET weighted_average=({$bay_total_votes} * {$bay_average} + total_votes * average) / ({$bay_total_votes} + total_votes) WHERE attribute={$attributeID}");
		$dbw->commit();
		
		$total_time = (microtime(true) - $time_pre);
		return $output . "{$pagesUpdated} pages updated in {$total_time}.<br>";
	}
	
	public static function get_between($string, $start, $end) {
		$string = ' ' . $string;
		$ini = strpos($string, $start);
		if ($ini == 0) return '';
		$ini += strlen($start);
		$len = strpos($string, $end, $ini) - $ini;
		return substr($string, $ini, $len);
	}
	
	public static function replace_between($str, $needle_start, $needle_end, $replacement, $remove_needles=false) {
		$pos = strpos($str, $needle_start);
		$start = $pos === false ? 0 : $pos + strlen($needle_start);
		$pos = strpos($str, $needle_end, $start);
		$end = $start === false ? strlen($str) : $pos;
		return substr_replace($str, $replacement, $start - ($remove_needles ? strlen($needle_start) : 0), $end - $start + ($remove_needles ? strlen($needle_start . $needle_end) : 0));
	}
}