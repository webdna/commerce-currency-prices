(function() {
	var digitalProducts = $('input[value="digital-products/products/save"]');

	var id = $('[name="productId"]').val();

	Craft.postActionRequest(
		'commerce-currency-prices/prices/get-purchasable-prices',
		{
			id: id
		},
		function(response, status) {
			$('#price-field').after($(response.html));
		}
	);
})();
