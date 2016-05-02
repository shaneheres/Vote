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
$wgVoteroSMW = true;
// Maximum pages to show in a query.
$wgVoteroQueryLimit = 50;
// Number of votes to accept before recalculating the averages. (For a small wiki this could probably stay pretty low.)
// This can be manually done at Special:Votero.
$wgVoteroUpdateAttribute = 1;
// Number of votes to accept before updating a pages semantic data. (For a small wiki this could probably stay pretty low.)
// This can be manually done at Special:Votero.
$wgVoteroUpdatePage = 1;

$wgVoteroAttributes = array(
// Rating modes.
	'Slider' => array('id' => 1000, 'style' => 'Slider', 'smw' => 'average AS percent, weighted AS normal, weighted AS class, average AS label_f, average AS label_c, votes AS format'),
	'Favorite' => array('id' => 0, 'style' => 'Favorite'),
	'Nero' => array('id' => 1, 'style' => 'Nero'),
	'Stars' => array('id' => 2, 'style' => 'Stars')
);
//SMW options: (default = 'average AS percent2, votes AS format')
//  votes = total votes for page attribute
//  votes_# (NOT IMPLEMENTED) = total votes for page attribute button
//  votes_all (NOT IMPLEMENTED) = all votes in comma seperated list for page attribute button. ie 4,10,23,100
//-
//  average = average vote for page attribute
//  weighted = weighted average for page attribute

//AS (only work for votes)
//  int = integer ie 1209515
//  format = comma seperated ie 1,209,515

//AS (only work for averages)
//  percent = ie 100 (no decimal)
//  percent1 = ie 100.0 (one decimal)
//  percent2 = ie 100.00 (two decimal)
//-
//  normal = ie 1.00
//-
//  index = ie 0 (rounded to nearest button index)
//  label = ie 'Thumbs Up' (rounded to nearest button label)
//  icon = ie 'fa fa-thumbs-up' (rounded to nearest button icon)
//-
//  index_f = ie 0 (floor to nearest button index)
//  label_f = ie 'Thumbs Up' (floor to nearest button label)
//  icon_f = ie 'fa fa-thumbs-up' (floor to nearest button icon)
//-
//  index_c = ie 0 (ceil to nearest button index)
//  label_c = ie 'Thumbs Up' (ceil to nearest button label)
//  icon_c = ie 'fa fa-thumbs-up' (ceil to nearest button icon)

$wgVoteroStyles = array();
$wgVoteroStyles['Slider']['display'] = 'range';
$wgVoteroStyles['Slider']['max'] = 10000;
$wgVoteroStyles['Slider']['step'] = 10;
$wgVoteroStyles['Slider']['width'] = '500px';
$wgVoteroStyles['Slider']['label'] = "Aprox MGSS.";
// SS = becomes 's if greater than one.
// VV = value with commas ie 1,000,000
// MCG = value in micrograms (mcg=0, mg=1000, g=1000000, kg=1000000000)
// MG = value in milligrams (mg=0, g=1000, kg=1000000)
$wgVoteroStyles['Slider']['buttons'] = array(
	array('label'=>'Awful', 'class'=>'emo emo-angry'),
	array('label'=>'Bad', 'class'=>'emo emo-confused'),
	array('label'=>'Nothing', 'class'=>'emo emo-neutraldownface'),
	array('label'=>'Good', 'class'=>'emo emo-blush'),
	array('label'=>'Great', 'class'=>'emo emo-heartdowneyes')
);

$wgVoteroStyles['Favorite']['width'] = '200px';
$wgVoteroStyles['Favorite']['size'] = '3em';
$wgVoteroStyles['Favorite']['color'] = '#fd9741';
$wgVoteroStyles['Favorite']['buttons'] = array(
	array('label'=>'Favorite', 'class'=>'fa fa-star')
);

$wgVoteroStyles['Nero']['reverse'] = 'true';
$wgVoteroStyles['Nero']['color'] = '#4ad0a1';
$wgVoteroStyles['Nero']['width'] = '4em';
$wgVoteroStyles['Nero']['buttons'] = array(
	array('label'=>'Thumbs Up', 'class'=>'fa fa-thumbs-up'),
	array('label'=>'Thumbs Down', 'class'=>'fa fa-thumbs-down', 'color'=>'#D8766E')
);

$wgVoteroStyles['Stars']['display'] = 'stars';
$wgVoteroStyles['Stars']['count display'] = 'off';
$wgVoteroStyles['Stars']['color'] = '#fd9741';
$wgVoteroStyles['Stars']['buttons'] = array(
	array('label'=>'Awful', 'class'=>'fa fa-star', 'classBack'=>'fa fa-star-o'),
	array('label'=>'Bad', 'class'=>'fa fa-star', 'classBack'=>'fa fa-star-o'),
	array('label'=>'Ok', 'class'=>'fa fa-star', 'classBack'=>'fa fa-star-o'),
	array('label'=>'Good', 'class'=>'fa fa-star', 'classBack'=>'fa fa-star-o'),
	array('label'=>'Great', 'class'=>'fa fa-star', 'classBack'=>'fa fa-star-o')
);

// User right: Can vote.
$wgAvailableRights[] = 'votero';
$wgGroupPermissions['*']['votero'] = false; // Anonymous users cannot vote.
$wgGroupPermissions['user']['votero'] = true; // Registered users can vote.
// User right: Can update vote averages from Special:Votero.
$wgAvailableRights[] = 'voteroadmin';
$wgGroupPermissions['*']['voteroadmin'] = false; // Anonymous users cannot.
$wgGroupPermissions['user']['voteroadmin'] = false; // Anonymous users cannot.
$wgGroupPermissions['sysop']['voteroadmin'] = true; // Admins can.

// Load files.
require_once 'Votero_AjaxFunctions.php';
$wgExtensionMessagesFiles['Votero'] = __DIR__ . '/Votero.i18n.php';
$wgAutoloadClasses['Votero'] = __DIR__ . '/VoteroClass.php';
$wgSpecialPages['Votero'] = 'SpecialPageVotero';
$wgAutoloadClasses['SpecialPageVotero'] = __DIR__ . '/SpecialPageVotero.php'; # Location of the SpecialMyExtension class (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['SpecialPageVotero'] = __DIR__ . '/SpecialPageVotero.alias.php';
// Parser hook.
$wgHooks['ParserFirstCallInit'][] = 'voteroOnParserSetup';

function voteroOnParserSetup( &$parser ) {
	$parser->setFunctionHook('votero', 'getInputHTML', SFH_NO_HASH);
	$parser->setFunctionHook('voteroquery', 'getQueryHTML', SFH_NO_HASH);
	return true;
}

function getInputHTML($parser, $attribute = '') {
	global $wgOut;
	
	// Check that attribute exists.
	$error = Votero::KeyAttributeExists($attribute);
	if ($error != "true")
		return $error;
	
	$votero = new Votero($wgOut->getTitle()->getArticleID(), $attribute);
	return array($votero->display($parser), 'noparse' => true, 'isHTML' => true);
}

function getQueryHTML($parser, $attribute = '', $options = '') {
	global $wgOut;
	
	// Check that attribute exists.
	$error = Votero::KeyAttributeExists($attribute);
	if ($error != "true")
		return $error;
	
	$votero = new Votero($wgOut->getTitle()->getArticleID(), $attribute);
	return array($votero->displayQuery($parser, extractOptions($options)), 'noparse' => true, 'isHTML' => true);
}

function extractOptions( $options ) {
	$options = explode(',', $options);
	$results = array();
	
	foreach ( $options as $option ) {
		$pair = explode( '=', $option, 2 );
		if ( count( $pair ) === 2 ) {
			$name = trim( $pair[0] );
			$value = trim( $pair[1] );
			$results[$name] = $value;
		}

		if ( count( $pair ) === 1 ) {
			$name = trim( $pair[0] );
			$results[$name] = true;
		}
	}
	return $results;
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
