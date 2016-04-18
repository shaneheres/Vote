<?php

// Places a vote, if the user is allowed and the attribute exists.
$wgAjaxExportList[] = 'wfVoteroClick';
function wfVoteroClick($page, $attribute, $vote) {
	global $wgUser;
	
	if (!is_numeric($page) or !is_numeric($vote)) {
		return json_encode(array("CHECK", !is_numeric($page), !is_numeric($vote)));
	}
	
	if (!$wgUser->isAllowed('votero')) {
		return "not allowed to vote";
	}
	
	$error = Votero::KeyAttributeExists($attribute);
	if ($error != "true") {
		return $error;
	}
	
	$votero = new Votero($page, $attribute);
	return json_encode($votero->placeVote($vote));
}

// Get's the votes for a pages attributes bars.
// This way page can be cached and still show up to date voting data.
$wgAjaxExportList[] = 'wfVoteroGetData';
function wfVoteroGetData($page, $attributes) {
	global $wgUser, $wgVoteroAttributes;
	
	$a = explode (",", $attributes);
	
	// Check that all attribute are allowed.
	// Don't bother continuing if someone tried to hack it.
	$keys = array_keys($wgVoteroAttributes);
	foreach ($a as $b) {
		if (!in_array($b, $keys)) {
			return $b . " is not an allowable attribute.";
		}
	}
	
	$votero = new Votero($page, "");
	return /*json_encode(*/$votero->getInitial($a);//);
}