(function($) {
  'use strict';

  function getFormContext(element) {
    return $(element).closest('[data-tdl-testimonial-form]');
  }

  function toggleTypeFields() {
    $('[data-tdl-testimonial-form]').each(function() {
      var $form = $(this);
      var type = $form.find('#tdl_t_type').val();

      $form.find('.tdl-t-field-text').toggle(type === 'text');
      $form.find('.tdl-t-field-video').toggle(type === 'video');
    });
  }

  function openMediaFrame(args) {
    if (!window.wp || !wp.media) {
      return null;
    }

    var frame = wp.media(args);
    frame.open();
    return frame;
  }

  function openModal() {
    var $modal = $('#tdl-testimonial-modal');
    if (!$modal.length) {
      return;
    }
    $modal.addClass('is-open').attr('aria-hidden', 'false');
    $('body').addClass('tdl-t-modal-open');
  }

  function closeModal() {
    $('#tdl-testimonial-modal').removeClass('is-open').attr('aria-hidden', 'true');
    $('body').removeClass('tdl-t-modal-open');
  }

  function bindForm() {
    if (!$('[data-tdl-testimonial-form]').length) {
      return;
    }

    toggleTypeFields();
    $(document).on('change', '#tdl_t_type', toggleTypeFields);

    $(document).on('click', '.tdl-star', function() {
      var $form = getFormContext(this);
      var value = parseInt($(this).data('val'), 10) || 5;

      $form.find('#tdl_t_rating').val(value);
      $form.find('.tdl-star').each(function() {
        $(this).toggleClass('active', (parseInt($(this).data('val'), 10) || 0) <= value);
      });
    });

    $(document).on('click', '.tdl-logo-btn', function(e) {
      var $form = getFormContext(this);
      var frame;
      e.preventDefault();
      frame = openMediaFrame({ title: stmTutorTestimonial.selectImageTitle, button: { text: stmTutorTestimonial.selectImageButton }, multiple: false, library: { type: 'image' } });
      if (!frame) { return; }
      frame.on('select', function() {
        var attachment = frame.state().get('selection').first().toJSON();
        $form.find('#tdl_t_logo').val(attachment.url);
        $form.find('#tdl-logo-preview').attr('src', attachment.url).show();
      });
    });

    $(document).on('click', '.tdl-video-btn', function(e) {
      var $form = getFormContext(this);
      var frame;
      e.preventDefault();
      frame = openMediaFrame({ title: stmTutorTestimonial.selectVideoTitle, button: { text: stmTutorTestimonial.selectVideoButton }, multiple: false, library: { type: 'video' } });
      if (!frame) { return; }
      frame.on('select', function() {
        var attachment = frame.state().get('selection').first().toJSON();
        $form.find('#tdl_t_video').val(attachment.url);
      });
    });

    $(document).on('click', '.tdl-thumb-btn', function(e) {
      var $form = getFormContext(this);
      var frame;
      e.preventDefault();
      frame = openMediaFrame({ title: stmTutorTestimonial.selectThumbTitle, button: { text: stmTutorTestimonial.selectThumbButton }, multiple: false, library: { type: 'image' } });
      if (!frame) { return; }
      frame.on('select', function() {
        var attachment = frame.state().get('selection').first().toJSON();
        $form.find('#tdl_t_thumbnail').val(attachment.url);
        $form.find('#tdl-t-thumb-preview').attr('src', attachment.url).show();
      });
    });
  }

  function injectDashboardButton() {
    var anchor;
    if (!stmTutorTestimonial.isAllowed || $('.tdl-t-dashboard-btn').length) {
      return;
    }
    anchor = $('.tutor-dashboard-create-course').last();
    if (!anchor.length) { anchor = $('a[href*="create-course"]').last(); }
    if (!anchor.length) {
      anchor = $('.tutor-btn').filter(function() { return $(this).text().toLowerCase().indexOf('course') !== -1; }).last();
    }
    if (!anchor.length) { return; }
    $('<a>', { href: '#', class: 'tutor-btn tutor-btn-outline-primary tdl-t-dashboard-btn', text: 'Add Testimonial' }).insertAfter(anchor);
  }

  function bindModal() {
    $(document).on('click', '.tdl-t-dashboard-btn', function(e) { e.preventDefault(); openModal(); });
    $(document).on('click', '[data-tdl-t-modal-close="1"]', closeModal);
    $(document).on('keydown', function(e) { if (e.key === 'Escape') { closeModal(); } });
    if (stmTutorTestimonial.saved) { openModal(); }
  }

  function bindDeleteConfirm() {
    $(document).on('click', '[data-tdl-t-delete="1"]', function() { return window.confirm(stmTutorTestimonial.confirmDelete); });
  }

  function bindCarousel() {
    $('.tdl-tc-wrap').each(function() {
      var wrap = this;
      var track = wrap.querySelector('.tdl-tc-track');
      var dotsEl = wrap.querySelector('.tdl-tc-dots');
      var cards = track ? track.querySelectorAll('.tdl-tc-card') : [];
      var total = cards.length;
      var autoplay = wrap.getAttribute('data-tdl-tc-autoplay') === 'true';
      var speed = parseInt(wrap.getAttribute('data-tdl-tc-speed'), 10) || 5000;
      var current = 0;
      var perPage = 3;
      var autoTimer = null;
      var dragStart = null;
      var dragDelta = 0;
      var resizeTimer;
      if (!track || !total) { return; }

      function getPerPage() {
        var width = wrap.offsetWidth;
        if (width <= 640) { return 1; }
        if (width <= 1024) { return 2; }
        return 3;
      }

      function cardWidth() {
        var gap = 24;
        return (wrap.offsetWidth - gap * (perPage - 1)) / perPage;
      }

      function updateDots() {
        var page = Math.floor(current / perPage);
        $(dotsEl).find('.tdl-tc-dot').each(function(index) { $(this).toggleClass('active', index === page); });
      }

      function goTo(index) {
        var maxIndex = Math.max(0, total - perPage);
        var translateX = current;
        current = Math.min(Math.max(0, index), maxIndex);
        translateX = current * (cardWidth() + 24);
        track.style.transform = 'translateX(-' + translateX + 'px)';
        updateDots();
      }

      function buildDots() {
        var pages = Math.ceil(total / perPage);
        dotsEl.innerHTML = '';
        for (var i = 0; i < pages; i += 1) {
          (function(index) {
            var button = document.createElement('button');
            button.className = 'tdl-tc-dot' + (index === 0 ? ' active' : '');
            button.type = 'button';
            button.addEventListener('click', function() { goTo(index * perPage); resetAuto(); });
            dotsEl.appendChild(button);
          }(i));
        }
      }

      function setSizes() {
        var width;
        perPage = getPerPage();
        width = cardWidth();
        Array.prototype.forEach.call(cards, function(card) { card.style.flexBasis = width + 'px'; });
        buildDots();
        goTo(current);
      }

      function startAuto() {
        if (!autoplay) { return; }
        autoTimer = window.setInterval(function() {
          var next = current + perPage;
          goTo(next >= total ? 0 : next);
        }, speed);
      }

      function resetAuto() { window.clearInterval(autoTimer); startAuto(); }
      function onPointerDown(event) { dragStart = event.touches ? event.touches[0].clientX : event.clientX; dragDelta = 0; track.classList.add('tdl-dragging'); }
      function onPointerMove(event) { if (dragStart === null) { return; } dragDelta = (event.touches ? event.touches[0].clientX : event.clientX) - dragStart; }
      function onPointerUp() {
        var next;
        if (dragStart === null) { return; }
        track.classList.remove('tdl-dragging');
        if (dragDelta < -60) { next = current + perPage; goTo(next >= total ? 0 : next); }
        else if (dragDelta > 60) { goTo(current - perPage); }
        else { goTo(current); }
        dragStart = null; dragDelta = 0; resetAuto();
      }

      $(wrap).find('.tdl-tc-arrow.prev').on('click', function() { goTo(current - perPage); resetAuto(); });
      $(wrap).find('.tdl-tc-arrow.next').on('click', function() { var next = current + perPage; goTo(next >= total ? 0 : next); resetAuto(); });
      $(track).on('mousedown', onPointerDown);
      $(track).on('mousemove', onPointerMove);
      $(track).on('mouseup mouseleave', onPointerUp);
      $(track).on('touchstart', onPointerDown);
      $(track).on('touchmove', onPointerMove);
      $(track).on('touchend', onPointerUp);
      $(track).find('.tdl-tc-play-btn').on('click', function(e) { var media = $(this).closest('.tdl-tc-video-media'); var video = media.find('video').get(0); e.stopPropagation(); if (Math.abs(dragDelta) > 5) { return; } media.addClass('tdl-playing'); if (video) { video.play(); } });
      $(window).on('resize', function() { window.clearTimeout(resizeTimer); resizeTimer = window.setTimeout(setSizes, 150); });
      setSizes();
      startAuto();
    });
  }

  $(document).ready(function() {
    var tries = 0;
    var timer;
    bindForm();
    bindModal();
    bindDeleteConfirm();
    bindCarousel();
    injectDashboardButton();
    if (stmTutorTestimonial.isAllowed) {
      timer = window.setInterval(function() {
        injectDashboardButton();
        tries += 1;
        if (tries >= 20) { window.clearInterval(timer); }
      }, 400);
      if (window.MutationObserver) {
        new MutationObserver(injectDashboardButton).observe(document.body, { childList: true, subtree: true });
      }
    }
  });
}(jQuery));
