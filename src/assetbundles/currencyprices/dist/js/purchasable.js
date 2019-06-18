(function() {
	var $price = $('#details .meta:not(.read-only) .field[id="price-field"]');
	if ($price[0]) {
		$('#prices')
			.children()
			.each(function() {
				$(this).insertAfter($price);
			});
	}
})();
