/**
 * Government MCQ - Contact Page JavaScript
 *
 * Form validation, AJAX submission, character counter, FAQ accordion.
 * Vanilla JS. No jQuery dependency.
 *
 * @package Astra
 * @subpackage Government_MCQ
 */

(function () {
    'use strict';

    // ============================================================
    // DOM REFS
    // ============================================================
    var form, submitBtn, submitText, submitSpinner, successDiv, sendAnother;
    var nameInput, emailInput, phoneInput, subjectSelect, messageTextarea;
    var nameError, emailError, phoneError, subjectError, messageError;
    var messageCounter;

    function cacheDom() {
        form = document.getElementById('gmcq-contact-form');
        submitBtn = document.getElementById('gmcq-contact-submit');
        submitText = document.getElementById('gmcq-submit-text');
        submitSpinner = document.getElementById('gmcq-submit-spinner');
        successDiv = document.getElementById('gmcq-contact-success');
        sendAnother = document.getElementById('gmcq-send-another');

        nameInput = document.getElementById('gmcq-name');
        emailInput = document.getElementById('gmcq-email');
        phoneInput = document.getElementById('gmcq-phone');
        subjectSelect = document.getElementById('gmcq-subject');
        messageTextarea = document.getElementById('gmcq-message');

        nameError = document.getElementById('gmcq-name-error');
        emailError = document.getElementById('gmcq-email-error');
        phoneError = document.getElementById('gmcq-phone-error');
        subjectError = document.getElementById('gmcq-subject-error');
        messageError = document.getElementById('gmcq-message-error');

        messageCounter = document.getElementById('gmcq-message-counter');
    }

    // ============================================================
    // CHARACTER COUNTER
    // ============================================================
    function initCharCounter() {
        if (!messageTextarea || !messageCounter) return;

        messageTextarea.addEventListener('input', function () {
            var len = this.value.length;
            var max = parseInt(this.getAttribute('maxlength')) || 2000;
            messageCounter.textContent = len + ' / ' + max;

            messageCounter.classList.remove('warning', 'danger');
            if (len > max * 0.85) {
                messageCounter.classList.add('warning');
            }
            if (len >= max) {
                messageCounter.classList.add('danger');
            }
        });
    }

    // ============================================================
    // CLIENT-SIDE VALIDATION
    // ============================================================
    function clearErrors() {
        [nameError, emailError, phoneError, subjectError, messageError].forEach(function (el) {
            if (el) el.textContent = '';
        });
        [nameInput, emailInput, phoneInput, subjectSelect, messageTextarea].forEach(function (el) {
            if (el) el.classList.remove('error');
        });
    }

    function setError(input, errorEl, message) {
        if (input) input.classList.add('error');
        if (errorEl) errorEl.textContent = message;
    }

    function validateForm() {
        clearErrors();
        var isValid = true;

        // Name
        var name = nameInput ? nameInput.value.trim() : '';
        if (!name) {
            setError(nameInput, nameError, gmcqContact.labels.nameRequired || 'Please enter your name.');
            isValid = false;
        } else if (name.length < 2) {
            setError(nameInput, nameError, gmcqContact.labels.nameShort || 'Name must be at least 2 characters.');
            isValid = false;
        }

        // Email
        var email = emailInput ? emailInput.value.trim() : '';
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email) {
            setError(emailInput, emailError, gmcqContact.labels.emailRequired || 'Please enter your email.');
            isValid = false;
        } else if (!emailRegex.test(email)) {
            setError(emailInput, emailError, gmcqContact.labels.emailInvalid || 'Please enter a valid email address.');
            isValid = false;
        }

        // Subject
        var subject = subjectSelect ? subjectSelect.value : '';
        if (!subject) {
            setError(subjectSelect, subjectError, gmcqContact.labels.subjectRequired || 'Please select a subject.');
            isValid = false;
        }

        // Message
        var message = messageTextarea ? messageTextarea.value.trim() : '';
        if (!message) {
            setError(messageTextarea, messageError, gmcqContact.labels.messageRequired || 'Please enter your message.');
            isValid = false;
        } else if (message.length < 10) {
            setError(messageTextarea, messageError, gmcqContact.labels.messageShort || 'Message must be at least 10 characters.');
            isValid = false;
        } else if (message.length > 2000) {
            setError(messageTextarea, messageError, gmcqContact.labels.messageLong || 'Message is too long (max 2000 characters).');
            isValid = false;
        }

        return isValid;
    }

    // ============================================================
    // AJAX SUBMISSION
    // ============================================================
    function handleSubmit(e) {
        e.preventDefault();

        if (!validateForm()) {
            // Scroll to first error
            var firstError = form.querySelector('.error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
            return;
        }

        // Show loading state
        if (submitText) submitText.textContent = gmcqContact.labels.sending || 'Sending...';
        if (submitSpinner) submitSpinner.style.display = 'inline-flex';
        if (submitBtn) submitBtn.disabled = true;

        var formData = new FormData(form);
        formData.append('action', 'gmcq_contact_submit');
        formData.append('nonce', gmcqContact.nonce);

        fetch(gmcqContact.ajaxUrl, {
            method: 'POST',
            body: formData,
        })
        .then(function (response) { return response.json(); })
        .then(function (result) {
            // Reset button
            if (submitText) submitText.textContent = gmcqContact.labels.send || 'Send Message';
            if (submitSpinner) submitSpinner.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;

            if (result.success) {
                // Hide form, show success
                if (form) form.style.display = 'none';
                if (successDiv) successDiv.style.display = 'block';
                // Scroll to success
                if (successDiv) successDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                // Show errors
                if (result.data && result.data.errors) {
                    var errors = result.data.errors;
                    if (errors.name) setError(nameInput, nameError, errors.name);
                    if (errors.email) setError(emailInput, emailError, errors.email);
                    if (errors.subject) setError(subjectSelect, subjectError, errors.subject);
                    if (errors.message) setError(messageTextarea, messageError, errors.message);
                } else if (result.data && result.data.message) {
                    alert(result.data.message);
                }
            }
        })
        .catch(function () {
            if (submitText) submitText.textContent = gmcqContact.labels.send || 'Send Message';
            if (submitSpinner) submitSpinner.style.display = 'none';
            if (submitBtn) submitBtn.disabled = false;
            alert(gmcqContact.labels.error || 'Something went wrong. Please try again.');
        });
    }

    // ============================================================
    // SEND ANOTHER MESSAGE
    // ============================================================
    function initSendAnother() {
        if (!sendAnother) return;
        sendAnother.addEventListener('click', function () {
            // Reset form
            if (form) {
                form.reset();
                form.style.display = '';
            }
            if (successDiv) successDiv.style.display = 'none';
            clearErrors();
            if (messageCounter) messageCounter.textContent = '0 / 2000';
            if (messageCounter) messageCounter.classList.remove('warning', 'danger');
            // Scroll to form
            if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    // ============================================================
    // FAQ ACCORDION
    // ============================================================
    function initFaqAccordion() {
        var questions = document.querySelectorAll('.gmcq-faq-question');
        questions.forEach(function (q) {
            q.addEventListener('click', function () {
                var expanded = this.getAttribute('aria-expanded') === 'true';
                var answer = this.nextElementSibling;

                // Close all others
                questions.forEach(function (other) {
                    if (other !== q) {
                        other.setAttribute('aria-expanded', 'false');
                        var otherAnswer = other.nextElementSibling;
                        if (otherAnswer) {
                            otherAnswer.style.display = 'none';
                        }
                    }
                });

                // Toggle current
                this.setAttribute('aria-expanded', !expanded);
                if (answer) {
                    answer.style.display = expanded ? 'none' : 'block';
                }
            });
        });
    }

    // ============================================================
    // SCROLL ANIMATIONS
    // ============================================================
    function initScrollAnimations() {
        if (!('IntersectionObserver' in window)) {
            document.querySelectorAll('.gmcq-animate-fade-in-up').forEach(function (el) {
                el.classList.add('gmcq-visible');
            });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var el = entry.target;
                    var delay = parseInt(el.getAttribute('data-delay')) || 0;
                    setTimeout(function () { el.classList.add('gmcq-visible'); }, delay);
                    observer.unobserve(el);
                }
            });
        }, { threshold: 0.15 });

        document.querySelectorAll('.gmcq-animate-fade-in-up').forEach(function (el) {
            observer.observe(el);
        });
    }

    // ============================================================
    // INIT
    // ============================================================
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }

        function run() {
            if (!document.querySelector('.gmcq-contact-page')) return;

            cacheDom();

            if (form) {
                form.addEventListener('submit', handleSubmit);
                // Real-time validation on blur
                if (nameInput) nameInput.addEventListener('blur', function () {
                    if (this.value.trim()) { this.classList.remove('error'); if (nameError) nameError.textContent = ''; }
                });
                if (emailInput) emailInput.addEventListener('blur', function () {
                    if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value.trim())) { this.classList.remove('error'); if (emailError) emailError.textContent = ''; }
                });
                if (subjectSelect) subjectSelect.addEventListener('change', function () {
                    if (this.value) { this.classList.remove('error'); if (subjectError) subjectError.textContent = ''; }
                });
                if (messageTextarea) messageTextarea.addEventListener('blur', function () {
                    if (this.value.trim().length >= 10) { this.classList.remove('error'); if (messageError) messageError.textContent = ''; }
                });
            }

            initCharCounter();
            initSendAnother();
            initFaqAccordion();
            initScrollAnimations();
        }
    }

    init();
})();