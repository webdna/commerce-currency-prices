(function() {
	if ($('body').hasClass('commerce')) {
		console.log('commerce settings');

		var fields = ['baseDiscount', 'perItemDiscount'];
		var found = 0;
		var loaded = 0;
		var id = $('[name="id"]').val();

		$.each(fields, function() {
			var name = this;
			var $el = $('#' + name + '-field');
			if ($el[0]) {
				found++;
				if (found == 1) {
					$('#header')
						.find('.submit')
						.prop('disabled', true);
				}

				Craft.postActionRequest(
					'commerce-currency-prices/discounts/get-inputs',
					{
						id: id,
						name: name,
						label: $el.find('label').text(),
						instructions: $el.find('.instructions').text()
					},
					function(response, status) {
						$el.find('[name="' + name + '"]')
							.parents('.field')
							.replaceWith($(response.html));

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

		if (found) {
			$('[name="action"]').val('commerce-currency-prices/discounts/save');
		}

		/*var $conditions = $('#conditions-tab');
		if ($conditions[0]) {
			console.log('shipping');

			$('#header')
				.find('.submit')
				.prop('disabled', true);

			$('[name="action"]').val('commerce-currency-prices/shipping/save');
			//
			//var values = $conditions.parents('form').serializeArray();
			var id = $('[name="id"]').val();

			Craft.postActionRequest('commerce-currency-prices/shipping/get-inputs', { id: id, name: 'minTotal' }, function(response, status) {
				$conditions
					.find('[name="minTotal"]')
					.parents('.field')
					.replaceWith($(response.html));
			});
			Craft.postActionRequest('commerce-currency-prices/shipping/get-inputs', { id: id, name: 'maxTotal' }, function(response, status) {
				$conditions
					.find('[name="maxTotal"]')
					.parents('.field')
					.replaceWith($(response.html));
			});
		}

		var $costs = $('#costs-tab');
		if ($costs[0]) {
			Craft.postActionRequest('commerce-currency-prices/shipping/get-inputs', { id: id, name: 'baseRate' }, function(response, status) {
				$costs
					.find('[name="baseRate"]')
					.parents('.field')
					.replaceWith($(response.html));
			});
			Craft.postActionRequest('commerce-currency-prices/shipping/get-inputs', { id: id, name: 'minRate' }, function(response, status) {
				$costs
					.find('[name="minRate"]')
					.parents('.field')
					.replaceWith($(response.html));
			});
			Craft.postActionRequest('commerce-currency-prices/shipping/get-inputs', { id: id, name: 'maxRate' }, function(response, status) {
				$costs
					.find('[name="maxRate"]')
					.parents('.field')
					.replaceWith($(response.html));
			});
			Craft.postActionRequest('commerce-currency-prices/shipping/get-inputs', { id: id, name: 'perItemRate' }, function(response, status) {
				$costs
					.find('[name="perItemRate"]')
					.parents('.field')
					.replaceWith($(response.html));
			});
			Craft.postActionRequest('commerce-currency-prices/shipping/get-inputs', { id: id, name: 'weightRate' }, function(response, status) {
				$costs
					.find('[name="weightRate"]')
					.parents('.field')
					.replaceWith($(response.html));
			});
			Craft.postActionRequest('commerce-currency-prices/shipping/get-inputs', { id: id, name: 'percentageRate' }, function(response, status) {
				$costs
					.find('[name="percentageRate"]')
					.parents('.field')
					.replaceWith($(response.html));

				$('#header')
					.find('.submit')
					.prop('disabled', false);
			});

			$costs.find('#shipping-categories-rates tr[data-name]').each(function() {
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
		}*/
	}
})();
