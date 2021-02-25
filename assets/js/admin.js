(function($) {
	var ZeroSpamAdmin = {
		init: function() {
			// Set active tab on the settings page.
			var $settingsContainer = $('.zerospam-settings-tabs');
			if ( $settingsContainer.length ) {
				this.openTab(1, $settingsContainer);

				$('h2', $settingsContainer).click(function(e) {
					e.preventDefault();

					const index = $settingsContainer.find('h2').index(this) + 1;
					ZeroSpamAdmin.openTab(index);
				});
			}
		},

		openTab: function( index, $container ) {
			$('.form-table', $container).removeClass('is-active');
			$('.h2', $container).removeClass('is-active');

			const $settingsTabContent = $('.form-table:nth-of-type(' + index + ')', $container);
			const $settingsTab = $('h2:nth-of-type(' + index + ')', $container);

			$settingsTabContent.addClass('is-active');
			$settingsTab.addClass('is-active');
		},

		prepopulateFields: function( $btn ) {
			var ip = $btn.data('ip');
			$('input[name="blocked_ip"]', $('.zerospam-modal-block')).val('');
			if ( ip ) {
				$('input[name="blocked_ip"]', $('.zerospam-modal-block')).val(ip);
			}

			var keyType = $btn.data('keytype');
			$('select[name="key_type"]', $('.zerospam-modal-block')).val('country_code');
			if ( keyType ) {
				$('select[name="key_type"]', $('.zerospam-modal-block')).val(keyType);
			}

			var blockedKey = $btn.data('blockedkey');
			$('input[name="blocked_key"]', $('.zerospam-modal-block')).val('');
			if ( blockedKey ) {
				$('input[name="blocked_key"]', $('.zerospam-modal-block')).val(blockedKey);
			}

			var reason = $btn.data('reason');
			$('input[name="blocked_reason"]', $('.zerospam-modal-block')).val('');
			if ( reason ) {
				$('input[name="blocked_reason"]', $('.zerospam-modal-block')).val(reason);
			}

			var type = $btn.data('type');
			$('select[name="blocked_type"]', $('.zerospam-modal-block')).val('temporary');
			if ( type ) {
				$('select[name="blocked_type"]', $('.zerospam-modal-block')).val(type);
			}

			var startDate = $btn.data('start');
			$('input[name="blocked_start_date"]', $('.zerospam-modal-block')).val('');
			if ( startDate ) {
				$('input[name="blocked_start_date"]', $('.zerospam-modal-block')).val(startDate);
			}

			var endDate = $btn.data('end');
			$('input[name="blocked_end_date"]', $('.zerospam-modal-block')).val('');
			if ( endDate ) {
				$('input[name="blocked_end_date"]', $('.zerospam-modal-block')).val(endDate);
			}
		}
	};

  $(function() {
		ZeroSpamAdmin.init();

    $( '.zerospam-details-trigger' ).click(function( e ) {
      e.preventDefault();
			$('.zerospam-modal').removeClass('is-active');

      var id = $(this).data('id');
      $('#zerospam-details-' + id).addClass('is-active');
    });

		$('.zerospam-close-modal').click(function(e) {
			e.preventDefault();
			$('.zerospam-modal').removeClass('is-active');
		});

		$('.zerospam-block-location-trigger').click(function(e) {
			e.preventDefault();

			ZeroSpamAdmin.prepopulateFields($(this));

			$('.zerospam-modal').removeClass('is-active');
			$('#zerospam-block-location').addClass('is-active');
		});

		$('.zerospam-block-trigger').click(function(e) {
			e.preventDefault();

			ZeroSpamAdmin.prepopulateFields($(this));

			$('.zerospam-modal').removeClass('is-active');
			$('#zerospam-block-ip').addClass('is-active');
		});

		$(document).on('keydown', function(e) {
			if(e.key == "Escape") {
				$('.zerospam-modal').removeClass('is-active');
			}
		});
  });
})(jQuery);
