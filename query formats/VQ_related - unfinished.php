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