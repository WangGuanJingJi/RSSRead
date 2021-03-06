<?php
/**
 * @todo Move to admin/settings.php
 * @author Ryan McCue <cubegames@gmail.com>
 * @package Lilina
 * @version 1.0
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

/** */
require_once('admin.php');
require_once(LILINA_PATH . '/admin/includes/settings.php');
do_action('register_options');
$available = available_templates();

if (isset($_POST['activate_template'])) {
	$new = $_POST['activate_template'];
	if (isset($available[$new])) {
		update_option('template', $new);

		header('HTTP/1.1 302 Found', true, 302);
		header('Location: ' . get_option('baseurl') . 'admin/settings.php?template_changed=1');
		die();
	}
}

if(!empty($_POST['action']) && $_POST['action'] == 'settings' && !empty($_POST['_nonce'])) {
	if(!check_nonce('settings', $_POST['_nonce']))
		Lilina::nice_die('Nonces do not match.');

	$updatable_options = AdminOptions::instance()->whitelisted;
	foreach($updatable_options as $option) {
		if(!empty($_POST[$option])) {
			$value = apply_filters('options-sanitize-' . $option, $_POST[$option]);
			update_option($option, $value);
		}
	}
	do_action('settings_after_update');

	header('HTTP/1.1 302 Found', true, 302);
	header('Location: ' . get_option('baseurl') . 'admin/settings.php?updated=1');
	die();
}

require_once(LILINA_INCPATH . '/core/file-functions.php');
admin_header(_r('普通设置'));

if(!empty($_GET['updated']))
	echo '<div class="message"><p>' . _r('已更新设置') . '</p></div>';

if(!empty($_GET['activated']))
	echo '<div class="message"><p>' . _r('插件 <strong>已激活</strong>') . '</p></div>';

if(!empty($_GET['deactivated']))
	echo '<div class="message"><p>' . _r('插件 <strong>已经停用</strong>') . '</p></div>';

if(!empty($_GET['template_changed']))
	echo '<div class="message"><p>' . _r('模板已设置') . '</p></div>';
?>

<h1><?php _e('普通设置'); ?></h1>
<form action="settings.php" method="post">
	<fieldset id="general">
		<legend><?php _e('普通设置'); ?></legend>
		<div class="row">
			<label for="sitename"><?php _e('网站名称'); ?>:</label>
			<input type="text" name="sitename" id="sitename" value="<?php echo get_option('sitename'); ?>" />
		</div>
		<div class="row">
			<label for="baseurl"><?php _e('网站地址(URL)'); ?>:</label>
			<input type="text" name="baseurl" id="baseurl" value="<?php echo get_option('baseurl'); ?>" disabled="disabled" />
			<p class="sidenote"><?php _e('此选项须手动修改 content/system/config/settings.php manually配置.'); ?></p>
		</div>
	</fieldset>
	<fieldset id="views">
		<legend><?php _e('浏览设置'); ?></legend>
		<div class="row">
			<label for="locale"><?php _e('语言(只有一种语言[doge])') ?>:</label>
			<select id="locale" name="locale">
				<?php
				foreach(available_locales() as $locale) {
					echo '<option';
					if($locale['realname'] === get_option('locale')) {
						echo ' selected="selected"';
					}
					echo ' value="' . $locale['realname'] . '">', $locale['name'], '</option>';
				}
				?>
			</select>
		</div>
		<div class="row">
			<label for="timezone"><?php _e('时区'); ?>:</label>
			<select id="timezone" name="timezone">
				<?php
					$ids = DateTimeZone::listIdentifiers();
					$real = array();
					foreach ($ids as $id) {
						if (strpos($id, '/') !== false) {
							list($continent, $city) = explode('/', $id);
						}
						else {
							$continent = 'Other';
							$city = $id;
						}
						if (empty($real[$continent])) {
							$real[$continent] = array();
						}
						$real[$continent][$city] = $id;
					}

					foreach ($real as $continent => $cities) {
						echo '<optgroup label="' . $continent . '">';
						foreach($cities as $city => $tz) {
							echo '<option';
							if($tz === get_option('timezone')) {
								echo ' selected="selected"';
							}
							echo ' value="' . $tz . '">' . $city . '</option>';
						}
						echo '</optgroup>';
					}
				?>
			</select>
		</div>
	</fieldset>
	<fieldset id="update">
		<h2><?php _e('更新') ?></h2>
		<p><?php printf(_r('更新链接是 <code>%s</code>'), get_option('baseurl') . '?method=update') ?></p>
		<p><?php printf(_r('For information on using cron with this URL, see the <a href="%s">documentation</a>.'), 'http://codex.getlilina.org/wiki/Updating_Feeds') ?></p>
	</fieldset>
	<?php do_action('options-form'); ?>
	<?php AdminOptions::instance()->do_sections(); ?>
	<input type="hidden" name="action" value="settings" />
	<input type="hidden" name="_nonce" value="<?php echo generate_nonce('settings') ?>" />
	<p class="buttons"><button type="submit" class="positive"><?php _e('Save') ?></button></p>
</form>
<form action="settings.php" method="post">
	<fieldset id="template">
		<legend><?php _e('主题'); ?></legend>
		<div id="template-list">
<?php
			foreach ($available as $key => $template) {
				$class = 'template';
				if ($template->slug === get_option('template')) {
					$class .= ' current';
				}
?>
				<div class="<?php echo $class ?>">
<?php
					if (file_exists($template->directory . '/screenshot.png')) {
?>
						<img src="<?php echo $template->directory ?>/screenshot.png" width="180" />
<?php
					}

					if (!empty($template->author_uri)) {
						$author = '<a href="' . $template->author_uri . '">' . $template->author . '</a>';
					}
					else {
						$author = $template->author;
					}

					$title = sprintf(_r('%1$s %2$s by %3$s'), $template->name, $template->version, $author);
					if ($template->slug === get_option('template')) {
						$title .= ' (当前主题)';
					}
?>
					<h2><?php echo $title ?></h2>
					<p><?php echo $template->description ?></p>
<?php
					if ($template->slug !== get_option('template')) {
?>
					<button type="submit" name="activate_template" value="<?php echo $template->slug ?>">激活</button>
<?php
					}
?>
				</div>
<?php
			}
?>
		</div>
	</fieldset>
</form>
<?php
admin_footer();
?>