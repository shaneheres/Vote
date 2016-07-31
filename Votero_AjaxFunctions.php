<?php
// Called by javascript.
// Places a vote, if the user is allowed and the attribute exists.
$wgAjaxExportList[] = 'wfVoteroClick';
function wfVoteroClick($pageID, $attributeID, $vote) {
	global $wgUser;
	
	// User pased an illegal page/attribute id.
	if (!is_numeric($pageID) or !is_numeric($attributeID) or !is_numeric($vote) or $pageID == -1 or $attributeID == -1) {
		return json_encode(array(is_numeric($pageID), is_numeric($attributeID), is_numeric($vote), $pageID, $attributeID));
	}
	
	// User doesn't have that rite.
	if (!$wgUser->isAllowed('votero')) {
		return "not allowed to vote";
	}
	
	// Attribute doesn't exist.
	$attribute = Votero::GetAttributeName($attributeID);
	if ($attribute == "false") {
		return "{$attribute} isn't an allowable attribute.";
	}
	
	// Place vote and return error message, if any.
	$votero = new Votero($pageID, $attribute);
	return json_encode($votero->placeVote($vote));
}

// Called in Special:Votero
$wgAjaxExportList[] = 'wfVoteroSpecialUpdateA';
function wfVoteroSpecialUpdateA($attribute) {
	global $wgUser, $wgVoteroAttributes;
	
	if (!in_array($attributeName, array_keys($wgVoteroAttributes)))
		return "{$attribute} isn't an allowable attribute.";
	
	if (!$wgUser->isAllowed('voteroadmin'))
		return "You don't have permission for that.";
	
	return Votero::UpdateAttribute($attribute);
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