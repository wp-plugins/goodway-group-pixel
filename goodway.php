<?php
/**
 * Plugin Name: GoodWay Pixels
 * Plugin URI: http://www.goodwaygroup.com/
 * Description: Easily integrate GoodWay's Pixel code in your blog.
 * Version: 2015.03.15-2
 * Author: GoodWay
 * Author URI: http://www.goodwaygroup.com/
 * License: Apache-v2.0
 */

defined('ABSPATH') or die('You have been weighed, you have been measured, and you have been found wanting.');

final class GoodWay {
	private $options = array();
	private $table_name;
	private $db_version = 1.0;
	public function __construct() {
		global $wpdb;
		$this->options = get_option('goodway');
		print_r($this->options);
		$this->table_name = $wpdb->prefix . 'goodway';

		if (is_admin()) {
			add_action('admin_menu', array($this, 'addPluginPage'));
			add_action('admin_init', array($this, 'pageInit'));
		}

		add_action('plugins_loaded', array($this, 'initPlugins'));
	}

	public function installTables() {
		global $wpdb;

		if($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") === $this->table_name) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $this->table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			url VARCHAR(255) NOT NULL,
			pixel TEXT NOT NULL,
			PRIMARY KEY(id),
			KEY url (url)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		add_option('goodway_db_version', $this->db_version);
	}

	public function initPlugins() {
		add_action('wp_head', array($this, 'addPixels'), 0);
	}

	public function addPluginPage() {
		add_options_page('Settings Admin', 'GoodWay Pixels', 'manage_options', 'goodway-settings', array($this, 'createAdminPage'));
	}

	public function createAdminPage() {
?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2>GoodWay Pixels</h2>
			<form method="post" action="options.php" id="goodwayOptions">
<?php
		// This prints out all hidden setting fields
		settings_fields('goodway-settings');
		do_settings_sections('goodway-settings');
		submit_button();
?>
			</form>
		</div>
<?php
	}
	public function pageInit() {
		$this->installTables();
		$header = '<h3>Please follow the steps below to activate your Goodway Pixels.<br>';
		$header .= 'If you need further assistants please call your account representative.</h3>';
		$header .= '<a href="http://www.goodwaygroup.com/"><img src="http://www.goodwaygroup.com/img/logo-gg.png"></a>';
		
		register_setting('goodway-settings', 'goodway', array($this, 'process'));

		add_settings_section('goodway', $header, null, 'goodway-settings');
		add_settings_field('pixels', 'Add Pixels', array($this, 'addPixelsCB'), 'goodway-settings', 'goodway');
		add_settings_field('prevPixels', 'Pixels', array($this, 'olderPixelsCB'), 'goodway-settings', 'goodway');
	}
	public function process($input) {
		if(isset($input['pixels']) && is_array($input['pixels'])) {
			global $wpdb;
			$pixels = $input['pixels']['p'];
			$urls = $input['pixels']['u'];
			$delete = $input['pixels']['d'];
			$wpdb->query('SET autocommit = 0;');
			for($i = 0; $i < count($pixels); $i++) {
				$p = $pixels[$i];
				$u = $urls[$i];
				if(strlen($p) > 0 && strlen($u) > 0) {
					$wpdb->insert($this->table_name, array('url' => $u, 'pixel' => $p), array('%s', '%s'));
				}
			}
			foreach($delete as $id) {
				$wpdb->delete($this->table_name, array('id' => $id ), array('%d'));
			}
			$wpdb->query('COMMIT;');
			$wpdb->query('SET autocommit = 1;');
		}
		return null;
	}

	public function addPixelsCB() {
?>
		<fieldset>
			<legend>Place your pixel below followed by the URL you would like it placed on:</legend>
			<div id="pixelInfoTmpl">
				<textarea name="goodway[pixels][p][]" placeholder="Paste your pixel here..." style="width: 463px;" rows="4"></textarea>
				<p><input name="goodway[pixels][u][]" placeholder="Paste the page URL, for example: https://you.com/awesome-product-page1" style="width: 463px;" value=""></p>
				<hr>
			</div>
			<div><button id="addPixel">Add another</button></div>
		</fieldset>
		<script>
		jQuery(function($) {
			var pixelInfo = $('#pixelInfoTmpl').attr('id', null).clone(),
				add = $('#addPixel');
			add.click(function(e) {
				e.preventDefault();
				pixelInfo.clone().insertBefore(add.parent('div'));
			});
		});
		</script>
<?php
	}

		public function olderPixelsCB() {
			global $wpdb;
?>
		<fieldset>
		<style>
		.pixelInfo.deleted textarea, .pixelInfo.deleted input {
			display: none;
		}
		</style>
<?php
			$res = $wpdb->get_results("select * from `$this->table_name`;");
			foreach($res as $row) {
?>
			<div class="pixelInfo" data-id="<?php echo $row->id?>">
				<textarea readonly="readonly" style="width: 463px;"><?php echo $row->pixel?></textarea>
				<div>
					<input readonly="readonly" style="width: 403px;" value="<?php echo $row->url?>">
					<button>Delete</button>
				</div>
				<hr>
			</div>

<?php
			}
?>
		</fieldset>
		<script>
		jQuery(function($) {
			$('.pixelInfo button').click(function(e){
				e.preventDefault();
				var parent = $(this).parent().parent('.pixelInfo'),
					id = parent.data('id');
				if(id === null || id === undefined) {
					console.log("invalid id", parent);
					return;
				} 
				if(parent.hasClass('deleted')) {
					this.innerText = 'Delete';
					parent.find('input[type=hidden]').remove();
					parent.removeClass('deleted');
				} else {
					this.innerText = 'Undelete';
					parent.addClass('deleted');
					parent.append('<input type="hidden" name="goodway[pixels][d][]" value="' + id + '">');
				}
			});
		});
		</script>
<?php
	}

	public function addPixels() {
		global $wpdb;
		global $post;
		$url = get_permalink($post->ID);
		$q = $wpdb->prepare('SELECT pixel FROM `' . $this->table_name . '` WHERE url = %s;', $url);
		$res = $wpdb->get_results($q);
		foreach($res as $row) {
			echo "$row->pixel\n";
		}
	}
}

$goodway = new GoodWay();
