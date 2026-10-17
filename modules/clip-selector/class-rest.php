<?php
if (!defined('ABSPATH')) exit;

class FTT_Clip_Rest
{
    const NS = 'ftt/v1/clip';

    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'routes']);
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

    public static function add(WP_REST_Request $req)
    {
        $clip = self::clip_from_request($req);
        if (is_wp_error($clip)) return $clip;

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
            'checkout'  => function_exists('fluent_cart_get_option') ? '' : home_url('/checkout/'),
        ];
    }
}
