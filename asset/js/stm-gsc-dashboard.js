(function($) {
  'use strict';

  function injectDashboardButton() {
    var $anchor;
    var $dashboard;

    if (!window.stmGscDashboard || !stmGscDashboard.url || $('.stm-gsc-dashboard-btn').length) {
      return;
    }

    $dashboard = $('.tutor-dashboard, .tutor-dashboard-content').first();

    if (!$dashboard.length) {
      return;
    }

    $anchor = $dashboard.find('.tutor-dashboard-create-course').last();

    if (!$anchor.length) {
      $anchor = $dashboard.find('a[href*="create-course"]').last();
    }

    if (!$anchor.length) {
      $anchor = $dashboard.find('.tutor-btn').filter(function() {
        return $(this).text().toLowerCase().indexOf('course') !== -1;
      }).last();
    }

    if (!$anchor.length) {
      $('<div>', { class: 'stm-gsc-dashboard-row' }).append(
        $('<a>', {
          href: stmGscDashboard.url,
          class: 'tutor-btn tutor-btn-outline-primary stm-gsc-dashboard-btn',
          target: '_blank',
          rel: 'noopener noreferrer',
          text: stmGscDashboard.buttonLabel || 'Open GSC'
        })
      ).prependTo($dashboard);

      return;
    }

    $('<a>', {
      href: stmGscDashboard.url,
      class: 'tutor-btn tutor-btn-outline-primary stm-gsc-dashboard-btn',
      target: '_blank',
      rel: 'noopener noreferrer',
      text: stmGscDashboard.buttonLabel || 'Open GSC'
    }).insertAfter($anchor);
  }

  $(document).ready(function() {
    var tries = 0;
    var timer;

    injectDashboardButton();

    timer = window.setInterval(function() {
      injectDashboardButton();
      tries += 1;

      if (tries >= 20) {
        window.clearInterval(timer);
      }
    }, 400);

    if (window.MutationObserver) {
      new MutationObserver(injectDashboardButton).observe(document.body, {
        childList: true,
        subtree: true
      });
    }
  });
}(jQuery));
