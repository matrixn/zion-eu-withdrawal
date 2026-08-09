<?php

declare(strict_types=1);

namespace Zion\EuWithdrawalPro\Infrastructure;

final class ProSettings
{
    public const OPTION = 'zion_eu_withdrawal_pro_settings';

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'visual_enabled' => 1,
            'floating_position' => 'bottom-right',
            'button_label_ro' => 'Retragere online',
            'button_label_en' => 'Online withdrawal',
            'button_background' => '#f97316',
            'button_text_color' => '#ffffff',
            'button_radius' => 14,
            'custom_css' => '',
            'customize_checkout' => 1,
            'checkout_position' => 'before_submit',
            'checkout_title_ro' => 'Dreptul de retragere',
            'checkout_title_en' => 'Right of withdrawal',
            'checkout_text_ro' => 'Informatii privind exercitarea dreptului de retragere si functia online dedicata sunt disponibile aici.',
            'checkout_text_en' => 'Information about exercising the right of withdrawal and the dedicated online function is available here.',
            'partial_withdrawal_enabled' => 0,
            'advanced_rules' => wp_json_encode([
                [
                    'name' => 'Produse personalizate',
                    'when' => ['category' => 'personalizate'],
                    'state' => 'potential_exception',
                    'exception_code' => 'EXC-C',
                    'reason_ro' => 'Produsul poate intra in categoria bunurilor personalizate; verificarea comerciantului ramane necesara.',
                    'reason_en' => 'The product may be personalised; merchant review remains necessary.',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'delivery_provider' => 'order_meta',
            'delivery_meta_key' => '_zion_delivery_date',
            'delivered_status' => 'completed',
            'deadline_reminder_days' => 3,
            'api_enabled' => 0,
            'api_secret' => '',
            'webhook_url' => '',
            'webhook_secret' => '',
            'webhook_events' => "withdrawal.created\nwithdrawal.status_changed",
            'white_label' => 0,
            'agency_label' => 'Zion3D Pro',
        ];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge(self::defaults(), (array) get_option(self::OPTION, []));
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        $settings = $this->all();
        return array_key_exists($key, $settings) ? $settings[$key] : $fallback;
    }

    /** @return array<string, array<string, mixed>> */
    public static function schema(): array
    {
        return [
            'visual_enabled' => ['section' => 'visual', 'type' => 'checkbox', 'ro' => 'Activeaza butonul plutitor', 'en' => 'Enable floating button', 'description_ro' => 'Afiseaza un buton persistent catre functia online de retragere pe paginile publice. Nu modifica formularul juridic.', 'description_en' => 'Shows a persistent button to the online withdrawal function on public pages. It does not change the legal form.'],
            'floating_position' => ['section' => 'visual', 'type' => 'select', 'options' => ['bottom-right' => ['ro' => 'Dreapta jos', 'en' => 'Bottom right'], 'bottom-left' => ['ro' => 'Stanga jos', 'en' => 'Bottom left'], 'top-right' => ['ro' => 'Dreapta sus', 'en' => 'Top right']], 'ro' => 'Pozitia butonului plutitor', 'en' => 'Floating button position', 'description_ro' => 'Alege coltul in care butonul ramane vizibil pe desktop si mobil.', 'description_en' => 'Choose the corner where the button stays visible on desktop and mobile.'],
            'button_label_ro' => ['section' => 'visual', 'type' => 'text', 'ro' => 'Eticheta butonului in romana', 'en' => 'Romanian button label', 'description_ro' => 'Textul scurt afisat in limba romana; trebuie sa indice explicit retragerea online.', 'description_en' => 'Short Romanian label; it must clearly identify online withdrawal.'],
            'button_label_en' => ['section' => 'visual', 'type' => 'text', 'ro' => 'Eticheta butonului in engleza', 'en' => 'English button label', 'description_ro' => 'Textul scurt afisat cand limba activa este engleza.', 'description_en' => 'Short label shown when English is the active language.'],
            'button_background' => ['section' => 'visual', 'type' => 'color', 'ro' => 'Culoarea de fundal a butonului', 'en' => 'Button background colour', 'description_ro' => 'Culoarea vizuala a actiunii; pastreaza un contrast suficient cu textul.', 'description_en' => 'Visual action colour; keep sufficient contrast with the text.'],
            'button_text_color' => ['section' => 'visual', 'type' => 'color', 'ro' => 'Culoarea textului butonului', 'en' => 'Button text colour', 'description_ro' => 'Culoarea textului butonului plutitor.', 'description_en' => 'Text colour used by the floating button.'],
            'button_radius' => ['section' => 'visual', 'type' => 'number', 'min' => 0, 'max' => 40, 'ro' => 'Rotunjirea butonului (px)', 'en' => 'Button radius (px)', 'description_ro' => 'Controleaza cat de rotunjit este butonul; valoarea 0 produce colturi drepte.', 'description_en' => 'Controls how rounded the button is; 0 produces square corners.'],
            'custom_css' => ['section' => 'visual', 'type' => 'textarea', 'ro' => 'CSS personalizat Pro', 'en' => 'Pro custom CSS', 'description_ro' => 'CSS avansat aplicat doar clasei butonului Pro. Foloseste-l numai pentru ajustari vizuale controlate.', 'description_en' => 'Advanced CSS applied to the Pro button class. Use it only for controlled visual adjustments.'],
            'customize_checkout' => ['section' => 'checkout', 'type' => 'checkbox', 'ro' => 'Personalizeaza informarea checkout', 'en' => 'Customise checkout disclosure', 'description_ro' => 'Activeaza textele si pozitionarea Pro in checkout; dezactivat pastreaza configuratia core.', 'description_en' => 'Enables Pro checkout copy and placement; disabled keeps the core configuration.'],
            'checkout_position' => ['section' => 'checkout', 'type' => 'select', 'options' => ['before_form' => ['ro' => 'Inaintea formularului', 'en' => 'Before checkout form'], 'before_submit' => ['ro' => 'Langa butonul de comanda', 'en' => 'Near place order button'], 'after_notes' => ['ro' => 'Dupa notele comenzii', 'en' => 'After order notes']], 'ro' => 'Pozitionare Pro in checkout', 'en' => 'Pro checkout placement', 'description_ro' => 'Alege pozitia informarii in checkout-ul clasic; Checkout Block foloseste zona sa dedicata.', 'description_en' => 'Choose placement in classic checkout; Checkout Block uses its dedicated area.'],
            'checkout_title_ro' => ['section' => 'checkout', 'type' => 'text', 'ro' => 'Titlu checkout in romana', 'en' => 'Romanian checkout title', 'description_ro' => 'Titlu custom pentru informarea precontractuala; nu elimina denumirea clara a dreptului de retragere.', 'description_en' => 'Custom pre-contract title; do not remove the clear withdrawal-right wording.'],
            'checkout_title_en' => ['section' => 'checkout', 'type' => 'text', 'ro' => 'Titlu checkout in engleza', 'en' => 'English checkout title', 'description_ro' => 'Titlu custom pentru checkout in limba engleza.', 'description_en' => 'Custom checkout title in English.'],
            'checkout_text_ro' => ['section' => 'checkout', 'type' => 'textarea', 'ro' => 'Text checkout in romana', 'en' => 'Romanian checkout text', 'description_ro' => 'Textul detaliat afisat inainte de comanda si linkul catre functia online.', 'description_en' => 'Detailed text shown before ordering and the link to the online function.'],
            'checkout_text_en' => ['section' => 'checkout', 'type' => 'textarea', 'ro' => 'Text checkout in engleza', 'en' => 'English checkout text', 'description_ro' => 'Textul detaliat afisat in limba engleza.', 'description_en' => 'Detailed text shown in English.'],
            'partial_withdrawal_enabled' => ['section' => 'rules', 'type' => 'checkbox', 'ro' => 'Permite retragerea partiala Pro', 'en' => 'Enable Pro partial withdrawal', 'description_ro' => 'Pregateste selectarea liniilor individuale dintr-o comanda; nu declanseaza automat rambursarea.', 'description_en' => 'Enables individual order-line selection; it never triggers an automatic refund.'],
            'advanced_rules' => ['section' => 'rules', 'type' => 'textarea', 'ro' => 'Reguli avansate JSON', 'en' => 'Advanced JSON rules', 'description_ro' => 'Reguli de forma when.category, when.tag, when.product_type, when.order_status, when.country sau when.shipping_method. Rezultatul este orientativ si nu blocheaza clientul.', 'description_en' => 'Rules can use when.category, when.tag, when.product_type, when.order_status, when.country or when.shipping_method. Results are indicative and never block the customer.'],
            'delivery_provider' => ['section' => 'delivery', 'type' => 'select', 'options' => ['order_meta' => ['ro' => 'Meta comanda WooCommerce', 'en' => 'WooCommerce order meta'], 'status_fallback' => ['ro' => 'Meta + status livrat', 'en' => 'Meta + delivered status fallback']], 'ro' => 'Provider data livrarii', 'en' => 'Delivery date provider', 'description_ro' => 'Alege sursa datei de livrare; statusul comenzii nu este tratat singur ca dovada a livrarii decat daca activezi fallback-ul.', 'description_en' => 'Choose the delivery date source; order status alone is not treated as proof unless fallback is enabled.'],
            'delivery_meta_key' => ['section' => 'delivery', 'type' => 'text', 'ro' => 'Cheie meta data livrarii Pro', 'en' => 'Pro delivery date meta key', 'description_ro' => 'Cheia meta populata de integrarea ta de curier sau ERP.', 'description_en' => 'Meta key populated by your courier or ERP integration.'],
            'delivered_status' => ['section' => 'delivery', 'type' => 'text', 'ro' => 'Status considerat livrat', 'en' => 'Status considered delivered', 'description_ro' => 'Status WooCommerce folosit doar impreuna cu providerul status_fallback.', 'description_en' => 'WooCommerce status used only with the status_fallback provider.'],
            'deadline_reminder_days' => ['section' => 'delivery', 'type' => 'number', 'min' => 0, 'max' => 60, 'ro' => 'Avertizare termen inainte cu (zile)', 'en' => 'Deadline reminder lead time (days)', 'description_ro' => 'Numarul de zile inainte de termen pentru panoul Pro si scanarea cron.', 'description_en' => 'Days before the deadline used by the Pro dashboard and cron scan.'],
            'api_enabled' => ['section' => 'integrations', 'type' => 'checkbox', 'ro' => 'Activeaza API-ul Pro', 'en' => 'Enable Pro API', 'description_ro' => 'Expune endpoint-uri REST protejate pentru integrari externe; nu activa fara secret.', 'description_en' => 'Exposes protected REST endpoints for external integrations; do not enable without a secret.'],
            'api_secret' => ['section' => 'integrations', 'type' => 'text', 'ro' => 'Secret API Pro', 'en' => 'Pro API secret', 'description_ro' => 'Secretul Bearer pentru endpoint-urile Pro. Pastreaza-l in afara logurilor si nu-l distribui public.', 'description_en' => 'Bearer secret for Pro endpoints. Keep it out of logs and never publish it.'],
            'webhook_url' => ['section' => 'integrations', 'type' => 'url', 'ro' => 'URL webhook retrageri', 'en' => 'Withdrawal webhook URL', 'description_ro' => 'Endpoint HTTPS care primeste evenimentele create si schimbate; lasat gol dezactiveaza trimiterea.', 'description_en' => 'HTTPS endpoint receiving created and changed events; empty disables delivery.'],
            'webhook_secret' => ['section' => 'integrations', 'type' => 'text', 'ro' => 'Secret semnare webhook', 'en' => 'Webhook signing secret', 'description_ro' => 'Secret folosit pentru semnatura HMAC SHA-256 din headerul X-Zion-Signature.', 'description_en' => 'Secret used for the HMAC SHA-256 signature in X-Zion-Signature.'],
            'webhook_events' => ['section' => 'integrations', 'type' => 'textarea', 'ro' => 'Evenimente webhook', 'en' => 'Webhook events', 'description_ro' => 'Cate un eveniment pe linie: withdrawal.created si withdrawal.status_changed.', 'description_en' => 'One event per line: withdrawal.created and withdrawal.status_changed.'],
            'white_label' => ['section' => 'agency', 'type' => 'checkbox', 'ro' => 'Ascunde brandingul Zion3D', 'en' => 'Hide Zion3D branding', 'description_ro' => 'Ascunde marca din butonul Pro; foloseste optiunea doar in scenarii de agentie autorizate.', 'description_en' => 'Hides Zion branding from the Pro button; use only for authorised agency scenarios.'],
            'agency_label' => ['section' => 'agency', 'type' => 'text', 'ro' => 'Nume agentie / brand', 'en' => 'Agency / brand name', 'description_ro' => 'Numele afisat in interfața de administrare Pro cand brandingul este personalizat.', 'description_en' => 'Name shown in the Pro administration interface when branding is customised.'],
        ];
    }

    public function sanitize(string $key, mixed $value): mixed
    {
        $field = self::schema()[$key] ?? null;
        if (! is_array($field)) {
            return null;
        }

        return match ($field['type']) {
            'checkbox' => empty($value) ? 0 : 1,
            'number' => max((int) ($field['min'] ?? 0), min((int) ($field['max'] ?? PHP_INT_MAX), absint($value))),
            'color' => sanitize_hex_color((string) $value) ?: (string) self::defaults()[$key],
            'select' => array_key_exists((string) $value, $field['options']) ? (string) $value : (string) array_key_first($field['options']),
            'textarea' => $key === 'advanced_rules' ? $this->sanitize_rules((string) $value) : sanitize_textarea_field((string) $value),
            'url' => esc_url_raw((string) $value),
            default => sanitize_text_field((string) $value),
        };
    }

    /** @return array<string, mixed> */
    public function update(string $key, mixed $value): array
    {
        $settings = $this->all();
        $settings[$key] = $value;
        update_option(self::OPTION, $settings, false);
        return $settings;
    }

    private function sanitize_rules(string $value): string
    {
        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return '[]';
        }

        $rules = [];
        foreach ($decoded as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $when = [];
            foreach (['category', 'tag', 'product_type', 'order_status', 'country', 'shipping_method'] as $condition) {
                if (isset($rule['when'][$condition])) {
                    $when[$condition] = sanitize_text_field((string) $rule['when'][$condition]);
                }
            }
            if ($when === []) {
                continue;
            }
            $rules[] = [
                'name' => sanitize_text_field((string) ($rule['name'] ?? 'Pro rule')),
                'when' => $when,
                'state' => in_array(($rule['state'] ?? ''), ['standard', 'potential_exception', 'unknown'], true) ? $rule['state'] : 'potential_exception',
                'exception_code' => strtoupper(sanitize_key((string) ($rule['exception_code'] ?? ''))),
                'reason_ro' => sanitize_textarea_field((string) ($rule['reason_ro'] ?? '')),
                'reason_en' => sanitize_textarea_field((string) ($rule['reason_en'] ?? '')),
            ];
        }

        return (string) wp_json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
