<?php
// Extension credits that show up on Special:Version
$wgExtensionCredits['parserhook'][] = array(
	'name' => "Votero",
	'url' => "https://github.com/tibbaroen/Votero",
	'description' => 'Allow users to vote on attributes.',
	'version' => '0.2'
);

// Path to Vote extension files
$wgVoteroDirectory = "$IP/extensions/Votero";

// Semantic MediaWiki tie in.
$wgVoteroSMW = false;
// Maximum pages to show in a query.
$wgVoteroQueryLimit = 10;

// Rating modes.
$wgVoteroAttributes = array(
	'Favorite' => array('id' => 0, 'style' => 'Favorite'),
	'Rating' => array('id' => 1, 'style' => 'Nero'),
	'Stars' => array('id' => 2, 'style' => 'Stars')
);

$wgVoteroStyles = array();
$wgVoteroStyles['Favorite']['buttons'] = array(
	array(
		'title'=>'Favorite',
		'class'=>'fa fa-star',
		'color'=>'#fd9741')
);

$wgVoteroStyles['Nero']['reverse'] = 'true';
$wgVoteroStyles['Nero']['buttons'] = array(
	array(
		'title'=>'Thumbs Up',
		'class'=>'fa fa-thumbs-up',
		'color'=>'#4ad0a1'),
	array(
		'title'=>'Thumbs Down',
		'class'=>'fa fa-thumbs-down',
		'color'=>'#fb756e')
);

$wgVoteroStyles['Stars']['display'] = 'stars';
$wgVoteroStyles['Stars']['buttons'] = array(
	array(
		'title'=>'Awful',
		'class'=>'fa fa-star',
		'classBack'=>'fa fa-star-o',
		'color'=>'#fd9741'),
	array(
		'title'=>'Bad',
		'class'=>'fa fa-star',
		'classBack'=>'fa fa-star-o',
		'color'=>'#fd9741'),
	array(
		'title'=>'Nothing',
		'class'=>'fa fa-star',
		'classBack'=>'fa fa-star-o',
		'color'=>'#fd9741'),
	array(
		'title'=>'Good',
		'class'=>'fa fa-star',
		'classBack'=>'fa fa-star-o',
		'color'=>'#fd9741'),
	array(
		'title'=>'Great',
		'class'=>'fa fa-star',
		'classBack'=>'fa fa-star-o',
		'color'=>'#fd9741')
);

// New user right
$wgAvailableRights[] = 'votero';
$wgGroupPermissions['*']['votero'] = false; // Anonymous users cannot vote.
$wgGroupPermissions['user']['votero'] = true; // Registered users can vote.

// Load files.
require_once 'Votero_AjaxFunctions.php';
$wgExtensionMessagesFiles['Votero'] = __DIR__ . '/Votero.i18n.php';
$wgAutoloadClasses['Votero'] = __DIR__ . '/VoteroClass.php';
// Parser hook.
$wgHooks['ParserFirstCallInit'][] = 'onParserSetup';

function onParserSetup( &$parser ) {
	$parser->setFunctionHook('votero', 'getVoteroInputHTML');
	$parser->setFunctionHook('voteroquery', 'getVoteroQueryHTML');
	return;
}
function getVoteroInputHTML($parser, $attribute) {
	global $wgOut;
	
	//$output = "<iframe src='http://www.w3schools.com' scrolling=no frameborder=0 width=200 height=200></iframe>";
	//return array($output, 'noparse' => true, 'isHTML' => true);
	
	$error = Votero::KeyAttributeExists($attribute);
	if ($error != "true")
		return $error;
	$votero = new Votero($wgOut->getTitle()->getArticleID(), $attribute);
	return array($votero->display($parser), 'noparse' => true, 'isHTML' => true);
}
function getVoteroQueryHTML($parser, $attribute) {
	
	$error = Votero::KeyAttributeExists($attribute);
	if ($error != "true")
		return $error;
	
	$votero = new Votero("", $attribute);
	return array($votero->displayQuery($parser), 'noparse' => true, 'isHTML' => true);
}

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.votero.styles'] = array(
	'styles' => 'Votero.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Votero',
	'position' => 'top' // available since r85616
);

$wgResourceModules['ext.votero.scripts'] = array(
	'scripts' => 'Votero.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Votero'
);
