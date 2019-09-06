(function() {
	if ($('body').hasClass('commerceaddons') && !$('body').hasClass('currency-prices')) {
		$('body').addClass('currency-prices');

		var fields = ['perItemDiscount'];
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
					'commerce-currency-prices/addons/get-inputs',
					{
						id: id,
						name: name + 'CP',
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
			$('[name="action"]').val('commerce-currency-prices/addons/save');
		}
	}
})();
