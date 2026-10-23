(function ($) {
  var ZeroSpamAdmin = {
    prepopulateFields: function ($btn) {
      var ip = $btn.data("ip");
      $('input[name="blocked_ip"]', $(".zerospam-modal-block")).val("");
      if (ip) {
        $('input[name="blocked_ip"]', $(".zerospam-modal-block")).val(ip);
      }

      var keyType = $btn.data("keytype");
      $('select[name="key_type"]', $(".zerospam-modal-block")).val(
        "country_code"
      );
      if (keyType) {
        $('select[name="key_type"]', $(".zerospam-modal-block")).val(keyType);
      }

      var blockedKey = $btn.data("blockedkey");
      $('input[name="blocked_key"]', $(".zerospam-modal-block")).val("");
      if (blockedKey) {
        $('input[name="blocked_key"]', $(".zerospam-modal-block")).val(
          blockedKey
        );
      }

      var reason = $btn.data("reason");
      $('input[name="blocked_reason"]', $(".zerospam-modal-block")).val("");
      if (reason) {
        $('input[name="blocked_reason"]', $(".zerospam-modal-block")).val(
          reason
        );
      }

      var type = $btn.data("type");
      $('select[name="blocked_type"]', $(".zerospam-modal-block")).val(
        "temporary"
      );
      if (type) {
        $('select[name="blocked_type"]', $(".zerospam-modal-block")).val(type);
      }

      var startDate = $btn.data("start");
      $('input[name="blocked_start_date"]', $(".zerospam-modal-block")).val("");
      if (startDate) {
        $('input[name="blocked_start_date"]', $(".zerospam-modal-block")).val(
          startDate
        );
      }

      var endDate = $btn.data("end");
      $('input[name="blocked_end_date"]', $(".zerospam-modal-block")).val("");
      if (endDate) {
        $('input[name="blocked_end_date"]', $(".zerospam-modal-block")).val(
          endDate
        );
      }
    },
  };

  $(function () {
    // One overlay for every modal: it sits above the admin bar and the
    // list-table rows (see .zerospam-modal-overlay in admin.scss), so nothing
    // behind an open modal can be clicked, and clicking it closes the modal.
    var $overlay = $('<div class="zerospam-modal-overlay" aria-hidden="true"></div>').appendTo("body");

    function closeModals() {
      $(".zerospam-modal").removeClass("is-active");
      $overlay.removeClass("is-active");
    }

    function openModal($modal) {
      closeModals();
      $modal.addClass("is-active");
      $overlay.addClass("is-active");
    }

    $overlay.on("click", closeModals);

    $(".zerospam-details-trigger").click(function (e) {
      e.preventDefault();

      var id = $(this).data("id");
      openModal($("#zerospam-details-" + id));
    });

    $(".zerospam-close-modal").click(function (e) {
      e.preventDefault();
      closeModals();
    });

    $(".zerospam-block-location-trigger").click(function (e) {
      e.preventDefault();

      ZeroSpamAdmin.prepopulateFields($(this));

      openModal($("#zerospam-block-location"));
    });

    $(".zerospam-block-trigger").click(function (e) {
      e.preventDefault();

      ZeroSpamAdmin.prepopulateFields($(this));

      openModal($("#zerospam-block-ip"));
    });

    $(document).on("keydown", function (e) {
      if (e.key == "Escape") {
        closeModals();
      }
    });
  });
})(jQuery);

/**
 * Documentation accordion functionality
 */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    // Accordion toggles
    var accordions = document.querySelectorAll('.zerospam-docs-accordion-toggle');
    
    accordions.forEach(function (accordion) {
      accordion.addEventListener('click', function () {
        var parent = this.closest('.zerospam-docs-accordion');
        var isActive = parent.classList.contains('active');
        
        // Close all accordions
        document.querySelectorAll('.zerospam-docs-accordion').forEach(function (el) {
          el.classList.remove('active');
        });
        
        // Open clicked accordion if it wasn't active
        if (!isActive) {
          parent.classList.add('active');
        }
      });
    });

    // Copy code button functionality
    var copyButtons = document.querySelectorAll('.zerospam-docs-copy-btn');
    
    copyButtons.forEach(function (button) {
      button.addEventListener('click', function (e) {
        e.stopPropagation();
        
        var targetId = this.getAttribute('data-clipboard-target');
        var targetElement = document.getElementById(targetId);
        
        if (!targetElement) {
          return;
        }
        
        var textToCopy = targetElement.textContent;
        
        // Try modern clipboard API first
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(textToCopy).then(function () {
            showCopyFeedback(button);
          }).catch(function () {
            fallbackCopyToClipboard(textToCopy, button);
          });
        } else {
          fallbackCopyToClipboard(textToCopy, button);
        }
      });
    });

    function fallbackCopyToClipboard(text, button) {
      var textArea = document.createElement('textarea');
      textArea.value = text;
      textArea.style.position = 'fixed';
      textArea.style.left = '-999999px';
      textArea.style.top = '-999999px';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      
      try {
        document.execCommand('copy');
        showCopyFeedback(button);
      } catch (err) {
        console.error('Failed to copy:', err);
      }
      
      document.body.removeChild(textArea);
    }

    function showCopyFeedback(button) {
      var originalText = button.textContent;
      button.textContent = 'Copied!';
      button.style.background = '#69b86b';
      
      setTimeout(function () {
        button.textContent = originalText;
        button.style.background = '';
      }, 2000);
    }

    // Open first accordion by default on page load
    var firstAccordion = document.querySelector('.zerospam-docs-accordion');
    if (firstAccordion) {
      firstAccordion.classList.add('active');
    }
  });
})();
