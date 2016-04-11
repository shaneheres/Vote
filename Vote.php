<?php
// Extension credits that show up on Special:Version
$wgExtensionCredits['semantic'][] = array(
	'name' => "Vote",
	'url' => "https://github.com/shaneheres/Vote",
	'description' => 'Allows users to vote on attributes.',
	'version' => '0.1',
	'author' => "[https://github.com/shaneheres/ Shane Heres]",
);

// Path to Vote extension files
$wgVoteDirectory = "$IP/extensions/Vote";

// Semantic MediaWiki tie in.
$wgVoteSMW = true;

// Rating modes.
$wgVoteAttributes = array(
	'Favorite' => array('id' => 0, 'style' => 'Favorite'),
	'Rating' => array('id' => 1, 'style' => 'Nero')
);

$wgVoteStyles = array();
$wgVoteStyles['Favorite'] = array(
	array(
		'title'=>'Favorite',
		'class'=>'fa fa-star',
		'color'=>'vote-yellow')
);

$wgVoteStyles['Nero'] = array(
	array(
		'title'=>'Thumbs Up',
		'class'=>'fa fa-thumbs-up',
		'color'=>'vote-green'),
	array(
		'title'=>'Thumbs Down',
		'class'=>'fa fa-thumbs-down',
		'color'=>'vote-red')
);

// New user right
$wgAvailableRights[] = 'vote';
$wgGroupPermissions['*']['vote'] = false; // Anonymous users cannot vote
$wgGroupPermissions['user']['vote'] = true; // Registered users can vote
// Load files.
require_once 'Vote_AjaxFunctions.php';
$wgExtensionMessagesFiles['Vote'] = __DIR__ . '/Vote.i18n.php';
$wgAutoloadClasses['Vote'] = __DIR__ . '/VoteClass.php';
// Parser hook.
$wgHooks['ParserFirstCallInit'][] = 'onParserSetup';

function onParserSetup( &$parser ) {
	$parser->setFunctionHook( 'vote', 'renderVote' );
	return;
}

function renderVote($parser, $attribute) {
	global $wgOut;
	
	$error = Vote::KeyAttributeExists($attribute);
	if ($error != "true")
		return $error;
	
	wfProfileIn(__METHOD__);
	$vote = new Vote($wgOut->getTitle()->getArticleID(), $attribute);
	$output = $vote->display($parser);
	wfProfileOut(__METHOD__);
	
	return array($output, 'noparse' => true, 'isHTML' => true);
}

// ResourceLoader support for MediaWiki 1.17+
$wgResourceModules['ext.vote.styles'] = array(
	'styles' => 'Vote.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Vote',
	'position' => 'top' // available since r85616
);

$wgResourceModules['ext.vote.scripts'] = array(
	'scripts' => 'Vote.js',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Vote'
);
