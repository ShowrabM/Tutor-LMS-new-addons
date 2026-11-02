/**
 * STM Tutor Popup Maker - Frontend Engine
 * Pure Vanilla JavaScript (Zero jQuery, Zero Dependencies)
 * Handles delay trigger, localStorage frequency cap, scroll lock, keyboard navigation,
 * and asynchronous AJAX form submission via native fetch().
 */

(function() {
    'use strict';

    function initPopup() {
        var modal = document.getElementById('stm-popup-maker-modal');
        if (!modal) {
            return;
        }

        var popupId       = modal.getAttribute('data-popup-id') || '0';
        var delaySec      = parseInt(modal.getAttribute('data-delay') || '2', 10);
        var frequencyDays = parseInt(modal.getAttribute('data-frequency') || '7', 10);
        var storageKey    = 'stm_pm_suppress_' + popupId;

        // 1. Check Frequency Capping in localStorage
        if (frequencyDays > 0) {
            try {
                var suppressedUntil = localStorage.getItem(storageKey);
                if (suppressedUntil) {
                    var expireTime = parseInt(suppressedUntil, 10);
                    if (!isNaN(expireTime) && Date.now() < expireTime) {
                        // Suppressed - do not show popup
                        return;
                    }
                }
            } catch (e) {
                // Ignore localStorage errors (e.g. private browsing storage restrictions)
            }
        }

        var form          = document.getElementById('stm-pm-lead-form');
        var closeBtn      = modal.querySelector('.stm-pm-modal-close');
        var backdrop      = modal.querySelector('.stm-pm-modal-backdrop');
        var submitBtn     = form ? form.querySelector('.stm-pm-submit-btn') : null;
        var btnTextEl     = submitBtn ? submitBtn.querySelector('.stm-pm-btn-text') : null;
        var spinnerEl     = submitBtn ? submitBtn.querySelector('.stm-pm-btn-spinner') : null;
        var successNotice = document.getElementById('stm-pm-success-notice');
        var errorNotice   = document.getElementById('stm-pm-error-notice');
        var originalBtnText = btnTextEl ? btnTextEl.textContent : (submitBtn ? submitBtn.textContent : 'Submit');

        function suppressForDays(days) {
            if (days > 0) {
                try {
                    var until = Date.now() + (days * 24 * 60 * 60 * 1000);
                    localStorage.setItem(storageKey, until.toString());
                } catch (e) {}
            }
        }

        // Open Popup Modal
        function openModal() {
            modal.classList.add('stm-pm-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('stm-pm-scroll-locked');

            // Focus on first input if available
            var firstInput = form ? form.querySelector('input:not([type="hidden"]), textarea') : null;
            if (firstInput) {
                setTimeout(function() {
                    firstInput.focus();
                }, 150);
            }
        }

        // Close Popup Modal
        function closeModal(isSubmission) {
            modal.classList.remove('stm-pm-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('stm-pm-scroll-locked');

            // Always suppress for configured frequency days after dismissal or submission
            suppressForDays(frequencyDays);
        }

        // 2. Trigger popup after the configured delay
        var delayTimer = setTimeout(function() {
            openModal();
        }, Math.max(0, delaySec) * 1000);

        // 3. Event Listeners for Dismissal
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal(false);
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal(false);
            });
        }

        // Escape key listener
        document.addEventListener('keydown', function(e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && modal.classList.contains('stm-pm-open')) {
                closeModal(false);
            }
        });

        // 4. Asynchronous AJAX Lead Submission using native fetch()
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // Clear previous messages
                if (errorNotice) {
                    errorNotice.textContent = '';
                    errorNotice.style.display = 'none';
                }
                if (successNotice) {
                    successNotice.style.display = 'none';
                }

                // Gather form data
                var formData = new FormData(form);
                formData.append('action', 'stm_submit_popup_lead');
                formData.append('page_url', window.location.href);

                // Set loading state
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                if (spinnerEl) {
                    spinnerEl.style.display = 'inline-block';
                }
                if (btnTextEl) {
                    btnTextEl.textContent = 'Submitting...';
                }

                var ajaxUrl = (window.stmPopupMakerData && window.stmPopupMakerData.ajaxUrl) 
                    ? window.stmPopupMakerData.ajaxUrl 
                    : '/wp-admin/admin-ajax.php';

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(result) {
                    if (result && result.success) {
                        // Success State
                        if (successNotice) {
                            successNotice.textContent = (result.data && result.data.message) ? result.data.message : 'Thank you! Your information has been submitted.';
                            successNotice.style.display = 'block';
                        }
                        form.style.display = 'none';

                        // Auto-close popup 1.5 seconds after successful submission and set local storage flag
                        setTimeout(function() {
                            closeModal(true);
                        }, 1500);
                    } else {
                        // Error State
                        var errorMsg = (result && result.data && result.data.message) ? result.data.message : 'An error occurred. Please try again.';
                        if (errorNotice) {
                            errorNotice.textContent = errorMsg;
                            errorNotice.style.display = 'block';
                        } else {
                            alert(errorMsg);
                        }
                        resetSubmitButton();
                    }
                })
                .catch(function(err) {
                    if (errorNotice) {
                        errorNotice.textContent = 'Network error. Please try again.';
                        errorNotice.style.display = 'block';
                    } else {
                        alert('Network error. Please try again.');
                    }
                    resetSubmitButton();
                });
            });
        }

        function resetSubmitButton() {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            if (spinnerEl) {
                spinnerEl.style.display = 'none';
            }
            if (btnTextEl) {
                btnTextEl.textContent = originalBtnText;
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPopup);
    } else {
        initPopup();
    }
})();
