<?php
class Vote
{
	public $IDUser = 0;
	public $IDPage = 0;
	public $IDStyle = 0;
	public $IDAttribute = 0;
	
	public $Style = "";
	public $Attribute = "";
	
	public static function KeyAttributeExists($attribute) {
		global $wgVoteAttributes;
		
		// in_array
		if (!in_array($attribute, array_keys($wgVoteAttributes)))
			return "no attribute: " .  $attribute;
		
		return "true";
	}
	
	public function __construct($page, $attribute) {
		global $wgUser, $wgVoteStyles, $wgVoteAttributes;
		
		$this->IDUser = $wgUser->getID();
		$this->IDPage = $page;
		
		$this->IDAttribute = $wgVoteAttributes[$attribute]['id'];//array_search($attribute, $keys);
		$this->Attribute = $attribute;
		
		$this->Style = $wgVoteAttributes[$attribute]['style'];
		$this->IDStyle = array_search($this->Style, array_keys($wgVoteStyles));
	}
	
	function rowSelect($vars, $conditions, $options = '') {
		$db = wfGetDB(DB_SLAVE);
		$res = $db->select('vote', $vars, $conditions, __METHOD__, $options);
		return $db->fetchObject($res);
	}
	
	function rowDelete($conditions) {
		$db = wfGetDB(DB_MASTER);
		$db->begin();
		$db->delete('vote', $conditions, __METHOD__);
		$db->commit();
	}
	
	function rowInsert($row) {
		$db = wfGetDB(DB_SLAVE);
		$db->begin();
		$db->insert('vote', $row, __METHOD__);
		$db->commit();
	}
	
	function countVotes($id) {
		return $this->rowSelect('COUNT(*) AS vote_count', array('id_page'=>$this->IDPage, 'id_attribute'=>$this->IDAttribute, 'id_vote'=>$id))->vote_count;
	}
	
	/**
	 * Clear caches - memcached, parser cache and Squid cache
	 */
	function clearCache() {
		global $wgUser, $wgMemc;
		
		// Kill internal cache
		$wgMemc->delete( wfMemcKey( 'vote', 'count' . $this->IDAttribute, $this->IDPage ) );
		$wgMemc->delete( wfMemcKey( 'vote', 'avg' . $this->IDAttribute, $this->IDPage ) );
		
		// Purge squid
		$pageTitle = Title::newFromID( $this->IDPage );
		if (is_object($pageTitle)) {
			$pageTitle->invalidateCache();
			$pageTitle->purgeSquid();
			
			// Kill parser cache
			$article = new Article( $pageTitle, /* oldid */0 );
			$parserCache = ParserCache::singleton();
			$parserKey = $parserCache->getKey( $article, $wgUser );
			$wgMemc->delete( $parserKey );
		}
	}
	
	function placeVote($vote)
	{
		global $wgVoteStyles;
		$data = $wgVoteStyles[$this->Style];
		
		// Check that the requested vote is within allowable range. Safety precaution.
		if($vote >= 0 && $vote < count($data))
		{
			$pageName = Title::newFromID($this->IDPage);
			$alreadyVoted = $this->getUsersVote();
			
			// Delete vote if user voted already.
			if ($alreadyVoted != -1)
			{
				$this->rowDelete(array('id_user' => $this->IDUser, 'id_page' => $this->IDPage, 'id_attribute' => $this->IDAttribute));
			}
			
			// Add vote, unless user is just taking back their vote.
			if ($alreadyVoted != $vote)
			{
				$this->rowInsert(array(
					'id_user' => $this->IDUser,
					'id_page' => $this->IDPage,
					'id_attribute' => $this->IDAttribute,
					'id_vote' => $vote
				));
				$this->clearCache();
			}
			
			// This is a really awful hack.
			// Save them for Semantic MediaWiki to use.
			global $wgVoteSMW;
			if ($wgVoteSMW) {
				// Get vote count and vote average.
				$row = $this->rowSelect(
					'COUNT(id_vote) AS vote_count, AVG(id_vote) AS vote_average',
					array('id_page' => $this->IDPage, 'id_attribute' => $this->IDAttribute));
				
				if ($row->vote_average == null) {
					$row->vote_average = 0;
					$row->vote_count = 0;
				}
				
				// Normalize the votes.
				$score = $row->vote_count == 0 ? 0 : number_format(100.0-(floatval($row->vote_average)/(count($data)-1.0)) * 100.0, 2);
				
				// Append semantic data to bottom of page. This will be invisible to readers, but not editors.
				$page = WikiPage::factory($pageName);
				$text = $page->getText();
				$subtext = "{{#set:{$this->Attribute}={$score}|{$this->Attribute} votes={$row->vote_count}}}";
				$tag = "Vote-" . $this->Attribute;
				
				if (strpos($text, "<!--Vote-") === false) {
					$text .= "\n<!--Results from Vote extension for use by Semantic MediaWiki extension.-->";
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
			
			return strval($flag);
		}
		else
			return "vote is out of range";
	}
	
	function replace_between($str, $needle_start, $needle_end, $replacement) {
		$pos = strpos($str, $needle_start);
		$start = $pos === false ? 0 : $pos + strlen($needle_start);
		$pos = strpos($str, $needle_end, $start);
		$end = $start === false ? strlen($str) : $pos;
		return substr_replace($str,$replacement,  $start, $end - $start);
	}
	
	function getUsersVote() {
		$s = $this->rowSelect('id_vote', array(
			'id_user' => $this->IDUser,
			'id_page' => $this->IDPage,
			'id_attribute' => $this->IDAttribute
		));
		return ($s === false ? -1 : $s->id_vote);
	}
	
	function format_number($number) {
		if ($number >= 9940) return round($number / 1000.0) . "k";
		if ($number >= 1000) return number_format($number / 1000, 1) . "k";
		return $number;
	}
	
	function display($parser) {
		global $wgUser, $wgVoteStyles;
		
		$parser->disableCache();
		$parser->getOutput()->addModuleStyles('ext.vote.styles');
		
		// Check if user can vote.
		if ($wgUser->isAllowed('vote')) {
			$parser->getOutput()->addModules('ext.vote.scripts');
		}
		
		$data = $wgVoteStyles[$this->Style];
		$voted = $this->getUsersVote();
		$output = "";
		$count = count($data);
		
		for($x = 0; $x < $count; $x += 1)
		{
			$voteCount = $this->countVotes($x);
			$selected = ($x == $voted ? "true" : "false");
			$selectClass = ($x == $voted ? "vote-selected " . $data[$x]['color'] : "vote-unselected");
			
			// This removes the right margin on the right most vote button.
			// Doesn't effect single button vote spans.
			$spanClass = ($x == floor($count-1) && $count > 1 ? "vote-span-end" : "");
			
			$output .= "<span class=\"vote-span {$spanClass}\">"
				. "<span class=\"vote-radio-button {$data[$x]['class']} {$selectClass}\""
					. "data-a=\"{$this->Attribute}\""
					. "data-v=\"{$x}\""
					. "data-s=\"{$selected}\""
					. "data-c=\"{$data[$x]['color']}\""
					. "data-toggle=\"tooltip\""
					. "data-placement=\"top\""
					. "title=\"{$data[$x]['title']}\"></span>"
				. " <span class=\"vote-radio-text\" data-t=\"{$voteCount}\" id=\"vote_{$this->Attribute}_{$x}\">{$this->format_number($voteCount)}</span>"
				. "</span>";
		}
		
		return $output;
	}
}