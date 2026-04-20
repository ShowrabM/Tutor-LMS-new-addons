(function($) {
  'use strict';

  function toggleUploadFields() {
    $('[data-tdl-upload-form]').each(function() {
      var $form = $(this);
      var type = $form.find('#tdl_demo_type').val();

      $form.find('.tdl-field-file').hide();
      $form.find('.tdl-field-embed').hide();

      if (type === 'video' || type === 'pdf') {
        $form.find('.tdl-field-file').show();
      }

      if (type === 'embed') {
        $form.find('.tdl-field-embed').show();
      }
    });
  }

  function getFormContext(element) {
    return $(element).closest('[data-tdl-upload-form]');
  }

  function openUploadModal() {
    var $modal = $('#tdl-upload-modal');

    if (!$modal.length) {
      return;
    }

    $modal.addClass('is-open').attr('aria-hidden', 'false');
    $('body').addClass('tdl-modal-open');
  }

  function closeUploadModal() {
    $('#tdl-upload-modal').removeClass('is-open').attr('aria-hidden', 'true');
    $('body').removeClass('tdl-modal-open');
  }

  function openMediaFrame(args) {
    if (!window.wp || !wp.media) {
      return;
    }

    var frame = wp.media(args);
    frame.open();

    return frame;
  }

  function bindUploadForm() {
    if (!$('[data-tdl-upload-form]').length) {
      return;
    }

    toggleUploadFields();
    $(document).on('change', '#tdl_demo_type', function() {
      toggleUploadFields();
    });

    $(document).on('click', '.tdl-upload-btn', function(e) {
      var library = {};
      var $form = getFormContext(this);
      var type = $form.find('#tdl_demo_type').val();

      e.preventDefault();

      if (type === 'video') {
        library = { type: 'video' };
      } else if (type === 'pdf') {
        library = { type: 'application/pdf' };
      }

      var frame = openMediaFrame({
        title: stmTutorDemo.selectFile,
        button: { text: stmTutorDemo.useFile },
        multiple: false,
        library: library
      });

      if (!frame) {
        return;
      }

      frame.on('select', function() {
        var attachment = frame.state().get('selection').first().toJSON();
        $form.find('#tdl_demo_file').val(attachment.url);
      });
    });

    $(document).on('click', '.tdl-thumb-btn', function(e) {
      var $form = getFormContext(this);

      e.preventDefault();

      var frame = openMediaFrame({
        title: stmTutorDemo.selectImage,
        button: { text: stmTutorDemo.useImage },
        multiple: false,
        library: { type: 'image' }
      });

      if (!frame) {
        return;
      }

      frame.on('select', function() {
        var attachment = frame.state().get('selection').first().toJSON();
        $form.find('#tdl_demo_thumbnail').val(attachment.url);
        $form.find('#tdl-thumb-preview').attr('src', attachment.url).show();
      });
    });
  }

  function injectDashboardButton() {
    if (!stmTutorDemo.isAllowed) {
      return;
    }

    if ($('.tdl-dashboard-upload-btn').length) {
      return;
    }

    var anchor = $('.tutor-dashboard-create-course').last();

    if (!anchor.length) {
      anchor = $('a[href*="create-course"]').last();
    }

    if (!anchor.length) {
      anchor = $('.tutor-btn').filter(function() {
        return $(this).text().toLowerCase().indexOf('course') !== -1;
      }).last();
    }

    if (!anchor.length) {
      return;
    }

    $('<a>', {
      href: '#',
      class: 'tutor-btn tutor-btn-outline-primary tdl-dashboard-upload-btn',
      text: 'Upload Demo'
    }).insertAfter(anchor);
  }

  function bindModalActions() {
    $(document).on('click', '.tdl-dashboard-upload-btn', function(e) {
      e.preventDefault();
      openUploadModal();
    });

    $(document).on('click', '[data-tdl-modal-close="1"]', function() {
      closeUploadModal();
    });

    $(document).on('keydown', function(e) {
      if (e.key === 'Escape') {
        closeUploadModal();
      }
    });

    if (stmTutorDemo.saved) {
      openUploadModal();
    }
  }

  function bindGalleryPlayback() {
    $(document).on('click', '.tdl-play-overlay', function() {
      var media = $(this).closest('.tdl-card-media');
      var iframe = media.find('iframe');
      var video = media.find('video').get(0);

      media.addClass('tdl-playing');

      if (iframe.length) {
        var src = iframe.attr('src');
        if (src && src.indexOf('autoplay=1') === -1) {
          src += src.indexOf('?') === -1 ? '?autoplay=1' : '&autoplay=1';
          iframe.attr('src', src);
        }
      }

      if (video) {
        video.play();
      }
    });

    $(document).on('click', '[data-tdl-delete="1"]', function() {
      return window.confirm(stmTutorDemo.confirmDelete);
    });
  }

  $(document).ready(function() {
    var tries = 0;
    var timer;

    bindUploadForm();
    bindGalleryPlayback();
    bindModalActions();
    injectDashboardButton();

    if (stmTutorDemo.isAllowed) {
      timer = setInterval(function() {
        injectDashboardButton();
        tries += 1;

        if (tries >= 20) {
          clearInterval(timer);
        }
      }, 400);

      if (window.MutationObserver) {
        new MutationObserver(injectDashboardButton).observe(document.body, {
          childList: true,
          subtree: true
        });
      }
    }
  });
}(jQuery));
