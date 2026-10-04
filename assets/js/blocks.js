( function () {
	var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting = window.wc.wcSettings.getSetting;
	var el = window.wp.element.createElement;
	var decodeEntities = window.wp.htmlEntities.decodeEntities;

	var settings = getSetting( 'unicredit_pagonline_data', {} );
	var label = decodeEntities( settings.title || 'UniCredit PagOnline' );

	var Label = function () {
		return el( 'span', null, label );
	};

	var Content = function () {
		return el( 'div', null, decodeEntities( settings.description || '' ) );
	};

	registerPaymentMethod( {
		name: 'unicredit_pagonline',
		label: el( Label ),
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: label,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
