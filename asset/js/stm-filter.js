(function($) {
  'use strict';

  function updateResultCount($archive, count) {
    var suffix = count === 1 ? '' : 's';
    $archive.find('.stm-result-count').text(count + ' course' + suffix);
  }

  function initArchive($archive) {
    var initialCount = $archive.find('.stm-course-card').length;
    updateResultCount($archive, initialCount);
  }

  $(document).ready(function() {
    $('.stm-course-archive').each(function() {
      initArchive($(this));
    });

  $(document).on('click', '.stm-course-archive .stm-cat-link', function(e) {
      e.preventDefault();

      var $clicked = $(this);
      var $archive = $clicked.closest('.stm-course-archive');
      var $grid = $archive.find('.stm-course-grid');
      var catId = $clicked.data('cat-id');
      var catName = $clicked.find('.stm-cat-name').text().trim();
      var postsPerPage = $archive.data('posts-per-page');
      var includeCategories = $archive.data('include-categories');
      var excludeCategories = $archive.data('exclude-categories');
      var defaultTitle = $archive.data('default-title');

      $archive.find('.stm-cat-link').removeClass('active');
      $clicked.addClass('active');
      $archive.find('.stm-main-title').text(catId === 0 ? defaultTitle : catName);
      $grid.css({ opacity: 0.4, pointerEvents: 'none' });

      $.ajax({
        url: stmAjax.ajax_url,
        type: 'POST',
        data: {
          action: 'stm_filter_courses',
          nonce: stmAjax.nonce,
          category_id: catId,
          posts_per_page: postsPerPage,
          include_categories: includeCategories,
          exclude_categories: excludeCategories
        },
        success: function(res) {
          if (res.success) {
            $grid.html(res.data.html);
            updateResultCount($archive, res.data.count);
          }
        },
        error: function() {
          $grid.html('<p class="stm-no-courses">Something went wrong. Please try again.</p>');
          updateResultCount($archive, 0);
        },
        complete: function() {
          $grid.css({ opacity: 1, pointerEvents: 'auto' });
        }
      });
    });
  });
}(jQuery));
