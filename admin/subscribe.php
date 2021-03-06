<?php
/**
 * @todo Move to admin/login.php
 * @author Ryan McCue <cubegames@gmail.com>
 * @package Lilina
 * @version 1.0
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

require_once('admin.php');

class SubscribePage {

	public static function run() {
		if(empty($_POST['url']))
			return self::sub_page();

		if(empty($_POST['nonce']) || !check_nonce('subscribe', $_POST['nonce']))
			return self::choose_page($_POST['url']);

		try {
			$feed = $_POST['url'];
			$discovered = self::get_discovered($feed);
			if (count((array)$discovered) > 1) {
				return self::choose_page($feed, $discovered);
			}

			if (!empty($discovered))
				$feed = $discovered[0];
			$result = Feeds::get_instance()->add( $_POST['url'], $_POST['name'] );
		}
		catch( Exception $e ) {
			return self::sub_page($e->getMessage());
		}
		return self::success_page($result);
	}

	public static function success_page($result) {
		self::head();
?>
	<div id="message">
		<h1><?php _e('Success!'); ?></h1>
		<p class="sidenote"><?php _e('即将关闭当前窗口') ?></p>
		<p class="sidenote" id="counter">3</p>
	</div>
	<script>
		$(document).ready(function () {
			setInterval(countdown, 1000);
		});

		function countdown() {
			if(timer > 0) {
				$('#counter').text(timer);
				timer--;
			}
			else {
				if ($('body').hasClass('framed') && typeof window.parent.jQuery != 'undefined') {
					window.parent.jQuery(window.parent.document).trigger('close-frame');
					return;
				}
				self.close();
			}
		}

		var timer = 2;
	</script>
<?php
		self::foot();
	}

	public static function sub_page($error = '') {
		if(!empty($_GET['url']))
			$url = htmlspecialchars($_GET['url']);
		else
			$url = '';
		self::head();
?>
	<div id="main">
		<h1><?php _e('添加订阅'); ?></h1>
		<p id="backlink"><a href="<?php echo get_option('baseurl'); ?>"><?php echo sprintf(_r('返回到  %s.'), get_option('sitename')); ?></a></p>
<?php
		if(!empty($error)) {
?>
		<div id="error">
			<p><?php echo sprintf(_r('噢！, 处理链接时发生错误了. %s'), $error); ?></p>
		</div>
<?php
		}
?>
		<form action="subscribe.php" method="post" id="add_form"<?php if(!empty($error)) echo ' class="errorform"' ?>>
			<fieldset id="add">
				<p>
					<label for="url"><?php _e('订阅地址'); ?>:</label>
					<input type="text" name="url" id="url" value="<?php if(!empty($url)) echo $url; ?>" class="input input_small" />
				</p>
				<p class="sidenote"><?php _e('如：'); ?>: https://yefengs.com/feed, https://leyaep.com/feed<br/><strong>注意：如果订阅失败请尝试域名后面加/feed/(Wordpress)或者页面里寻找RSS链接添加(大部分博客都没问题，除非博主关闭了feed功能)</strong></p>
			</fieldset>
			<fieldset id="optional" class="optional">
				<p>
					<label for="name"><?php _e('名称'); ?>:</label>
					<input type="text" name="name" id="name" class="input input_small" />
				</p>
				<p class="sidenote"><?php _e('如果没有填名称，那么从网站的内容中获取名称'); ?></p>
			
			</fieldset>

			<input type="hidden" name="action" value="add" />
			<input type="hidden" name="nonce" value="<?php echo generate_nonce('subscribe') ?>" />
<?php
		if (isset($_REQUEST['framed'])) {
?>
			<input type="hidden" name="framed" value="true" />
<?php
		}
?>
			<input type="submit" value="<?php _e('添加订阅'); ?>" class="submit" />
		</form>
	</div>
<?php
		self::foot();
	} // function sub_main()

	public static function choose_page($url, $discovered = false) {
		self::head();
		if (!$discovered)
			$discovered = self::get_discovered($url);
?>
	<div id="main">
		<h1><?php _e('选择一个订阅') ?></h1>
		<p>一般，一个网站有多个订阅，来选一个。</p>
		<form action="subscribe.php" method="post" id="add_form"<?php if(!empty($error)) echo ' class="errorform"' ?>>
			<fieldset id="select">
				<ul>
<?php
		foreach ($discovered as $feed) {
			echo '<li><label><input type="radio" name="url" value="' . $feed['file']->url . '" /> ' . $feed['title'] . '</label></li>';
		}
?>
				</ul>
			</fieldset>
			<input type="hidden" name="action" value="add" />
			<input type="hidden" name="name" value="<?php echo htmlspecialchars($_POST['name']) // Possibly unsafe ?>" />
			<input type="hidden" name="nonce" value="<?php echo generate_nonce('subscribe') ?>" />
<?php
		if (isset($_REQUEST['framed'])) {
?>
			<input type="hidden" name="framed" value="true" />
<?php
		}
?>
			<input type="submit" value="<?php _e('添加订阅'); ?>" class="submit" />
		</form>
	</div>
<?php
		self::foot();
	}

	public static function get_discovered($url) {
		require_once(LILINA_INCPATH . '/contrib/simplepie.class.php');
		class_exists('Lilina_HTTP');
		$feed = new SimplePie();
		$feed->set_useragent(LILINA_USERAGENT . ' SimplePie/' . SIMPLEPIE_BUILD);
		$feed->set_stupidly_fast(true);
		$feed->enable_cache(false);
		$feed->set_feed_url($url);
		$feed->set_file_class('Lilina_SimplePie_File');
		$feed->set_locator_class('Lilina_SimplePie_Locator');
		// We set this higher, since we're explicitly autodiscovering
		$feed->set_max_checked_feeds(50);
		$feed->init();
		return $feed->get_all_discovered_feeds();
	}

	public static function head() {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php _e('Subscribe') ?> &mdash; <?php echo get_option('sitename'); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link rel="stylesheet" type="text/css" href="<?php echo get_option('baseurl'); ?>admin/resources/core.css" media="screen"/>
<link rel="stylesheet" type="text/css" href="<?php echo get_option('baseurl'); ?>admin/resources/mini.css" media="screen"/>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<script type="text/javascript" src="<?php echo get_option('baseurl'); ?>inc/js/jquery.js"></script>
</head>
<body id="admin-subscribe" class="admin-page<?php if (isset($_REQUEST['framed'])) echo ' framed'; ?>">
<?php
	} // function head()

	public static function foot() {
?>
	<script type="text/javascript">
		$(document).ready(function () {
			$('.framed #backlink').click(function () {
				if (typeof window.parent.jQuery != 'undefined') {
					window.parent.jQuery(window.parent.document).trigger('close-frame');
					return false;
				}
			});
			$(".optional").hide();
			$("<p class='hideshow'><span><?php _e('高级选项') ?></span></p>").insertBefore(".optional").click(function () {
				$(this).siblings(".optional").show();
				$(this).hide();
			});

			$("#url").focus();
		});
	</script>
</body>
</html>
<?php
	}
} // class SubscribePage

SubscribePage::run();