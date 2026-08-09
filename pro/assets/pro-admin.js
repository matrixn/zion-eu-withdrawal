(function ($) {
    'use strict';
    const config = window.ZionEuWithdrawalProAdmin || {};
    const timers = {};
    function valueFor(input) { return input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value; }
    function save(input) {
        const key = input.dataset.zionProSetting;
        const row = input.closest('[data-zion-pro-setting-row]');
        const status = row ? row.querySelector('.zion-eu-save-status') : null;
        if (!key || !status) return;
        status.className = 'zion-eu-save-status is-saving';
        status.textContent = config.saving || 'Saving…';
        $.post(config.ajaxUrl, { action: 'zion_eu_pro_save_setting', nonce: config.nonce, key: key, value: valueFor(input) }).done(function (response) {
            if (response && response.success) {
                status.className = 'zion-eu-save-status is-saved';
                status.textContent = (response.data && response.data.message) || config.saved || 'Saved automatically';
            } else {
                status.className = 'zion-eu-save-status is-error';
                status.textContent = (response.data && response.data.message) || config.error || 'Could not save.';
            }
        }).fail(function () { status.className = 'zion-eu-save-status is-error'; status.textContent = config.error || 'Could not save.'; });
    }
    $(document).on('change', '[data-zion-pro-setting]', function () { save(this); });
    $(document).on('blur', '[data-zion-pro-setting]:not([type="checkbox"])', function () { const input = this; clearTimeout(timers[input.dataset.zionProSetting]); timers[input.dataset.zionProSetting] = setTimeout(function () { save(input); }, 250); });
}(jQuery));
