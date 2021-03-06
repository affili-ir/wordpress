<?php

namespace AffiliIR;


require_once 'Woocommerce.php';
require_once 'ListTable.php';
require_once 'Installer.php';
require_once 'ActivePluginsCheck.php';


use AffiliIR\ActivePluginsCheck as AffiliIR_ActivePluginsCheck;
use AffiliIR\Woocommerce as AffiliIR_Woocommerce;
use AffiliIR\ListTable as AffiliIR_ListTable;
use AffiliIR\Installer as AffiliIR_Installer;

class Action
{
    protected $plugin_name = 'affili_ir';

    private $table_name;
    private $wpdb;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb       = $wpdb;
        $this->table_name = $wpdb->prefix . 'affili';
    }

    public function init()
    {
        load_plugin_textdomain('affili', false, 'affili/languages');
    }

    public function menu()
    {
        $page_title = __('Affili Plugin', $this->plugin_name);
        $menu_title = __('Affili', $this->plugin_name);
        $capability = 'manage_options';
        $menu_slug  = $this->plugin_name;
        $function   = [$this, 'renderPage'];
        $icon_url   = 'data:image/svg+xml;base64,'. base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 173.16 173.16"><defs><style>.cls-1{fill:#fff;fill-rule:evenodd;}</style></defs><title>Asset 4</title><g id="Layer_2" data-name="Layer 2"><g id="Layer_1-2" data-name="Layer 1"><path class="cls-1" d="M173.16,150.47V86.58A86.59,86.59,0,1,0,134,159V115.45c0-3.75-5.22-13.31-12-13.31v8.76c0,10.8-15.87,30.24-47.25,30.24-27.11,0-52-24.37-52-54.56a63.89,63.89,0,0,1,127.78,0v63.89a22.69,22.69,0,0,0,22.69,22.69Z"/><path class="cls-1" d="M130.22,92.17a7,7,0,1,0-7-7A7,7,0,0,0,130.22,92.17Z"/></g></g></svg>');
        $position   = 100;

        add_menu_page(
            $page_title,
            $menu_title,
            $capability,
            $menu_slug,
            $function,
            $icon_url,
            $position
        );
    }

    public function renderPage()
    {
        $show_brand  = AffiliIR_ActivePluginsCheck::wooBrandActiveCheck();
        $woocommerce = new AffiliIR_Woocommerce;

        $account_id  = $this->getAccountId();
        $custom_code = $this->getCustomCode();
        $plugin_name = $this->plugin_name;

        $list_table = new AffiliIR_ListTable();
        $list_table->prepare_items();

        include_once __DIR__.'/../views/form.php';
    }

    public function loadAdminStyles()
    {
        wp_enqueue_style( 'affili-ir-admin-style', plugins_url('assets/css/admin-style-main.css',__DIR__), false, '1.0.0' );

        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css' );
	    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js', array('jquery') );

        wp_enqueue_script('affili-ir-admin-script',  plugins_url('assets/js/admin-script-main.js', __DIR__), array( 'jquery', 'select2' ) );
    }

    public function setAccountId()
    {
        $nonce     = wp_verify_nonce($_POST['affili_set_account_id'], 'eadkf#adk$fawlkaawwlRRe');
        $condition = isset($_POST['affili_set_account_id']) && $nonce;

        if($condition) {
            $this->createTableIfNotExists();

            $account_id = sanitize_text_field($_POST['account_id']);
            $data = [
                'name'  => 'account_id',
                'value' => $account_id,
            ];

            $account_id_model = $this->getAccountId();

            if(empty($account_id_model)) {
                $this->wpdb->insert($this->table_name, $data, '%s');
            }else {
                $this->wpdb->update($this->table_name, $data, [
                    'id' => $account_id_model->id
                ]);
            }

            $woocommerce = new AffiliIR_Woocommerce;
            $woocommerce->insertCommissionKeys($_POST['item']);

            $admin_notice = "success";
            $message      = __('Data saved successful.', $this->plugin_name);

            $this->customRedirect($message, $admin_notice);
            exit;
        }
        else {
            wp_die(
                __( 'Invalid nonce specified', $this->plugin_name ),
                __( 'Error', $this->plugin_name ),
                [
                    'response' 	=> 403,
                    'back_link' => 'admin.php?page=' . $this->plugin_name,
                ]
            );
        }
    }

    public function setCustomCode()
    {
        $nonce     = wp_verify_nonce($_POST['affili_custom_code'], 'eadkf#adk$fawlkrrt2RRe');
        $condition = isset($_POST['affili_custom_code']) && $nonce;

        if($condition) {
            $custom_code = $_POST['custom_code'];

            $data = [
                'name'  => 'custom_code',
                'value' => $custom_code,
            ];

            $custom_code_model = $this->getCustomCode();
            if(empty($custom_code_model)) {
                $this->wpdb->insert($this->table_name, $data, '%s');
            }else {
                $this->wpdb->update($this->table_name, $data, [
                    'id' => $custom_code_model->id
                ]);
            }

            $admin_notice = "success";
            $message      = __('Data saved successful.', $this->plugin_name);

            $this->customRedirect($message, $admin_notice);
            exit;
        }else {
            wp_die(
                __( 'Invalid nonce specified', $this->plugin_name ),
                __( 'Error', $this->plugin_name ),
                [
                    'response' 	=> 403,
                    'back_link' => 'admin.php?page=' . $this->plugin_name,
                ]
            );
        }
    }

    public function displayFlashNotices() {
        $notices = get_option('affili_flash_notices', []);

        // Iterate through our notices to be displayed and print them.
        foreach ($notices as $notice) {
            printf('<div class="notice notice-%1$s %2$s" style="margin:25px 0px 0 20px;"><p>%3$s</p></div>',
                $notice['type'],
                $notice['dismissible'],
                $notice['notice']
            );
        }

        // We reset our options to prevent notices being displayed forever.
        if(!empty($notices)) {
            delete_option('affili_flash_notices', []);
        }
    }

    public function setAffiliJs()
    {
        $script = $this->createInlineScript();

        wp_enqueue_script("affili-ir-script", "https://analytics.affili.ir/scripts/affili-js.js");
        wp_add_inline_script("affili-ir-script", $script);
    }

    public function createInlineScript()
    {
        $model = $this->getAccountId();

        $script = '';

        if($model) {
            $script .= 'window.affiliData = window.affiliData || [];function affili(){affiliData.push(arguments);}'.PHP_EOL;
            $script .= 'affili("create", "'.$model->value.'");'.PHP_EOL;
            $script .= 'affili("detect");'.PHP_EOL;

            $custom_code = $this->getCustomCode();
            if($custom_code) {
                $script .= $custom_code->value;
            }
        }

        return $script;
    }

    public function trackOrders($order_id)
    {
        $order_id    = apply_filters('woocommerce_thankyou_order_id', absint($GLOBALS['order-received']));
        $order_key   = apply_filters('woocommerce_thankyou_order_key', empty($_GET['key']) ? '' : wc_clean($_GET['key']));
        $woocommerce = new AffiliIR_Woocommerce;
        $order       = wc_get_order($order_id);

        if ($order_id <= 0) return;

        $order_key_check = $woocommerce->isWoo3() ? $order->get_order_key() : $order->order_key;

        if ($order_key_check !== $order_key) return;

        $data = $woocommerce->getOrderData($order);

        $commissions  = $data['commissions'];
        $options      = $data['options'];
        $external_id  = $data['external_id'];
        $amount       = $data['amount'];
        $is_multi     = $data['is_multi'];
        $default_name = $data['default_name'];
        // $order_key   = $data['order_key'];

        // Check if we have multiple commission names
        if($is_multi) {
            $script = "affili('conversionMulti', '{$external_id}', '{$amount}', {$commissions}, {$options});";
        }else {
            $script = "affili('conversion', '{$external_id}', '{$amount}', '{$default_name}', {$options})";
        }

        wp_add_inline_script("affili-ir-script", $script);
    }

    public function loadTextDomain()
    {
        $lang_dir = AFFILI_BASENAME.'/languages/';
        load_plugin_textdomain($this->plugin_name, false, $lang_dir);
    }

    public function setup()
    {
        add_action('plugins_loaded', [$this, 'loadTextDomain']); // load plugin translation file

        add_action('admin_menu', [$this, 'menu']);
        add_action('init', [$this, 'init']);
        add_action('admin_enqueue_scripts', [$this, 'loadAdminStyles']);
        add_action('admin_post_set_account_id', [$this, 'setAccountId']);
        add_action('admin_post_set_custom_code', [$this, 'setCustomCode']);

        add_action('admin_notices', [$this, 'displayFlashNotices'], 12);
        add_action('wp_head', [$this, 'setAffiliJs'] );

        add_action('woocommerce_thankyou', [$this, 'trackOrders']);

        add_action('wp_ajax_affili_find_category', [$this, 'findCategoryAjax']);
        add_action('wp_ajax_affili_find_brand', [$this, 'findBrandAjax']);
    }

    public static function factory()
    {
        static $instance;

        if(!$instance) {
            $instance = new static;

            $instance->setup();
        }

        return $instance;
    }

    protected function getAccountId()
    {
        $result = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE name = 'account_id' limit 1"
        );
        $result = is_array($result) ? array_pop($result) : [];

        return $result;
    }

    protected function getCustomCode()
    {
        $result = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE name = 'custom_code' limit 1"
        );
        $result = is_array($result) ? array_pop($result) : [];

        if($result) {
            $result->value = stripslashes($result->value);
        }

        return $result;
    }

    protected function customRedirect($message, $admin_notice = 'success')
    {
        $this->addFlashNotice(
            $message, $admin_notice, true
        );

        wp_redirect('admin.php?page='.$this->plugin_name);
    }

    protected function addFlashNotice($notice = '', $type = 'success', $dismissible = true ) {
        $notices = get_option('affili_flash_notices', []);

        $dismissible_text = $dismissible ? 'is-dismissible' : '';

        array_push($notices, [
            'notice'        => $notice,
            'type'          => $type,
            'dismissible'   => $dismissible_text
        ]);

        // We update the option with our notices array
        update_option('affili_flash_notices', $notices );
    }

    public function findCategoryAjax()
    {
        // we will pass category IDs and titles to this array
        $return = [];

        $search_results = (new AffiliIR_Woocommerce)->getCategories(null, [
            'name__like' => $_GET['q'],
        ]);
        foreach($search_results as $result) {
            $return[] = [
                $result->cat_ID,
                $result->cat_name,
            ];
        }
        echo json_encode( $return );
        wp_die();
    }

    public function findBrandAjax()
    {
        // we will pass brand IDs and names to this array
        $return = [];

        $search_results = (new AffiliIR_Woocommerce)->getBrands(null, [
            'name__like' => $_GET['q'],
        ]);

        foreach($search_results as $result) {
            $return[] = [
                $result->term_id,
                $result->name,
            ];
        }
        echo json_encode( $return );
        wp_die();
    }

    private function createTableIfNotExists()
    {
        $sql        = AffiliIR_Installer::sqlString();
        $table_name = $this->wpdb->prefix.AffiliIR_Installer::$table;

        maybe_create_table($table_name, $sql);
    }
}