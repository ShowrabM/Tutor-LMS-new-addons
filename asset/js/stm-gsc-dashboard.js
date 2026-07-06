(function($) {
  'use strict';

  function getDashboardRoot() {
    return $('.tutor-dashboard, .tutor-dashboard-content').first();
  }

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

  function getMyCoursesSection($dashboard) {
    var $section = $();

    $dashboard.find('*').each(function() {
      var $el = $(this);
      var text = ($el.text() || '').trim().toLowerCase();

      if (text === 'my courses') {
        $section = $el.closest('section, article, .tutor-dashboard-content, .tutor-col-12, .tutor-row, .tutor-wrap, div');
        if ($section.length) {
          return false;
        }
      }
    });

    if ($section.length) {
      return $section;
    }

    return $dashboard.find('.tutor-dashboard-content, .tutor-dashboard-card, .tutor-my-courses, .tutor-course-card').first();
  }

  function injectCourseSearch() {
    var $dashboard;
    var $section;
    var $header;
    var $wrap;

    if (window.stmTutorDashboardSearch || !window.stmGscDashboard || !stmGscDashboard.isInstructor || $('.stm-tutor-course-search-wrap').length) {
      return;
    }

    $dashboard = getDashboardRoot();
    if (!$dashboard.length) {
      return;
    }

    $section = getMyCoursesSection($dashboard);
    if (!$section.length) {
      return;
    }

    $header = $section.find('h1, h2, h3, .tutor-dashboard-title, .tutor-fs-5').first();
    if (!$header.length) {
      return;
    }

    if ($header.closest('.stm-tutor-course-search-host').length) {
      return;
    }

    $wrap = $('<div>', {
      class: 'stm-tutor-course-search-wrap'
    }).append(
      $('<label>', {
        class: 'stm-tutor-course-search-label',
        text: 'Search courses'
      }).append(
        $('<input>', {
          type: 'search',
          class: 'stm-tutor-course-search',
          placeholder: 'Search courses',
          autocomplete: 'off'
        })
      )
    );

    $wrap.insertAfter($header);
  }

  function getCourseCards($dashboard) {
    return $dashboard.find([
      '.tutor-dashboard-course-card',
      '.tutor-dashboard-course-item',
      '.tutor-course-card',
      '.tutor-mycourse-item',
      '.tutor-my-course-item',
      '.tutor-course-list-item',
      '.tutor-course-item'
    ].join(','));
  }

  function filterDashboardCourses(query) {
    var $dashboard = getDashboardRoot();
    var q = (query || '').trim().toLowerCase();
    var $cards = getCourseCards($dashboard);

    if (!$cards.length) {
      return;
    }

    $cards.each(function() {
      var $card = $(this);
      var haystack = ($card.text() || '').toLowerCase();
      var match = !q || haystack.indexOf(q) !== -1;

      $card.toggle(match);
    });

    $dashboard.find('.stm-tutor-course-search-empty').remove();

    if (q && !$cards.filter(':visible').length) {
      $('<p>', {
        class: 'stm-tutor-course-search-empty',
        text: 'No courses found.'
      }).insertAfter($dashboard.find('.stm-tutor-course-search-wrap').last());
    }
  }

  $(document).ready(function() {
    var tries = 0;
    var timer;

    injectDashboardButton();
    injectCourseSearch();

    timer = window.setInterval(function() {
      injectDashboardButton();
      injectCourseSearch();
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

      new MutationObserver(injectCourseSearch).observe(document.body, {
        childList: true,
        subtree: true
      });
    }

    $(document).on('input', '.stm-tutor-course-search', function() {
      filterDashboardCourses($(this).val());
    });
  });
}(jQuery));
