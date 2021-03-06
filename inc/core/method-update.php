<?php
/**
 * OPML feeds export, different to the items export on rss.php
 *
 * @package Lilina
 * @subpackage Methods
 */

class UpdaterMethod {
	protected $feeds = array();
	protected $action = '';
	protected $format = '';
	protected $selector = '';
	/**
	 * Constructor
	 */
	public function __construct() {
		if(!empty($_REQUEST['action']))
			$this->action = $_REQUEST['action'];
		if(!empty($_REQUEST['format']))
			$this->format = $_REQUEST['format'];

		//  Special stuff for cron updating
		if(isset($_GET['cron'])) {
			$this->action = 'cron';
		}

		// We require a format to be set, otherwise we default to HTML. We 
		// don't want this if we're updating via cron, so we have to fake it.
		if($this->action == 'cron')
			$this->format = 'cron';
	}

	/**
	 * Process the request and hand-off to ItemUpdater
	 */
	public function process($feed) {
		switch($feed) {
			case 'all':
				$this->feeds = Feeds::get_instance()->getAll();
				break;
			default:
				if($this->validate($feed))
					$this->feeds[] = Feeds::get_instance()->get($feed);
				else {
					$this->errors[] = sprintf(_r('Invalid feed ID supplied: %s'), $feed);
					return;
				}
				break;
		}
		
		$messages = array();
		ItemUpdater::set_feeds($this->feeds);
		foreach(ItemUpdater::process() as $feed => $updated) {
			$name = Feeds::get_instance()->get($feed);
			$name = $name['name'];
			$text = Localise::ngettext('"%1$s" %2$d 条内容.', '"%1$s" %2$d条内容.', $updated);
			$messages[] = array('msg' => sprintf($text, $name, $updated), 'updated' => $updated);
		}
		return array('success' => 1, 'msgs' => $messages);
	}

	/**
	 * Validate a supplied feed ID
	 *
	 * @param string $id Feed ID (presumably) to validate
	 * @return boolean True if feed exists, false otherwise, but will throw an exception first
	 */
	protected function validate($id) {
		if(!Feeds::get_instance()->get($id)) {
			throw new Exception(_r('Invalid feed ID supplied.'));
			return false;
		}
		return true;
	}

	/**
	 * Run the updater
	 */
	public function init() {
		if(empty($this->format)) {
			$this->page();
			die();
		}

		try {
			header('Content-Type: application/json; charset=utf-8');
			if(empty($this->action)) {
				throw new Exception(_r('No action specified'), Errors::get_code('api.itemupdater.ajax.action_unknown'));
			}

			switch($this->action) {
				case 'test':
					$return = $this->test();
					break;
				case 'cron':
					$return = $this->cron();
					break;
				case 'all':
					$return = $this->process('all');
					break;
				case 'single':
					if(empty($_REQUEST['id']))
						throw new Exception('No ID specified', Errors::get_code('api.itemupdater.ajax.no_id'));
					$return = $this->process($_REQUEST['id']);
					break;
				default:
					throw new Exception('Unknown action: ' . preg_replace('/[^-_.0-9a-zA-Z]/', '', $_REQUEST['action']), Errors::get_code('api.itemupdater.ajax.action_unknown'));
					break;
			}
		} catch (Exception $e) {
			header('HTTP/1.1 500 Internal Server Error');
			echo json_encode( array('error'=>1, 'msg'=>$e->getMessage(), 'code'=>$e->getCode()));
			die();
		}
		
		echo json_encode($return);
		die();
	}
	
	protected function cron() {
		set_time_limit(0);
		
		ItemUpdater::set_feeds( Feeds::get_instance()->getAll() );
		ItemUpdater::$fatal = false;

		foreach(ItemUpdater::process() as $feed => $updated) {
			$name = Feeds::get_instance()->get($feed);
			$name = $name['name'];
			if($updated < 0) {
				$text = 'An error occurred while updating feed "%1$s".';
			}
			else {
				$text = Localise::ngettext('Updated feed "%1$s". Added %2$d item.', 'Updated feed "%1$s". Added %2$d items.', $updated);	
			}
			$messages[] = array('msg' => sprintf($text, $name, $updated), 'updated' => $updated);
		}
		
		if(isset($_GET['output'])){
			return array('success' => 1, 'msgs' => $messages);
		}
		else {
			die();
		}
	}
	
	protected function test() {
		return array('success' => 1, 'msg' => 'Test succeeded');
	}

	/**
	 * Page header
	 */
	protected function page() {
		header('Content-Type: text/html; charset=utf-8');
		$feeds = Feeds::get_instance()->getAll();
		foreach($feeds as &$feed) {
			$feed = $feed['id'];
		}
		$feeds = array_values($feeds);
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
		<title><?php _e('Item Updater') ?> &mdash; <?php echo get_option('sitename') ?></title>
		<link rel="stylesheet" type="text/css" href="<?php echo get_option('baseurl') ?>admin/resources/reset.css" />
		<link rel="stylesheet" type="text/css" href="<?php echo get_option('baseurl') ?>install.css" />
		<link rel="stylesheet" type="text/css" href="<?php echo get_option('baseurl') ?>admin/resources/iu.css" />
		<script type="text/javascript" src="<?php echo get_option('baseurl') ?>inc/js/jquery.js"></script>
		<script type="text/javascript" src="<?php echo get_option('baseurl') ?>inc/js/json2.js"></script>
		<script type="text/javascript" src="<?php echo get_option('baseurl') ?>admin/resources/iu.js"></script>
		<script type="text/javascript">
			ItemUpdater.location = "<?php echo get_option('baseurl') ?>";
			ItemUpdater.feeds = <?php echo json_encode($feeds) ?>;
		</script>
	</head>
	<body class="updater">
		<div id="content">
			<h1 id="title"><?php _e('订阅内容更新') ?></h1>
			<p><?php _e('现在开始更新，这是一个循环更新，请确保 Javascript处于启用状态！') ?></p>
			<p class="js-hide"><?php _e('当前 Javascript处于禁用状态，请启动后再试！') ?></p>
			<ul id="updatelist">
			</ul>
			<p id="loading"><?php _e('更新中...') ?></p>
			<div id="finished">
				<p><?php _e('完成更新.') ?></p>
				<p><?php echo sprintf(_r('<a href="%1$s">返回  %2$s</a>.'), get_option('baseurl'), get_option('sitename')) ?></p>
			</div>
		</div>
		<div id="footer">
			<p>Powered by <a href="http://getlilina.org/">Lilina</a> <span class="version">1.0-bleeding</span>. Read the <a href="http://codex.getlilina.org/">documentation</a> or get help on the <a href="http://getlilina.org/forums/">forums</a></p>
		</div>
	</body>
</html>
<?php
	}
}

global $updatemethod;
$updatemethod = new UpdaterMethod();
Controller::registerMethod('update', array($updatemethod, 'init'));