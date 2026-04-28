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

  function updateMainNewPill($archive, newCount) {
    var $heading = $archive.find('.stm-main-heading');
    var $pill = $heading.find('.stm-main-new-pill');

    if (newCount > 0) {
      if (!$pill.length) {
        $pill = $('<span class="stm-main-new-pill"></span>').appendTo($heading);
      }

      $pill.text('New (' + newCount + ')');
    } else {
      $pill.remove();
    }
  }

  function getArchiveTitle(catId, catName, defaultTitle) {
    if (String(catId) === '0') {
      return defaultTitle;
    }

    return catName;
  }

  function initArchive($archive) {
    syncMobileFilter($archive);
  }

  function syncMobileFilter($archive) {
    var activeCatId = String($archive.find('.stm-cat-link.active').data('cat-id'));
    var $select = $archive.find('.stm-cat-select');

    if ($select.length) {
      $select.val(activeCatId);
    }
  }

  function triggerCategoryChange($clicked) {
    if ($clicked.length) {
      $clicked.trigger('click');
    }
  }

  function filterArchive($archive, catId, catName) {
    var $grid = $archive.find('.stm-course-grid');
    var postsPerPage = $archive.data('posts-per-page');
    var includeCategories = $archive.data('include-categories');
    var excludeCategories = $archive.data('exclude-categories');
    var defaultTitle = $archive.data('default-title');

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
          updateMainNewPill($archive, res.data.new_count);
        }
      },
      error: function() {
        $grid.html('<p class="stm-no-courses">Something went wrong. Please try again.</p>');
        updateResultCount($archive, 0, '');
        updateMainNewPill($archive, 0);
      },
      complete: function() {
        $grid.css({ opacity: 1, pointerEvents: 'auto' });
      }
    });
  }

  $(document).ready(function() {
    $('.stm-course-archive').each(function() {
      initArchive($(this));
    });

    $(document).on('change', '.stm-course-archive .stm-cat-select', function() {
      var $select = $(this);
      var $archive = $select.closest('.stm-course-archive');
      var selectedCatId = String($select.val());
      var $clicked = $archive.find('.stm-cat-link[data-cat-id="' + selectedCatId + '"]');

      if ($clicked.length) {
        triggerCategoryChange($clicked);
        return;
      }

      $archive.find('.stm-cat-link').removeClass('active');
      filterArchive($archive, selectedCatId, $select.find('option:selected').text().replace(/\s*\(\d+\)\s*$/, '').trim());
    });

    $(document).on('click', '.stm-course-archive .stm-cat-link', function(e) {
      e.preventDefault();

      var $clicked = $(this);
      var $archive = $clicked.closest('.stm-course-archive');
      var catId = $clicked.data('cat-id');
      var catName = $clicked.find('.stm-cat-name').text().trim();

      $archive.find('.stm-cat-link').removeClass('active');
      $clicked.addClass('active');
      syncMobileFilter($archive);
      filterArchive($archive, catId, catName);
    });
  });
}(jQuery));
