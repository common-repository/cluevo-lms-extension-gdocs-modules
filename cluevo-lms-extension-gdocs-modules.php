<?php

/**
 * Plugin Name: CLUEVO LMS Extension: Google Documents as modules
 * Plugin URI:  https://www.cluevo.at
 * Description: Enables you to use Google Documents as modules in your LMS
 * Version:     1.1.1
 * Author:      CLUEVO
 * Author URI:  https://profiles.wordpress.org/cluevo/
 * Text Domain: cluevo-ext-gdocs
 * Domain Path: /lang
 * License:     GPL2
 * CLUEVO tested up to: 1.13.0
 */

if (!class_exists('CluevoExt_GDocsModules')) {

  class CluevoExt_GDocsModules
  {

    public static function adminInit()
    {
      if (!self::dependency_check()) {
        add_action('init', 'CluevoExt_GDocsModules::load_plugin_textdomain');
        add_action('cluevo_handle_misc_module_url_input', 'CluevoExt_GDocsModules::handle_misc_module_url_input');
        add_action('cluevo_enqueue_lms_modules_ui_js', 'CluevoExt_GDocsModules::enqueue_admin_js');
        add_action('cluevo_register_module_types', 'CluevoExt_GDocsModules::register_module_ui');
      }
    }

    public static function frontendInit()
    {
      if (!self::dependency_check()) {
        add_action('wp_head', 'CluevoExt_GDocsModules::add_inline_styles');
        add_action('cluevo_enqueue_module_scripts', 'CluevoExt_GDocsModules::enqueue_frontend_scripts');
        add_action('cluevo_save_module_progress', 'CluevoExt_GDocsModules::save_module_progress');
        add_action('cluevo_display_module', 'CluevoExt_GDocsModules::display_module');
      }
    }

    public static function register_module_ui($args)
    {
      foreach ($args["types"] as $key => $value) {
        if (!empty($value["alt-type"]) && $value["alt-type"] == "cluevo-lms-extension-gdocs") {
          unset($args["types"][$key]);
          break;
        }
      }
      $args["types"][] = [
        'name' => __('Google Documents', 'cluevo-ext-gdocs'),
        'key' => 'gdocs',
        'icon' => plugins_url("/images/icon-module-ui-gdocs_256x256.png", __FILE__),
        'description' => __("Installs a Google Document: Doc (Word), Sheet (Excel), Slide (PowerPoint), Image or a Google Form.<br/>You can get your embed code in your document under File -> Publish to the Web -> Embed. After publishing you can copy the embed code and paste it here.", "cluevo-ext-gdocs"),
        'field' => 'textarea',
        'field-placeholder' => __('Paste your embed code here', 'cluevo-ext-gdocs'),
        'button-label' => __('Install Module', 'cluevo-ext-gdocs'),
        "form-class" => "cluevo-ext-gdocs"
      ];
    }

    public static function enqueue_frontend_scripts()
    {
      wp_register_script('cluevo-gdocs-module-script', plugins_url('/js/cluevo-gdocs-module.js', __FILE__), array('jquery', 'cluevo-lightbox'), '20151215', true);
      wp_enqueue_script('cluevo-lightbox');
      wp_enqueue_script('cluevo-gdocs-module-script');
    }

    public static function add_inline_styles()
    {
      $css = '#cluevo-module-lightbox-overlay.gdocs .cluevo-gdocs-element { margin: 0 auto; }';
      echo "<style>$css</style>";
    }

    public static function enqueue_admin_js()
    {
      wp_register_script('cluevo-gdocs-module-admin-script', plugins_url('/js/cluevo-gdocs-admin-module.js', __FILE__), array('jquery', "cluevo-admin-module-page"), '20151215', true);
      wp_localize_script(
        'cluevo-gdocs-module-admin-script',
        'cluevoGDocsStrings',
        array(
          "titleLabel" => __("Document Title", 'cluevo-ext-gdocs')
        )
      );
      wp_enqueue_script('cluevo-gdocs-module-admin-script');
      wp_register_style('cluevo-ext-gdocs-admin-css', plugins_url('/styles/style.css', __FILE__), array(), CLUEVO_VERSION);  // admin page styles
      wp_enqueue_style('cluevo-ext-gdocs-admin-css');
    }

    public static function save_module_progress($args)
    {
      $userId = $args["user_id"];
      $itemId = $args["item_id"];
      $module = $args["module"];
      if (empty($module) || !property_exists($module, "type_name") || strtolower($module->type_name) !== "google docs") return;
      $moduleId = $module->module_id;
      if (!empty($args["request"])) {
        $request = $args["request"];
        $max = (float)$request['max'];
        $score = (float)$request["score"];
      } else {
        $max = 100;
        $score = 100;
      }

      if ($max <= 0)
        return false;

      if (!empty($userId) && !empty($module)) {

        $attempt = 0;
        $attempt = cluevo_get_current_attempt_id($userId, $moduleId);
        $attempt++;

        global $wpdb;
        $table = $wpdb->prefix . CLUEVO_DB_TABLE_MODULES_PROGRESS;
        $complete = 'completed';
        $success = 'passed';

        $sql = "INSERT INTO $table SET
          user_id = %d,
          module_id = %d,
          attempt_id = %d,
          score_min = 0,
          score_max = %s,
          score_raw = %s,
          score_scaled = %s,
          is_practice = %s,
          completion_status = %s,
          success_status = %s
          ON DUPLICATE KEY UPDATE
          score_raw = %s,
          score_scaled = %s,
          completion_status = %s,
          success_status = %s";

        $scaled = 1;
        $practice = ($attempt == 0) ? 0 : 1;

        $wpdb->query(
          $wpdb->prepare($sql, [
            $userId,
            $moduleId,
            $attempt,
            $max,
            $score,
            $scaled,
            $practice,
            $complete,
            $success,
            $score,
            $scaled,
            $complete,
            $success
          ])
        );

        $state = cluevo_get_module_progress($userId, $module->module_id);
        $practiceMode = cluevo_user_module_progress_complete($userId, $module->module_id);
        $pointsToAdd = 0;
        $sourceType = "";
        $item = null;
        if (!empty($itemId)) {
          $item = cluevo_get_learning_structure_item($itemId);
        }
        if (!$item) return;
        if (!$practiceMode) {
          $progressPoints = cluevo_get_user_module_progression_points($userId, $module->module_id);
          $pointsWorth = !empty($item->points_worth) ? $item->points_worth : 0;
          $sourceType = "gdocs-module";
          if ($pointsToAdd > 0) {
            cluevo_add_points_to_user($userId, $pointsToAdd, $sourceType, $module->module_id, $attempt);
            do_action('cluevo_award_user_progress_points_from_module', [
              "user_id" => $userId,
              "points_added" => $pointsWorth,
              "module_id" => $module->module_id,
              "item_id" => $itemId,
              "attempt_id" => $attempt
            ]);
          }
        } else {
          if ($state["completion_status"] == "completed" || $state["lesson_status"] == "completed" || $state["lesson_status"] == "passed") {
            $pointsToAdd = (!empty($item->practice_points)) ? $item->practice_points : 0;
            $sourceType = "gdocs-module-practice";
            cluevo_add_points_to_user(
              $userId,
              $pointsToAdd,
              $sourceType,
              $module->module_id,
              $attempt
            );
            do_action('cluevo_award_user_practice_points_from_module', [
              "user_id" => $userId,
              "points_added" => $pointsToAdd,
              "module_id" => $module->module_id,
              "item_id" => $itemId,
              "attempt_id" => $attempt
            ]);
          }
        }
        if ($pointsToAdd > 0) {
          do_action('cluevo_user_points_awarded_from_module', [
            "user_id" => $userId,
            "module_id" => $module->module_id,
            "attempt_id" => $attempt,
            "points_added" => $pointsToAdd,
            "is_practice" => $practiceMode,
            "source-type" => $sourceType
          ]);
        }
      }
    }

    public static function activate()
    {
      if (!self::dependency_check()) {
        wp_die(
          __('You must have the core CLUEVO LMS plugin installed and activated to use this plugin.', 'cluevo'),
          __('Error', 'cluevo'),
          ['back_link' => true]
        );
      } else {
        global $wpdb;
        $table = $wpdb->prefix . CLUEVO_DB_TABLE_MODULE_TYPES;
        $sql = "INSERT IGNORE INTO $table SET type_id = %d, type_name = %s, type_description = %s";
        $wpdb->query(
          $wpdb->prepare($sql, [5, "Google Docs", ""])
        );
      }
    }

    public static function handle_misc_module_url_input($args)
    {
      $input = stripslashes($args["input"]);
      if (filter_var($input, FILTER_VALIDATE_URL)) {
        if (preg_match('/^http[s]?:\/\/docs.google.com\//', $input) === 1) {
          if (preg_match('/^http[s]?:\/\/docs.google.com\/presentation\//', $input) === 1) {
            $input = preg_replace('/\/pub([?]?.*)$/', '/embed$1', $input);
            $input = '<iframe src="' . esc_url($input) . '" frameborder="0" allowfullscreen="true" mozallowfullscreen="true" webkitallowfullscreen="true"/>';
          } else if (preg_match('/^http[s]?:\/\/docs.google.com\/document\//', $input) === 1) {
            $input = preg_replace('/\/pub(?:\?)(.*)$/', '/pub?embedded=true&$1', $input);
            $input = '<iframe src="' . esc_url($input) . '" frameborder="0" allowfullscreen="true" mozallowfullscreen="true" webkitallowfullscreen="true"/>';
          } else if (preg_match('/^http[s]?:\/\/docs.google.com\/spreadsheets\//', $input) === 1) {
            $input = preg_replace('/\/pub(?:html)?(?:\?)?(?:output=[\w]+)?(.*)$/', '/pubhtml?gid=0&single=true&widget=true&headers=false$1', $input);
            $input = '<iframe src="' . esc_url($input) . '" frameborder="0" allowfullscreen="true" mozallowfullscreen="true" webkitallowfullscreen="true"/>';
          }
        }
      }
      $doc = new DOMDocument();
      @$doc->loadHTML($input);
      $xpath = new DOMXpath($doc);
      $result = $xpath->query('//iframe[starts-with(@src, "https://docs.google.com")]|//img[starts-with(@src, "https://docs.google.com")]');

      if (count($result) === 1) {
        $node = $result->item(0);
        $elName = $node->nodeName;
        $attrs = [];
        foreach ($node->attributes as $attr) {
          $attrs[$attr->name] = $attr->value;
        }

        $index = json_encode(["element" => $elName, "attrs" => $attrs]);
        $title = sanitize_text_field($_POST["cluevo-gdocs-module-name"]);
        $id = null;
        if (self::module_exists($index)) {
          $module = self::get_module_from_index($index);
          $args["messages"][] = __("Module updated.", 'cluevo-ext-gdocs');
          cluevo_update_module($module->module_id, ["module_name" => $title]);
        } else {
          $id = cluevo_create_module_metadata_post($title);
          cluevo_create_module($title, 5, $id, "", "", $index, "", null);
          $args["messages"][] = __("Module created.", 'cluevo-ext-gdocs');
        }
        $args["handled"] = true;
      }
    }

    public static function module_exists($strIndex)
    {
      if (defined(CLUEVO_DB_TABLE_MODULES)) {
        global $wpdb;
        $table = $wpdb->prefix . CLUEVO_DB_TABLE_MODULES;
        $sql = "SELECT * FROM $table WHERE module_index = %s";
        $result = $wpdb->get_results(
          $wpdb->prepare($sql, [$strIndex])
        );

        return !empty($result);
      }
    }

    public static function get_module_from_index($strIndex)
    {
      if (!is_plugin_active('cluevo-lms/cluevo-lms.php')) {
        global $wpdb;
        $table = $wpdb->prefix . CLUEVO_DB_TABLE_MODULES;
        $sql = "SELECT * FROM $table WHERE module_index = %s";
        $result = $wpdb->get_row(
          $wpdb->prepare($sql, [$strIndex]),
          OBJECT
        );

        $module = CluevoItem::from_std_class($result);
        return $module;
      }
    }

    public static function load_plugin_textdomain()
    {
      if (self::dependency_check()) {
        $moFile = WP_LANG_DIR . '/' . dirname(plugin_basename(__FILE__)) . '/' . get_locale() . '.mo';
        load_textdomain('cluevo-ext-gdocs', $moFile);
        if (!load_plugin_textdomain('cluevo-ext-gdocs', false, dirname(plugin_basename(__FILE__)) . '/lang/')) {
          $locale = get_locale();
          if (strtolower(substr($locale, 0, 2)) == 'de') {
            $moFile = plugin_dir_path(__FILE__) . '/lang/de.mo';
          } else {
            $moFile = plugin_dir_path(__FILE__) . '/lang/en.mo';
          }
          $dir = plugin_dir_path(__FILE__);
          load_textdomain('cluevo', $moFile);
        }
      }
    }

    public static function display_module($args)
    {
      if (self::dependency_check()) {
        $module = $args["module"];
        if ($module->type_name == "google docs") {
          if (!empty($module->module_index)) {
            $data = json_decode($module->module_index, true);
            $userId = get_current_user_id();
            if ($userId) {
              self::save_module_progress(["user_id" => $userId, "module" => $module]);
            }
            echo self::build_embed_element($module);
          }
        }
      }
    }

    public static function build_embed_element($module)
    {
      $data = json_decode($module->module_index, true);
      $el = "<" . esc_attr($data["element"]) . ' class="cluevo-gdocs-element cluevo-gdocs-element-' . esc_attr($data["element"]) . '" data-module-id="' . esc_attr($module->module_id) . '"';
      foreach ($data["attrs"] as $attr => $value) {
        $value = esc_attr($value);
        $attr = esc_attr($attr);
        $el .= " $attr=\"$value\" ";
      }
      $el .= "></" . esc_attr($data["element"]) . ">";
      return $el;
    }

    public static function get_gdocs_data($args)
    {
      if (self::dependency_check()) {
        $module = cluevo_get_module((int)$args["id"]);
        if ($module) {
          if ($module->type_name == "google docs") {
            if (!empty($module->module_index)) {
              $data = json_decode($module->module_index, true);
              $module->href = $data["attrs"]["src"];
              $module->html = self::build_embed_element($module);
            }
          }
          return $module;
        } else {
          return false;
        }
      }
    }

    public static function dependency_check()
    {
      return (defined('CLUEVO_ACTIVE') && CLUEVO_ACTIVE === true);
    }

    public static function display_dependency_notice_in_row($file, $data, $status)
    {
      if (!self::dependency_check()) {
        $curVersion = $data["Version"];
        $statusClass = "active";
        if ($data["new_version"] && version_compare($curVersion, $data["new_version"]) != 0) {
          $statusClass .= " update";
        }
        $out = '<tr class="plugin-update-tr ' . $statusClass . '"><td colspan="3" class="plugin-update colspanchange"><div class="notice inline notice-error notice-alt">';
        $out .=  "<p class=\"cluevo-update-compat-text\"><span class=\"dashicons dashicons-warning\"></span> " . esc_html__("The core CLUEVO LMS plugin must be active for this extension to work.", "cluevo") . "</p>";
        $out .= '</div></td></tr>';
        echo $out;
      }
    }
  }

  CluevoExt_GDocsModules::adminInit();
  CluevoExt_GDocsModules::frontendInit();
  register_activation_hook(__FILE__, array('CluevoExt_GDocsModules', 'activate'));

  add_action('after_plugin_row_cluevo-lms-extension-gdocs-modules/cluevo-lms-extension-gdocs-modules.php', 'CluevoExt_GDocsModules::display_dependency_notice_in_row', 10, 3);

  add_action('rest_api_init', function () {

    register_rest_route(CLUEVO_PLUGIN_NAMESPACE . '/v1', '/extensions/gdocs/modules/(?P<id>[\d]+)', array(
      'methods' => 'GET',
      'callback' => 'CluevoExt_GDocsModules::get_gdocs_data',
      'permission_callback' => function () {
        return true;
      }
    ));
  });
}
