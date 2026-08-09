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
