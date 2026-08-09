(function () {
    'use strict';

    const app = document.querySelector('[data-zion-withdrawal-app]');
    const config = window.ZionEuWithdrawalPublic || {};
    if (!app || !config.ajaxUrl) return;

    const strings = config.strings || {};
    let reviewToken = '';
    let review = null;
    let statement = '';

    const escapeHtml = function (value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };

    const formValues = function (form) {
        const values = {};
        new FormData(form).forEach(function (value, key) { values[key] = value; });
        return values;
    };

    const request = function (action, data) {
        const body = new URLSearchParams({ action: action, nonce: config.nonce || '' });
        Object.keys(data).forEach(function (key) { body.append(key, data[key] === undefined ? '' : data[key]); });
        return fetch(config.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString(), credentials: 'same-origin' }).then(function (response) { return response.json(); });
    };

    const feedback = function (name, message, error) {
        const target = app.querySelector('[data-feedback="' + name + '"]');
        if (!target) return;
        target.textContent = message || '';
        target.className = 'zion-eu-withdrawal-feedback' + (error ? ' is-error' : ' is-success');
    };

    const showStep = function (step) {
        app.querySelectorAll('[data-step]').forEach(function (panel) {
            if (panel.dataset.step === 'success') return;
            const active = panel.dataset.step === String(step);
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });
        app.querySelectorAll('[data-progress]').forEach(function (item) {
            item.classList.toggle('is-active', Number(item.dataset.progress) <= step);
        });
        app.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const setBusy = function (form, busy, text) {
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;
        if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
        button.disabled = busy;
        button.classList.toggle('is-loading', busy);
        if (busy) button.childNodes[0].textContent = text || strings.loading || 'Loading…';
        else button.childNodes[0].textContent = button.dataset.originalText;
    };

    const renderItems = function (items) {
        return (items || []).map(function (item) {
            return '<li><span>' + escapeHtml(item.name) + '</span><strong>× ' + escapeHtml(item.quantity) + '</strong></li>';
        }).join('');
    };

    const renderReview = function () {
        const card = app.querySelector('[data-review-card]');
        if (!card || !review) return;
        card.innerHTML = '<div class="zion-eu-review-line"><span>' + escapeHtml(review.customer_name) + '</span><strong>' + escapeHtml(review.customer_email) + '</strong></div>' +
            '<div class="zion-eu-review-line"><span>' + escapeHtml(strings.orderLabel || 'Order / contract') + '</span><strong>#' + escapeHtml(review.order_reference) + '</strong></div>' +
            '<div class="zion-eu-review-declaration"><span>' + escapeHtml(strings.declarationLabel || 'Declaration') + '</span><p>' + escapeHtml(strings.declaration || 'I communicate my unambiguous decision to withdraw from this contract.') + '</p>' +
            (statement ? '<small>' + escapeHtml(statement) + '</small>' : '<small>' + escapeHtml(strings.noNotes || 'No additional notes.') + '</small>') + '</div>' +
            '<div class="zion-eu-review-proof"><span>RO-2026.06.19-v1</span><span>' + escapeHtml(strings.serverProof || 'Server-side timestamp will be saved after confirmation.') + '</span></div>';
    };

    const identifyForm = app.querySelector('[data-zion-form="identify"]');
    if (identifyForm) {
        const prefill = app.dataset.prefillOrder || app.dataset.guestOrder || '';
        if (prefill) identifyForm.elements.order_reference.value = prefill;
        if (app.dataset.guestName) identifyForm.elements.customer_name.value = app.dataset.guestName;
        if (app.dataset.guestEmail) identifyForm.elements.customer_email.value = app.dataset.guestEmail;
        identifyForm.addEventListener('submit', function (event) {
            event.preventDefault();
            feedback('identify', '');
            setBusy(identifyForm, true, strings.loading);
            const identifyValues = formValues(identifyForm);
            identifyValues.guest_token = app.dataset.guestToken || '';
            request('zion_eu_begin_withdrawal', identifyValues).then(function (response) {
                if (!response || !response.success) throw new Error(response && response.data && response.data.message ? response.data.message : (strings.genericError || 'Could not process the request.'));
                const values = formValues(identifyForm);
                reviewToken = response.data.review_token;
                review = { customer_name: values.customer_name, customer_email: values.customer_email, customer_phone: values.customer_phone || '', order_reference: response.data.order.reference, items: response.data.order.items || [] };
                const summary = app.querySelector('[data-order-summary]');
                if (summary) summary.innerHTML = '<div class="zion-eu-summary-heading"><span>' + escapeHtml(strings.contractIdentified || 'Contract identified') + '</span><strong>#' + escapeHtml(review.order_reference) + '</strong></div><ul>' + renderItems(review.items) + '</ul>';
                feedback('identify', response.data.message, false);
                showStep(2);
            }).catch(function (error) { feedback('identify', error.message || strings.genericError, true); }).finally(function () { setBusy(identifyForm, false); });
        });
        const guestButton = identifyForm.querySelector('[data-request-guest-link]');
        if (guestButton) {
            guestButton.addEventListener('click', function () {
                const values = formValues(identifyForm);
                const target = identifyForm.querySelector('[data-guest-feedback]');
                guestButton.disabled = true;
                if (target) target.textContent = strings.guestLinkSending || 'Sending…';
                request('zion_eu_request_guest_link', { customer_email: values.customer_email || '', order_reference: values.order_reference || '' }).then(function (response) {
                    if (target) target.textContent = response && response.data && response.data.message ? response.data.message : (strings.guestLinkGeneric || 'If the details match, an e-mail will arrive shortly.');
                }).catch(function () {
                    if (target) target.textContent = strings.guestLinkGeneric || 'If the details match, an e-mail will arrive shortly.';
                }).finally(function () { guestButton.disabled = false; });
            });
        }
    }

    const statementForm = app.querySelector('[data-zion-form="statement"]');
    if (statementForm) {
        statementForm.addEventListener('submit', function (event) {
            event.preventDefault();
            statement = statementForm.elements.statement.value.trim();
            renderReview();
            showStep(3);
        });
    }

    const confirmForm = app.querySelector('[data-zion-form="confirm"]');
    if (confirmForm) {
        confirmForm.addEventListener('submit', function (event) {
            event.preventDefault();
            feedback('confirm', '');
            setBusy(confirmForm, true, strings.confirming);
            request('zion_eu_confirm_withdrawal', { review_token: reviewToken, statement: statement, confirmation: '1' }).then(function (response) {
                if (!response || !response.success) throw new Error(response && response.data && response.data.message ? response.data.message : (strings.genericError || 'Could not process the request.'));
                app.querySelectorAll('[data-step]').forEach(function (panel) { panel.hidden = true; panel.classList.remove('is-active'); });
                const success = app.querySelector('[data-step="success"]');
                if (success) success.hidden = false;
                const id = app.querySelector('[data-withdrawal-id]');
                if (id) id.textContent = response.data.withdrawal_id;
                const meta = app.querySelector('[data-success-meta]');
                if (meta) meta.textContent = (strings.submittedAt || 'Submitted at (UTC)') + ': ' + response.data.submitted_at;
                app.querySelectorAll('[data-progress]').forEach(function (item) { item.classList.add('is-active'); });
                app.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }).catch(function (error) { feedback('confirm', error.message || strings.genericError, true); }).finally(function () { setBusy(confirmForm, false); });
        });
    }
}());
