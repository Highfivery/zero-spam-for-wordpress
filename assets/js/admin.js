(function($) {
  $(function() {
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

		$('.zerospam-block-trigger').click(function(e) {
			e.preventDefault();

			var ip = $(this).data('ip');
			$('input[name="blocked_ip"]', $('#zerospam-block-ip')).val('');
			if ( ip ) {
				$('input[name="blocked_ip"]', $('#zerospam-block-ip')).val(ip);
			}

			var reason = $(this).data('reason');
			$('input[name="blocked_reason"]', $('#zerospam-block-ip')).val('');
			if ( reason ) {
				$('input[name="blocked_reason"]', $('#zerospam-block-ip')).val(reason);
			}

			var type = $(this).data('type');
			$('select[name="blocked_type"]', $('#zerospam-block-ip')).val('temporary');
			if ( type ) {
				$('select[name="blocked_type"]', $('#zerospam-block-ip')).val(type);
			}

			var startDate = $(this).data('start');
			$('input[name="blocked_start_date"]', $('#zerospam-block-ip')).val('');
			if ( startDate ) {
				$('input[name="blocked_start_date"]', $('#zerospam-block-ip')).val(startDate);
			}

			var endDate = $(this).data('end');
			$('input[name="blocked_end_date"]', $('#zerospam-block-ip')).val('');
			if ( endDate ) {
				$('input[name="blocked_end_date"]', $('#zerospam-block-ip')).val(endDate);
			}

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
