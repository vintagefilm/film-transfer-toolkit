<?php
if (!defined('ABSPATH')) exit;

class FTT_Clip_Rest
{
    const NS = 'ftt/v1/clip';

    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('send_headers', [__CLASS__, 'nocache_for_cart_holders']);
        // 1.5.12: fresh wp_rest nonce for pages served from a page cache (the nonce baked into the page can be expired).
        // admin-ajax knows the signed-in user from the cookie without a nonce; same-origin only (no CORS echo).
        add_action('wp_ajax_' . self::AJAX_NONCE, [__CLASS__, 'ajax_nonce']);
        add_action('wp_ajax_nopriv_' . self::AJAX_NONCE, [__CLASS__, 'ajax_nonce']);
    }

    const AJAX_NONCE = 'ftt_clip_nonce';

    /** 1.5.12: never let a page cache, CDN or browser store a nonce response. */
    public static function nonce_headers()
    {
        if (headers_sent()) return;
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
        header('X-LiteSpeed-Cache-Control: no-cache');
        header('X-Robots-Tag: noindex');
    }

    /** 1.5.12: the nonce payload. Cookie-authenticated user (or visitor) only; nothing else is returned. */
    public static function nonce_payload()
    {
        return ['nonce' => wp_create_nonce('wp_rest'), 'ttl' => (int) apply_filters('nonce_life', DAY_IN_SECONDS)];
    }

    /** 1.5.12: admin-ajax.php?action=ftt_clip_nonce (GET). Primary source: correct for signed-in users and visitors. */
    public static function ajax_nonce()
    {
        self::nonce_headers();
        wp_send_json(self::nonce_payload());
    }

    /**
     * 1.5.12: GET /ftt/v1/clip/nonce, a fallback for sites that block admin-ajax for visitors. WordPress treats a REST
     * request without a valid nonce as a visitor, so this only ever mints a visitor nonce (or one for a user whose request
     * already carried a valid nonce); it never hands a signed-in user's nonce to another origin.
     */
    public static function rest_nonce(WP_REST_Request $req)
    {
        self::nonce_headers();
        $r = rest_ensure_response(self::nonce_payload());
        $r->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $r->header('X-LiteSpeed-Cache-Control', 'no-cache');
        return $r;
    }

    public static function routes()
    {
        $args = [
            'product_id' => ['required' => true],
            'clip_id'    => ['required' => true],
            'in'         => ['required' => true],
            'out'        => ['required' => true],
            'fps'        => ['required' => true],
            'seq'        => ['required' => false, 'default' => 1],
            'name'       => ['required' => false, 'default' => ''],
        ];
        register_rest_route(self::NS, '/quote', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'quote'],
            'permission_callback' => [__CLASS__, 'check_nonce'], 'args' => $args,
        ]);
        register_rest_route(self::NS, '/add', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'add'],
            'permission_callback' => [__CLASS__, 'check_nonce'], 'args' => $args,
        ]);
        register_rest_route(self::NS, '/nonce', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'rest_nonce'], 'permission_callback' => '__return_true',
        ]);
        // 1.5.9: live mini-cart summary (count + subtotal) so a page served from a page cache can correct the header cart.
        register_rest_route(self::NS, '/minicart', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'minicart'], 'permission_callback' => '__return_true',
        ]);
    }

    /** Require the standard wp_rest nonce (sent as X-WP-Nonce) plus a simple rate limit. */
    public static function check_nonce(WP_REST_Request $req)
    {
        $nonce = $req->get_header('x_wp_nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('fwcc_nonce', 'Missing or invalid nonce.', ['status' => 403]);
        }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'x';
        $key = 'ftt_clip_rl_' . md5($ip);
        $n = (int) get_transient($key);
        if ($n > 60) return new WP_Error('fwcc_rate', 'Too many requests, try again in a minute.', ['status' => 429]);
        set_transient($key, $n + 1, MINUTE_IN_SECONDS);
        return true;
    }

    private static function clip_from_request(WP_REST_Request $req)
    {
        return FTT_Clip::build($req['product_id'], $req['clip_id'], $req['in'], $req['out'], $req['fps'], $req['seq'], $req['name']);
    }

    public static function quote(WP_REST_Request $req)
    {
        $clip = self::clip_from_request($req);
        if (is_wp_error($clip)) return $clip;
        return ['clip' => $clip];
    }

    /** 1.5.9: fresh mini-cart numbers (the CartCheckoutHelper singleton is reset so a stale empty cart is never reused). */
    public static function mini_cart_summary()
    {
        $out = ['count' => 0, 'qty' => 0, 'subtotal' => '', 'subtotal_cents' => 0];
        if (!class_exists('\FluentCart\App\Helpers\CartHelper')) return $out;
        $cart = \FluentCart\App\Helpers\CartHelper::getCart(null, false);
        $data = ($cart && !is_wp_error($cart)) ? (array) ($cart->cart_data ?? []) : [];
        $cents = 0;
        foreach ($data as $it) {
            $q = (int) ($it['quantity'] ?? 1);
            $out['qty'] += $q;
            $cents += (int) round((float) ($it['unit_price'] ?? $it['price'] ?? 0)) * $q;
        }
        $out['count'] = count($data);
        $h = '\FluentCart\App\Helpers\CartCheckoutHelper';
        $fmt = '';
        if ($data && class_exists($h)) {
            try {
                if (method_exists($h, 'resetInstance')) $h::resetInstance();
                $fmt = (string) $h::make()->getItemsAmountSubtotal(true, true);
                $raw = (int) $h::make()->getItemsAmountSubtotal(false, false);
                if ($raw > 0) $cents = $raw;
            } catch (\Throwable $e) { $fmt = ''; }
        }
        if ($fmt === '' || ($cents > 0 && preg_match('/^\D*0[.,]00\D*$/', $fmt))) {
            $fmt = class_exists('\FluentCart\App\Helpers\Helper') ? (string) \FluentCart\App\Helpers\Helper::toDecimal($cents, true) : '$' . number_format($cents / 100, 2);
        }
        $out['subtotal'] = html_entity_decode(wp_strip_all_tags($fmt), ENT_QUOTES, 'UTF-8'); // plain text, e.g. &#36; -> $
        $out['subtotal_cents'] = $cents;
        return $out;
    }

    public static function minicart(WP_REST_Request $req)
    {
        $r = rest_ensure_response(self::mini_cart_summary());
        $r->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $r;
    }

    /** 1.5.9: a guest who holds a FluentCart cart gets no-cache headers, so a page cache does not keep serving an empty header cart. */
    public static function nocache_for_cart_holders()
    {
        if (is_admin() || is_user_logged_in() || empty($_COOKIE['fct_cart_hash'])) return;
        if (!headers_sent()) nocache_headers();
    }

    public static function add(WP_REST_Request $req)
    {
        $clip = self::clip_from_request($req);
        if (is_wp_error($clip)) return $clip;

        // 1.5.11: every visitor, signed in or not, can add clips (standard cart flow). Sign-in, guest checkout and account
        // creation are handled by FluentCart at the cart and checkout, never here.
        // A client-supplied price is never used. If one is sent and it differs
        // from the server price, the request is rejected so tampering is visible.
        $client = $req->get_param('client_price_cents');
        if ($client !== null && $client !== '' && (int) $client !== (int) $clip['price_cents']) {
            return new WP_Error('fwcc_price_mismatch', 'Price mismatch: the server price is ' . $clip['price_cents'] . ' cents. Nothing was added.', [
                'status' => 409, 'server_price_cents' => $clip['price_cents'],
            ]);
        }

        if (!class_exists('\FluentCart\App\Helpers\CartHelper')) {
            return new WP_Error('fwcc_no_cart', 'FluentCart is not active.', ['status' => 500]);
        }
        $cart = \FluentCart\App\Helpers\CartHelper::getCart(null, true);
        if (!$cart || is_wp_error($cart)) {
            return new WP_Error('fwcc_cart', 'Could not open the cart.', ['status' => 500]);
        }
        $result = $cart->addByCustom(FTT_Clip::to_variation($clip), ['quantity' => 1]);
        if (is_wp_error($result)) {
            $result->add_data(['status' => 400]);
            return $result;
        }
        $cart = \FluentCart\App\Helpers\CartHelper::getCart();
        $items = [];
        foreach ((array) ($cart->cart_data ?? []) as $it) {
            $items[] = ['id' => $it['id'] ?? '', 'title' => $it['title'] ?? '', 'unit_price' => $it['unit_price'] ?? null, 'quantity' => $it['quantity'] ?? null];
        }
        return [
            'added'     => FTT_Clip::item_id($clip),
            'clip'      => $clip,
            'cart_hash' => $cart->cart_hash ?? '',
            'items'     => $items,
            'mini_cart' => self::mini_cart_summary(), // 1.5.9: lets the page update the header mini-cart right away
            'cart_url'  => FTT_Clip_Cart::cart_url(), // 1.5.11: for the "View cart" link
            'checkout'  => function_exists('fluent_cart_get_option') ? '' : home_url('/checkout/'),
        ];
    }
}
