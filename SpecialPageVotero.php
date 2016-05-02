<?php
class SpecialPageVotero extends SpecialPage {
	function __construct() {
		parent::__construct( 'Votero' );
	}
	
	function execute( $par ) {
		global $wgVoteroAttributes;
		
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
		$output->addScript( '<script type="text/javascript">
			window.onload = function() {
				$(".update-attribute").click(function() { updateAttribute($(this).data("attribute")); });
				$(".update-page").click(function() { updatePage($(this).data("page")); });
			}
			
			updateAttribute = function(a) { $.post(mw.util.wikiScript(), { action:"ajax", rs:"wfVoteroSpecialUpdateA", rsargs: [a] }).done( function(d) {
				$(".ouput").html(d + "<br>" + $(".ouput").html());
			});};
			
			updatePage = function(p) { $.post(mw.util.wikiScript(), { action:"ajax", rs:"wfVoteroSpecialUpdateP", rsargs: [p] }).done( function(d) {
				$(".ouput").html(d + "<br>" + $(".ouput").html());
			});};
		</script>' );
		
		# Get request data from, e.g.
		$param = $request->getText( 'param' );
		
		$dbr = wfGetDB( DB_SLAVE );
		
		$wikitext = "<div class='col-md-4'><u><b>Attributes</b></u><br>";
		
		// Attributes to update.
		$attributes = array_keys($wgVoteroAttributes);
		for ($i = 0; $i < count($attributes); $i++) {
			$attribute = $attributes[$i];
			$id = $wgVoteroAttributes[$attribute]['id'];
			$total = $dbr->fetchObject($dbr->query("SELECT SUM(new_votes) new_votes FROM votero_scores WHERE attribute={$id}"));
			
			$text = $total->new_votes == null || $total->new_votes == 0 ? "" : " <a href='#' class='update-attribute' data-attribute='{$attribute}'>Update</a>";
			if ($text != "") {
				$wikitext .= "<br><b>{$attribute}</b>{$text}<br>";
				
				$pages = $dbr->query("SELECT page, new_votes FROM votero_scores WHERE attribute={$id} AND new_votes>0 ORDER BY new_votes DESC");
				foreach ($pages as $page) {
					$pageName = Title::newFromID($page->page);
					$text = $page->new_votes == null || $page->new_votes == 0 ? "" : " ({$page->new_votes} new) ";
					$wikitext .= "{$pageName}{$text}<br>";
				}
			}
		}
		$wikitext .= "</div><div class='col-md-4'>";
		$wikitext .= "<u><b>Pages</b></u><br>";
		
		// Pages to update.
		$pages = $dbr->query("SELECT page, new_votes FROM votero_scores WHERE attribute=-1 AND new_votes>0");
		foreach ($pages as $page) {
			$pageName = Title::newFromID($page->page);
			$wikitext .= "{$pageName} ({$page->new_votes} new) <a href='#' class='update-page' data-page='{$page->page}'>Update</a><br>";
		}
		
		$wikitext .= "</div><div class='col-md-4'><b><u>Output:</u></b><br><div class='ouput'></div></div>";
		
		$output->addHTML( $wikitext );
	}
}