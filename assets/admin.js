(function ($) {
    'use strict';

    const config = window.ZionEuWithdrawalAdmin || {};
    const timers = {};

    function valueFor(input) {
        if (input.type === 'checkbox') {
            return input.checked ? '1' : '0';
        }
        return input.value;
    }

    function save(input) {
        const key = input.dataset.zionSetting;
        const row = input.closest('[data-setting-row]');
        const status = row ? row.querySelector('.zion-eu-save-status') : null;
        if (!key || !status || input.hasAttribute('readonly')) return;
        status.className = 'zion-eu-save-status is-saving';
        status.textContent = config.saving || 'Saving…';
        const body = { action: 'zion_eu_save_setting', nonce: config.nonce, key: key, value: valueFor(input) };
        $.post(config.ajaxUrl, body).done(function (response) {
            if (response && response.success) {
                status.className = 'zion-eu-save-status is-saved';
                status.textContent = (response.data && response.data.message) || config.saved || 'Saved automatically';
                setTimeout(function () { status.textContent = ''; status.className = 'zion-eu-save-status'; }, 2600);
            } else {
                status.className = 'zion-eu-save-status is-error';
                status.textContent = (response.data && response.data.message) || config.error || 'Could not save.';
            }
        }).fail(function () {
            status.className = 'zion-eu-save-status is-error';
            status.textContent = config.error || 'Could not save.';
        });
    }

    $(document).on('change', '[data-zion-setting]', function () { save(this); });
    $(document).on('blur', '[data-zion-setting]:not([type="checkbox"]):not([readonly])', function () {
        const input = this;
        clearTimeout(timers[input.dataset.zionSetting]);
        timers[input.dataset.zionSetting] = setTimeout(function () { save(input); }, 250);
    });
}(jQuery));

(function ($) {
    'use strict';

    const config = window.ZionEuWithdrawalAdmin || {};

    $(document).on('click', '[data-save-withdrawal]', function () {
        const button = $(this);
        const id = button.data('save-withdrawal');
        const card = button.closest('.zion-eu-admin-edit-card');
        const feedback = card.find('[data-withdrawal-feedback]');
        feedback.removeClass('is-error is-saved').addClass('is-saving').text('Saving…');
        button.prop('disabled', true);
        $.post(config.ajaxUrl, {
            action: 'zion_eu_update_withdrawal',
            nonce: config.adminNonce || '',
            id: id,
            status: card.find('[data-withdrawal-status]').val(),
            merchant_notes: card.find('[data-withdrawal-notes]').val()
        }).done(function (response) {
            if (!response || !response.success) throw new Error(response && response.data && response.data.message ? response.data.message : 'Could not save.');
            feedback.removeClass('is-saving is-error').addClass('is-saved').text(response.data.message || 'Saved.');
        }).fail(function (xhr) {
            const response = xhr.responseJSON || {};
            feedback.removeClass('is-saving is-saved').addClass('is-error').text(response.data && response.data.message ? response.data.message : (config.error || 'Could not save.'));
        }).always(function () { button.prop('disabled', false); });
    });

    $(document).on('click', '[data-resend-notification]', function () {
        const button = $(this);
        button.prop('disabled', true);
        $.post(config.ajaxUrl, { action: 'zion_eu_resend_notification', nonce: config.adminNonce || '', id: button.data('resend-notification'), type: button.data('notification-type') }).done(function (response) {
            window.alert(response && response.data && response.data.message ? response.data.message : 'Notification processed.');
        }).fail(function (xhr) {
            const response = xhr.responseJSON || {};
            window.alert(response.data && response.data.message ? response.data.message : 'Notification delivery failed.');
        }).always(function () { button.prop('disabled', false); });
    });
}(jQuery));
