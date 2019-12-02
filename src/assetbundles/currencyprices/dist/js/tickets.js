(function() {
	var $ticketsTable = $('#ticketsTable');

	if ($ticketsTable[0]) {
		$ticketsTable.find('.field[id$="-price-field"]').each(function() {
			var $this = $(this),
				id = $this
					.attr('id')
					.replace('tickets-', '')
					.replace('-price-field', '');
			$('#prices-' + id)
				.children()
				.each(function() {
					$this.append($(this).find('.input'));
					if ($(this).find('.errors')[0]) {
						$this.append($(this).find('.errors'));
					}
				});
		});

		$ticketsTable
			.parent()
			.find('> .btn.add')
			.on('click', function(e) {
				setTimeout(function() {
					var $new = $ticketsTable.find('[data-id=""]').last(),
						$field = $new.find('.field[id$="-price-field"]');

					$('#prices-new')
						.children()
						.each(function() {
							var $newField = $(this).clone();
							var html = $newField.html();
							html = html.replace(new RegExp('new', 'g'), $new.data('id'));
							$field.append($newField.html(html).find('.input'));
						});
				}, 50);
			});

		//$('[name="action"]').val('commerce-currency-prices/tickets/save');
	}
})();
