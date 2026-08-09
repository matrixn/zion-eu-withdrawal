(function (wp, wc) {
    'use strict';

    if (!wp || !wp.plugins || !wp.element || !wc || !wc.blocksCheckout) {
        return;
    }

    const settingsApi = wc.wcSettings || {};
    const data = typeof settingsApi.getSetting === 'function' ? settingsApi.getSetting('zion-eu-withdrawal_data', {}) : {};
    if (data.enabled === false) {
        return;
    }

    const createElement = wp.element.createElement;
    const OrderMeta = wc.blocksCheckout.ExperimentalOrderMeta;
    if (!OrderMeta) {
        return;
    }

    const Disclosure = function () {
        return createElement('div', { className: 'zion-eu-checkout-disclosure' },
            createElement('strong', null, data.title || 'Right of withdrawal'),
            createElement('p', null, data.text || 'Information about the right of withdrawal is available here. ',
                createElement('a', { href: data.url || '#' }, data.linkLabel || 'View the online function', ' →'))
        );
    };

    wp.plugins.registerPlugin('zion-eu-withdrawal-checkout', {
        render: function () {
            return createElement(OrderMeta, null, createElement(Disclosure));
        },
        scope: 'woocommerce-checkout'
    });
}(window.wp, window.wc));
