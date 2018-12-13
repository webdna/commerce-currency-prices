console.log('Payment Currencies');

var $currenciesTable = $('table#currencies');
if ($currenciesTable[0]) {
	(function() {
		var timer = setInterval(function() {
			if (window.adminTable) {
				clearInterval(timer);
				window.adminTable.settings.deleteAction = 'currency-prices/payment-currencies/delete';
			}
		}, 50);
	})();
}

var $newCurrencyAction = $('input[value="commerce/payment-currencies/save"]');
if ($newCurrencyAction[0]) {
	$newCurrencyAction.val('currency-prices/payment-currencies/save');
}
