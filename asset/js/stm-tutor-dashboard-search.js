(function($) {
  'use strict';

  var suggestionTimer;

  function getCourseCards() {
    return $('.tutor-dashboard-my-courses .tutor-card.tutor-course-card');
  }

  function filterCourses(query) {
    var q = (query || '').trim().toLowerCase();
    var $cards = getCourseCards();
    var visibleCount = 0;
    var $empty = $('.stm-tutor-course-search-empty');

    $cards.each(function() {
      var $card = $(this);
      var text = ($card.find('.tutor-course-name').first().text() || '').toLowerCase();
      var match = !q || text.indexOf(q) !== -1;

      $card.toggle(match);

      if (match) {
        visibleCount += 1;
      }
    });

    if (q && visibleCount === 0) {
      if (!$empty.length) {
        $('<p>', {
          class: 'stm-tutor-course-search-empty',
          text: 'No courses found.'
        }).insertAfter('.stm-tutor-course-search-wrap');
      }
    } else {
      $empty.remove();
    }
  }

  function addDashboardSearch() {
    var settings = window.stmTutorDashboardSearch || {};
    var $actions;
    var $createButton;
    var $form;

    if (!settings.showHeaderSearch || !settings.myCoursesUrl || $('.stm-dashboard-course-search-form').length) {
      return;
    }

    $actions = $('.tutor-header-right-side .tutor-d-flex').first();
    if (!$actions.length) {
      return;
    }

    $createButton = $actions.find('.tutor-dashboard-create-course, .tutor-create-new-course, a[href*="create-course"]').first();
    $form = $('<form>', {
      class: 'stm-dashboard-course-search-form',
      action: settings.myCoursesUrl,
      method: 'get',
      role: 'search'
    }).append(
      $('<button>', {
        type: 'button',
        class: 'tutor-btn tutor-btn-outline-primary stm-dashboard-course-search-toggle',
        'aria-expanded': 'false',
        'aria-controls': 'stm-dashboard-course-search-panel'
      }).append(
        $('<span>', { class: 'tutor-icon-search', 'aria-hidden': 'true' }),
        $('<span>', { text: 'Search Courses' })
      ),
      $('<div>', {
        id: 'stm-dashboard-course-search-panel',
        class: 'stm-dashboard-course-search-panel'
      }).append(
        $('<div>', { class: 'stm-dashboard-course-search-field' }).append(
          $('<label>', {
            class: 'screen-reader-text',
            for: 'stm-dashboard-course-search-input',
            text: 'Search courses'
          }),
          $('<input>', {
            type: 'search',
            id: 'stm-dashboard-course-search-input',
            class: 'stm-dashboard-course-search-input',
            name: settings.searchParam || 'stm_course_search',
            placeholder: 'Type a course name...',
            autocomplete: 'off',
            'aria-autocomplete': 'list',
            'aria-controls': 'stm-dashboard-course-suggestions',
            required: true
          }),
          $('<button>', {
            type: 'submit',
            class: 'tutor-btn tutor-btn-primary stm-dashboard-course-search-submit',
            text: 'Search'
          })
        ),
        $('<div>', {
          id: 'stm-dashboard-course-suggestions',
          class: 'stm-dashboard-course-suggestions',
          role: 'listbox',
          'aria-live': 'polite'
        })
      )
    );

    if ($createButton.length) {
      $form.insertAfter($createButton);
    } else {
      $actions.append($form);
    }
  }

  $(document).ready(function() {
    addDashboardSearch();

    if ($('.tutor-dashboard-my-courses').length && $('#stm-tutor-course-search').length) {
      filterCourses($('#stm-tutor-course-search').val());
    }

    $(document).on('input', '#stm-tutor-course-search', function() {
      filterCourses($(this).val());
    });

    $(document).on('click', '.stm-dashboard-course-search-toggle', function() {
      var $button = $(this);
      var $form = $button.closest('.stm-dashboard-course-search-form');
      var isOpen = !$form.hasClass('is-open');

      $form.toggleClass('is-open', isOpen);
      $button.attr('aria-expanded', isOpen ? 'true' : 'false');
      if (isOpen) {
        $form.find('.stm-dashboard-course-search-input').trigger('focus');
      }
    });

    $(document).on('click', function(event) {
      if (!$(event.target).closest('.stm-dashboard-course-search-form').length) {
        $('.stm-dashboard-course-search-form').removeClass('is-open')
          .find('.stm-dashboard-course-search-toggle').attr('aria-expanded', 'false');
      }
    });

    $(document).on('input', '.stm-dashboard-course-search-input', function() {
      var $input = $(this);
      var $suggestions = $input.closest('.stm-dashboard-course-search-panel').find('.stm-dashboard-course-suggestions');
      var query = $.trim($input.val());
      var settings = window.stmTutorDashboardSearch || {};

      window.clearTimeout(suggestionTimer);
      if (query.length < 2 || !settings.ajaxUrl) {
        $suggestions.empty().removeClass('is-visible');
        return;
      }

      $suggestions.html('<div class="stm-dashboard-suggestion-message">Searching...</div>').addClass('is-visible');
      suggestionTimer = window.setTimeout(function() {
        $.get(settings.ajaxUrl, {
          action: 'stm_instructor_course_suggestions',
          nonce: settings.nonce,
          query: query
        }).done(function(response) {
          var items = response && response.success ? response.data : [];
          $suggestions.empty();

          if (!items.length) {
            $suggestions.html('<div class="stm-dashboard-suggestion-message">No courses found.</div>');
            return;
          }

          $.each(items, function(index, course) {
            $('<a>', {
              class: 'stm-dashboard-course-suggestion',
              href: course.url,
              role: 'option'
            }).append(
              $('<span>', { class: 'stm-dashboard-suggestion-title', text: course.title }),
              $('<span>', { class: 'stm-dashboard-suggestion-status', text: course.status })
            ).appendTo($suggestions);
          });
        }).fail(function() {
          $suggestions.html('<div class="stm-dashboard-suggestion-message">Could not load courses.</div>');
        });
      }, 250);
    });

    $(document).on('submit', '.stm-dashboard-course-search-form', function(event) {
      var firstSuggestion = $(this).find('.stm-dashboard-course-suggestion').first().attr('href');
      if (firstSuggestion) {
        event.preventDefault();
        window.location.href = firstSuggestion;
      }
    });
  });
}(jQuery));
