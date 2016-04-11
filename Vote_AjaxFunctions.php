<?php
/**
 * AJAX functions used by Vote extension.
 */

$wgAjaxExportList[] = 'wfVoteClick';
function wfVoteClick($page, $attribute, $voteValue) {
	global $wgUser, $wgVoteKeys, $wgVoteAttributes;
	
	if (!is_numeric($page) or !is_numeric($voteValue)) {
		return json_encode(array("CHECK", !is_numeric($page), !is_numeric($voteValue)));
	}
	
	if (!$wgUser->isAllowed('vote')) {
		return "not allowed to vote";
	}
	
	$error = Vote::KeyAttributeExists($attribute);
	if ($error != "true") {
		return $error;
	}
	
	$vote = new Vote($page, $attribute);
	return json_encode($vote->placeVote($voteValue));
}