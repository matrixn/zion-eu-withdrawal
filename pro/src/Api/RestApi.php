<?php

declare(strict_types=1);

namespace Zion\EuWithdrawalPro\Api;

use Zion\EuWithdrawal\Infrastructure\WithdrawalRepository;
use Zion\EuWithdrawalPro\Infrastructure\ProSettings;
use WP_REST_Request;
use WP_REST_Response;

final class RestApi
{
    private const NAMESPACE = 'zion-eu-withdrawal/v1';

    public function __construct(
        private readonly ProSettings $settings,
        private readonly WithdrawalRepository $repository
    ) {
    }

    public function register_hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/health', ['methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => fn (): WP_REST_Response => new WP_REST_Response(['pro' => ZION_EU_WITHDRAWAL_PRO_VERSION, 'core' => defined('ZION_EU_WITHDRAWAL_VERSION') ? ZION_EU_WITHDRAWAL_VERSION : ''], 200)]);
        register_rest_route(self::NAMESPACE, '/withdrawals', ['methods' => 'GET', 'permission_callback' => [$this, 'permission'], 'callback' => [$this, 'withdrawals']]);
        register_rest_route(self::NAMESPACE, '/delivery/(?P<order_id>\d+)', ['methods' => 'POST', 'permission_callback' => [$this, 'permission'], 'callback' => [$this, 'delivery']]);
    }

    public function permission(WP_REST_Request $request): bool
    {
        if (! $this->settings->get('api_enabled', 0)) {
            return false;
        }
        if (current_user_can('manage_options')) {
            return true;
        }

        $secret = (string) $this->settings->get('api_secret', '');
        $header = (string) $request->get_header('authorization');
        $token = preg_replace('/^Bearer\s+/i', '', $header) ?: '';
        return $secret !== '' && $token !== '' && hash_equals($secret, $token);
    }

    public function withdrawals(WP_REST_Request $request): WP_REST_Response
    {
        $status = sanitize_key((string) $request->get_param('status'));
        $search = sanitize_text_field((string) $request->get_param('search'));
        return new WP_REST_Response(['data' => $this->repository->all($status, 100, 0, $search)], 200);
    }

    public function delivery(WP_REST_Request $request): WP_REST_Response
    {
        $order_id = absint($request['order_id']);
        $date = sanitize_text_field((string) $request->get_param('delivery_date'));
        if ($order_id < 1 || $date === '' || ! function_exists('wc_get_order')) {
            return new WP_REST_Response(['message' => 'A valid order_id and delivery_date are required.'], 422);
        }
        $order = wc_get_order($order_id);
        if (! $order) {
            return new WP_REST_Response(['message' => 'Order not found.'], 404);
        }
        $order->update_meta_data((string) $this->settings->get('delivery_meta_key', '_zion_delivery_date'), $date);
        $order->save();
        return new WP_REST_Response(['order_id' => $order_id, 'delivery_date' => $date], 200);
    }
}
