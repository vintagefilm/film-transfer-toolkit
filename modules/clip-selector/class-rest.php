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
        // 1.5.5: sign-in before add to cart. /pending saves a guest's clips (server-built, signed) under a one-time
        // token; /resume claims one for the signed-in user (backup for the template_redirect claim).
        register_rest_route(self::NS, '/pending', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'pending'],
            'permission_callback' => [__CLASS__, 'check_nonce'],
            'args' => ['clips' => ['required' => true], 'return' => ['required' => true]],
        ]);
        register_rest_route(self::NS, '/resume', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'resume'],
            'permission_callback' => [__CLASS__, 'check_nonce'],
            'args' => ['token' => ['required' => true]],
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

    /** 1.5.5: opt-out signature for one selector with require_login="no" while the site setting is on. */
    public static function login_optout_sig($product_id)
    {
        return substr(hash_hmac('sha256', 'optout|' . absint($product_id), wp_salt('auth') . 'fwcc-login'), 0, 20);
    }

    public static function add(WP_REST_Request $req)
    {
        $clip = self::clip_from_request($req);
        if (is_wp_error($clip)) return $clip;

        // 1.5.5: with "Require sign-in to add to cart" on, guests must go through /pending (sign in, then resume).
        // 1.5.8: login_mode = add_then_prompt lets guests add (server-priced as usual); the browser then shows the prompt.
        $set = FTT_Clip_Settings::get();
        $prompt = !is_user_logged_in() && $set['require_login'] && 'add_then_prompt' === $set['login_mode'];
        if (!is_user_logged_in() && $set['require_login'] && !$prompt
            && !hash_equals(self::login_optout_sig($clip['product_id']), (string) $req->get_param('login_optout'))) {
            return new WP_Error('fwcc_login_required', 'Please sign in or create an account to add clips to your cart.', ['status' => 401]);
        }

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
        $auth = [];
        if (!is_user_logged_in() && 'add_then_prompt' === $set['login_mode']) {
            $back = 'cart' === $set['after_login'] ? FTT_Clip_Resume::cart_url() : FTT_Clip_Resume::safe_return((string) $req->get_param('return'));
            if ($set['require_login']) FTT_Clip_Resume::remember_back($back);
            $auth = ['login_url' => FTT_Clip_Resume::auth_url('login', $back), 'register_url' => FTT_Clip_Resume::auth_url('register', $back)];
        }
        return [
            'auth'      => $auth, // 1.5.8: sign-in / create account links when login_mode = add_then_prompt (guests only)
            'added'     => FTT_Clip::item_id($clip),
            'clip'      => $clip,
            'cart_hash' => $cart->cart_hash ?? '',
            'items'     => $items,
            'checkout'  => function_exists('fluent_cart_get_option') ? '' : home_url('/checkout/'),
        ];
    }

    public static function pending(WP_REST_Request $req)
    {
        if (is_user_logged_in()) {
            return new WP_Error('fwcc_signed_in', 'You are already signed in; add the clips to the cart directly.', ['status' => 400]);
        }
        $clips = $req->get_param('clips');
        if (!is_array($clips)) return new WP_Error('fwcc_bad_input', 'clips must be a list.', ['status' => 400]);
        foreach ($clips as $c) {
            // A client price is never used; a differing one is refused so tampering is visible (same as /add).
            if (!is_array($c)) return new WP_Error('fwcc_bad_input', 'Bad clip.', ['status' => 400]);
            if (isset($c['client_price_cents']) && $c['client_price_cents'] !== '') {
                $b = FTT_Clip::build($c['product_id'] ?? 0, $c['clip_id'] ?? '', $c['in'] ?? null, $c['out'] ?? null, $c['fps'] ?? '', $c['seq'] ?? 1, $c['name'] ?? '');
                if (!is_wp_error($b) && (int) $c['client_price_cents'] !== (int) $b['price_cents']) {
                    return new WP_Error('fwcc_price_mismatch', 'Price mismatch: the server price is ' . $b['price_cents'] . ' cents. Nothing was saved.', ['status' => 409, 'server_price_cents' => $b['price_cents']]);
                }
            }
        }
        $r = FTT_Clip_Resume::create($clips, (string) $req->get_param('return'));
        if (is_wp_error($r)) {
            $d = $r->get_error_data(); if (!is_array($d) || empty($d['status'])) $r->add_data(['status' => 400]);
        }
        return $r;
    }

    public static function resume(WP_REST_Request $req)
    {
        $r = FTT_Clip_Resume::claim((string) $req->get_param('token'));
        if (is_wp_error($r)) return $r;
        $r['cart_url'] = FTT_Clip_Resume::cart_url();
        $r['after_login'] = FTT_Clip_Settings::get()['after_login'];
        return $r;
    }
}
