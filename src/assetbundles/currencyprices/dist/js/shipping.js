(function() {
	if ($('body').hasClass('commerce') && !$('body').hasClass('currency-prices')) {

		var fields = ['minTotal', 'maxTotal', 'baseRate', 'minRate', 'maxRate', 'perItemRate', 'weightRate', 'percentageRate'];
		var found = 0;
		var loaded = 0;
		var id = $('[name="id"]').val();

		$.each(fields, function() {
			var name = this;
			var $el = $('input[name="' + name + '"]');
			if ($el[0]) {
				found++;
				if (found == 1) {
					$('#header')
						.find('.submit')
						.prop('disabled', true);
					$('body')
						.addClass('currency-prices');
				}

				Craft.postActionRequest(
					'commerce-currency-prices/shipping/get-inputs',
					{
						id: id,
						name: name + 'CP',
						label: $el
							.closest('.field')
							.find('label')
							.text(),
						instructions: $el
							.closest('.field')
							.find('.instructions')
							.text()
					},
					function(response, status) {
						$el.closest('.field').replaceWith($(response.html));

						loaded++;
						if (loaded == found) {
							$('#header')
								.find('.submit')
								.prop('disabled', false);
						}
					}
				);
			}
		});

		$('#shipping-categories-rates tr[data-name]').each(function() {
			var $this = $(this),
				data = { id: id, name: $this.attr('data-name') };
			$this.find('input').each(function() {
				var $el = $(this);
				data[$el.attr('name')] = 0;
			});

			Craft.postActionRequest('commerce-currency-prices/shipping/get-category-inputs', data, function(response, status) {
				$this.replaceWith($(response.html));
			});
		});

		if (found) {
			$('[name="action"]').val('commerce-currency-prices/shipping/save');
		}
	}
})();
