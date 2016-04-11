var Vote = function Vote() {
	this.clickVote = function( a, v ) {
		$.post(
			mw.util.wikiScript(), {
				action: 'ajax',
				rs: 'wfVoteClick',
				rsargs: [ mw.config['values']['wgArticleId'], a, v ]
			}
		).done( function( d ) {
			console.log(d);
		} );
	};
};
function format_number(number) {
	if (number >= 9940) return Math.round(number / 1000) + "k";
	if (number >= 1000) return (number / 1000).toFixed(1) + "k";
	return number;
}

$( function() {
	var vote = new Vote();
	var canVote = true;
	
	$( 'body' ).on( 'click', '.vote-radio-button', function() {
		if (canVote == true)
		{
			canVote = false;
			setTimeout(function(){ canVote = true; }, 1000);
			
			var that = $(this);
			vote.clickVote(that.data('a'),that.data('v'));
            
			var previous = that.data('s');
			$( '.vote-radio-button' ).each(function()
			{
				if ($(this).data('a') == that.data('a') && $(this).data('s') == true)
				{
					$(this).data('s', false);
                    $(this).removeClass($(this).data('c'));
					$(this).removeClass("vote-selected").addClass("vote-unselected");
					
					var countID = "#vote_" + $(this).data('a') + "_" + $(this).data('v');
                    var count = parseInt($(countID).data('t')) - 1;
                    $(countID).data('t', count);
					$(countID).text(format_number(count));
				}
			});
			
			if (previous == false)
			{
				that.data('s', true);
				that.removeClass("vote-unselected").addClass("vote-selected");
				that.addClass(that.data('c'));
                
				var countID = "#vote_" + that.data('a') + "_" + that.data('v');
                var count = parseInt($(countID).data('t')) + 1;
                $(countID).data('t', count);
				$(countID).text(format_number(count));
			}
		}
	});
} );