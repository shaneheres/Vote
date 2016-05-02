<?php
// Called by javascript.
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

// Called in Special:Votero
$wgAjaxExportList[] = 'wfVoteroSpecialUpdateA';
function wfVoteroSpecialUpdateA($attributeName) {
	global $wgUser, $wgVoteroAttributes;
	
	if (!in_array($attributeName, array_keys($wgVoteroAttributes)))
		return "{$attributeName} isn't an allowable attribute.";
	
	if (!$wgUser->isAllowed('voteroadmin'))
		return "You don't have permission for that.";
	
	return Votero::UpdateAttribute($attributeName);
}

// Called in Special:Votero
$wgAjaxExportList[] = 'wfVoteroSpecialUpdateP';
function wfVoteroSpecialUpdateP($pageID) {
	global $wgUser;
	
	if (!is_numeric($pageID))
		return "{$pageID} isn't an allowable page.";
	
	if (!$wgUser->isAllowed('voteroadmin'))
		return "You don't have permission for that.";
	
	return Votero::UpdatePageSemanticData($pageID);
}