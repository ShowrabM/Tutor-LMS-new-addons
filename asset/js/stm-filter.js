(function($) {
  'use strict';

  function updateResultCount($archive, count, newCountLabel) {
    var suffix = count === 1 ? '' : 's';
    var html = count + ' course' + suffix;

    if (newCountLabel) {
      html += ' <span class="stm-result-new-count">' + newCountLabel + '</span>';
    }

    $archive.find('.stm-result-count').html(html);
  }

  function getArchiveTitle(catId, catName, defaultTitle) {
    if (String(catId) === '0') {
      return defaultTitle;
    }

    return catName;
  }

  function initArchive($archive) {
    var initialCount = $archive.find('.stm-course-card').length;
    var initialNewLabel = $archive.find('.stm-result-new-count').text().trim();
    updateResultCount($archive, initialCount, initialNewLabel);
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
      $archive.find('.stm-main-title').text(getArchiveTitle(catId, catName, defaultTitle));
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
            updateResultCount($archive, res.data.count, res.data.new_count_label);
          }
        },
        error: function() {
          $grid.html('<p class="stm-no-courses">Something went wrong. Please try again.</p>');
          updateResultCount($archive, 0, '');
        },
        complete: function() {
          $grid.css({ opacity: 1, pointerEvents: 'auto' });
        }
      });
    });
  });
}(jQuery));
