<?php
class VQ_stars extends VQ_default
{
	function display($parser, $options) {
		global $wgVoteroQueryLimit;
		
		$row = $this->getRow();
		$size = $this->get($options, 'size', '1em');
		
		$output = "<span title='{$row->total_votes} votes' style='font-size:{$size};'>";
		if ($row->average != null) {
			for ($i = 0; $i < 5; $i += 1) {
				$x = ($i / 5.0) * 100.0;
				$class = $x > $row->average ? 'fa-star-p' : 'fa-star';
				$output .= "<i class='fa {$class}'></i>";
			}
		}
		$output .= "</span>";
		
		return $output;
	}
}