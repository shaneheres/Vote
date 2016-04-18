# Votero version 0.2
Customizable voting extension for MediaWiki. Results can be queryed with Semantic MediaWiki extension. (Though SMW is not required.)<br>
Original code based off the VoteNY extension.

## .2
* Renamed to Votero to prevent scripting conflicts.
* Added #voteroquerry to list pages sorted on attribute by bayesian rating. (No need for SMW.)
* New ways to display ratings.
  * 'underneath' Numbers below. (More condensed overall.)
  * 'labels' Numbers and text below. (No need for mouseover.)
  * 'stars'. The traditional stars system. (Selecting one highlights the buttons before.)
* Button colors changed to hex values instead of classes.
* Can set a backing icon. (Shown when button is not selected.)
* Stats load through js, so pages can be cached and show up to date results.
* Styles can have their averages stored in reverse. (25% -> 75%)

<br><br>
## Some of the bars you can create.
![Alt text](/Untitled.png?raw=true "Optional Title")

## How to install.
* You will need an icon font like [Font Awesome](http://fortawesome.github.io/Font-Awesome/) or [Glypicons](http://glyphicons.bootstrapcheatsheets.com/)
* Add Votero folder to your mediawiki/extensions/ folder.
* Add table 'votero' to mysql database:
```sql
CREATE TABLE `votero` (
  `id` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `page` int(11) NOT NULL,
  `attribute` int(11) NOT NULL,
  `vote` int(11) NOT NULL,
  `date` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `votero`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vote_page_id_index` (`page`),
  ADD KEY `valueidx` (`attribute`),
  ADD KEY `vote_date` (`date`);

ALTER TABLE `votero`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
```
* Add table 'votero_scores' to mysql database:
```sql
CREATE TABLE `votero_scores` (
  `id` int(11) NOT NULL,
  `page` int(11) NOT NULL,
  `attribute` int(11) NOT NULL,
  `average` decimal(7,4) NOT NULL,
  `total_votes` int(11) NOT NULL,
  `bayes` decimal(7,4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `votero_scores`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `votero_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
```
* Add to LocalSettings.php
```php
require_once "$IP/extensions/Votero/Votero.php";
// Semantic MediaWiki tie in.
$wgVoteroSMW = false;
// Maximum pages to show in a query.
$wgVoteroQueryLimit = 10;
```

## How to use in wiki
```wiki
<!-- To create an input field -->
{{#votero:Favorite}}{{#votero:Rating}}

<!-- To create a query list -->
{{#voteroquery:Rating}}
```

## How to add more attributes.
The extension only has two pre made attributes that can be voted on: Favorite and Rating.<br>
To add (or remove) more attributes, modify $wgVoteroAttributes in LocalSettings.php
```php
// 'id' must be a unique integer, and should never be changed.
$wgVoteroAttributes['Readability'] = array('id'=>10, 'style'=>'Nero');
$wgVoteroAttributes['Random Attribute'] = array('id'=>11, 'style'=>'Nero');
$wgVoteroAttributes['Favorite 2'] = array('id'=>100, 'style'=>'Favorite');
```

## How to add more styles.
The extension has two pre made styles that can be used: Favorite (a single star icon) and Nero (a thumbs up & thumbs down icon).<br>
To add (or remove) more styles, modify $wgVoteroStyles in LocalSettings.php
```php
$wgVoteroStyles['Faces']['display'] = "labels";
$wgVoteroStyles['Faces']['buttons'] = array(
	array(
		'title'=>'Great',
		'class'=>'emo emo-heartdowneyes',
		/* Optional class to show when unselected. Good for Star bars.
		'classBack'=>'fa fa-star-o',
		*/
		'color'=>'#75caeb'),
	array(
		'title'=>'Good',
		'class'=>'emo emo-blush',
		'color'=>'#75caeb'),
	array(
		'title'=>'Nothing',
		'class'=>'emo emo-neutraldownface',
		'color'=>'#75caeb'),
	array(
		'title'=>'Bad',
		'class'=>'emo emo-confused',
		'color'=>'#75caeb'),
	array(
		'title'=>'Awful',
		'class'=>'emo emo-angry',
		'color'=>'#75caeb')
);
```
## Semantic MediaWiki.
When any user makes a vote, the data gets edited (hacked) into the bottom of the page, like so:
```php
<!--Results from Votero extension for use by Semantic MediaWiki extension.-->
<!--Votero-Feeling-->{{#set:Feeling=75.00|Feeling votes=1}}<!--end-->
<!--Votero-Favorite-->{{#set:Favorite=100.00|Favorite votes=1}}<!--end-->
<!--Votero-Rating-->{{#set:Rating=100.00|Rating votes=1}}<!--end-->
```
It's invisible to readers, but page editor could modify it, though it would be reset whenever someone else voted.<br>
Currently you can only querry the data with SMW. They have lot's of useful [query formats](https://www.semantic-mediawiki.org/wiki/Help:Result_formats) including a [Custom template](https://www.semantic-mediawiki.org/wiki/Help:Template_format) format.<br>
To querry with SMW:
```wiki
{{#ask:
[[Rating::+]]
|?Rating
|?Rating votes
}}
```

