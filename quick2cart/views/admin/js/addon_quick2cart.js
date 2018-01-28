/*
 * This file is part of the JReviews Quick2Cart Add-on
 *
 * Copyright (C) ClickFWD LLC 2010-2018 <sales@jreviews.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!window.jreviews) {
	jreviews = {};
}

if (!window.jreviews.quick2cart) {
	jreviews.quick2cart = {};
}

(function($, jreviews, window, undefined) {

	jreviews.quick2cart.init = function() {

		var page = jrPage;

		/*
		If we wanted to load some admin javascript for the add-on we would initialize it here
		 */

		page.on('click','.jr-q2c-remove-price,.jr-q2c-remove-discount-price',function(e) {

			e.preventDefault();

			$(this).parent().parent().remove();

			jreviews.quick2cart.recalcPriceRowIndex(page, $(this).data('type'));
		});

		page.on('click','.jr-q2c-new-price,.jr-q2c-new-discount-price',function(e) {

			e.preventDefault();

			var el = $(this),
				type = el.data('type');

			var currentRowDiv = el.parent().parent();

			var removeRowButton = $('<button class="jr-q2c-remove-' + type + ' jrButton jrIconOnly"><span class="jrIconRemove"></span></button>').data('type',type);

			var clonedRow = currentRowDiv
				.prev()
				.clone()
				.find(':input').val('').removeClass('jr-ready')
			.end()
			.insertBefore(currentRowDiv);

			removeRowButton.on('click',function(e) {

				e.preventDefault();

				clonedRow.remove();

				jreviews.quick2cart.recalcPriceRowIndex(page, type);
			});

			clonedRow
				.children('div').eq(2).html('');

			clonedRow
				.find('.jr-q2c-remove-' + type).remove()
				.end()
				.children('div').eq(1).append(removeRowButton);

			jreviews.quick2cart.recalcPriceRowIndex(page, type);

			jreviews.field.suggest(page);
		});
	};

	jreviews.quick2cart.recalcPriceRowIndex = function(page, type) {

		page
			.find('.jr-'+type+'-currency').parent().parent()
			.each(function(index,value){
				$(this).find(':input').each(function() {
					this.name = this.name.replace(/\[(\d+)\]/, '[' + (parseInt(index,10)) + ']');
				});
			});
	};

	jreviews.addOnload('quick2cart-init',	jreviews.quick2cart.init);

})(jQuery, jreviews , window);