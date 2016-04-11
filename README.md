# Vote
Customizable voting extension for MediaWiki, with results usable by the Semantic MediaWiki extension.

Image shows 3 different bars easily created with Vote extension.<br>
![Alt text](/Untitled.png?raw=true "Optional Title")

## How to install
* Add Vote folder to your mediawiki/extensions/ folder.
* Add table to mysql database:
```mysql
CREATE TABLE /*_*/vote (
  -- meh
  `incremental` int(11) NOT NULL auto_increment PRIMARY KEY,
  -- User ID
  `id_user` int(11) NOT NULL default '0',
  -- ID of the page
  `id_page` int(11) NOT NULL default '0',
  -- ID of the attribute
  `id_attribute` int(11) NOT NULL default '0',
  -- ID of the vote
  `id_vote` int(11) NOT NULL default '0',
  -- Timestamp when the vote was cast
  `vote_date` datetime NOT NULL default CURRENT_TIMESTAMP,
) /*$wgDBTableOptions*/;

CREATE INDEX vote_page_id_index ON /*_*/vote (id_page);
CREATE INDEX valueidx ON /*_*/vote (id_attribute);
CREATE INDEX vote_date ON /*_*/vote (vote_date);
```
* Add to LocalSettings.php
```php
require_once "$IP/extensions/Vote/Vote.php";
// $wgVoteSMW = false; /*uncomment to disable semantic option*/
```

## How to use in wiki
```wiki
{{#vote:Favorite}}{{#vote:Rating}}
```

## How to add more attributes
The extension only has two pre made attributes that can be voted on: Favorite and Rating.<br>
To add (or remove) more attributes, modify $wgVoteAttributes in LocalSettings.php
```php
// 'id' must be a unique integer, and should never be changed.
$wgVoteAttributes['Readability'] = array('id'=>10, 'style'=>'Nero');
$wgVoteAttributes['Random Attribute'] = array('id'=>11, 'style'=>'Nero');
$wgVoteAttributes['Favorite 2'] = array('id'=>11, 'style'=>'Favorite');
```

## How to add more styles
The extension has two pre made styles that can be used: Favorite (a single star icon) and Nero (a thumbs up icon and a thumbs down icon.<br>
To add (or remove) more styles, modify $wgVoteStyles in LocalSettings.php
```php
$wgVoteStyles['Faces'] = array( // Use a unique name for the style.
// Each button must have a 'title', 'class', and 'color' option.
	array(
		'title'=>'Great', // Text that shows up when this button is hovered over.
		'class'=>'emo emo-heartdowneyes', // An image class used for the button (Font Awesome, Glyphicons...)
		'color'=>'vote-green'), // A color class to use for select buttons. (vote-red, green, blue, and yellow are available. leave blank for black)
	array(
		'title'=>'Good',
		'class'=>'emo emo-blush',
		'color'=>'vote-blue'),
	array(
		'title'=>'Nothing',
		'class'=>'emo emo-neutraldownface',
		'color'=>'vote-blue'),
	array(
		'title'=>'Bad',
		'class'=>'emo emo-confused',
		'color'=>'vote-blue'),
	array(
		'title'=>'Awful',
		'class'=>'emo emo-angry',
		'color'=>'vote-red')
);
```
## Semantic MediaWiki
When any user makes a vote, the data gets edited (hacked) into the bottom of the page, like so:
```php
<!--Results from Vote extension for use by Semantic MediaWiki extension.-->
<!--Vote-Feeling-->{{#set:Feeling=75.00|Feeling votes=1}}<!--end-->
<!--Vote-Favorite-->{{#set:Favorite=100.00|Favorite votes=1}}<!--end-->
<!--Vote-Rating-->{{#set:Rating=100.00|Rating votes=1}}<!--end-->
```
It's invisible to readers, but page editor could modify it, though it would be reset whenever someone else voted.<br>
Currently you can only querry the data with SMW. I may add some options to the Vote extension later.<br>
To querry with SMW:
```wiki
{{#ask:
[[Rating::+]]
|?Rating
|?Rating votes
}}
```
