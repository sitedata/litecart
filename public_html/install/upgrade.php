<?php
  // Automatic upgrade: upgrade.php?from_version={version}&upgrade=true&redirect={url}

  ob_start();

  require_once('../includes/config.inc.php');
  if (!defined('FS_DIR_APP')) define('FS_DIR_APP', FS_DIR_HTTP_ROOT . WS_DIR_HTTP_HOME);
  if (!defined('FS_DIR_ADMIN')) define('FS_DIR_ADMIN', FS_DIR_HTTP_ROOT . WS_DIR_ADMIN);

  require_once('../includes/error_handler.inc.php');
  require_once('../includes/library/lib_database.inc.php');
  require_once('includes/header.inc.php');
  require_once('includes/functions.inc.php');

// Turn on errors
  error_reporting(version_compare(PHP_VERSION, '5.4.0', '<') ? E_ALL | E_STRICT : E_ALL);
  ini_set('ignore_repeated_errors', 'On');
  ini_set('log_errors', 'Off');
  ini_set('display_errors', 'On');
  ini_set('html_errors', 'On');

// Set platform name
  preg_match('#define\(\'PLATFORM_NAME\', \'([^\']+)\'\);#', file_get_contents('../includes/app_header.inc.php'), $matches);
  define('PLATFORM_NAME', isset($matches[1]) ? $matches[1] : false);

// Set platform version
  preg_match('#define\(\'PLATFORM_VERSION\', \'([^\']+)\'\);#', file_get_contents('../includes/app_header.inc.php'), $matches);
  define('PLATFORM_VERSION', isset($matches[1]) ? $matches[1] : false);

  if (!PLATFORM_VERSION) die('Could not identify target version.');

// Get current platform database version
  $platform_database_version_query = database::query(
    "select `value` from ". DB_TABLE_SETTINGS ."
    where `key` = 'platform_database_version'
    limit 1;"
  );
  $platform_database_version = database::fetch($platform_database_version_query);

  if (!empty($platform_database_version)) {
    define('PLATFORM_DATABASE_VERSION', $platform_database_version['value']);
    if (empty($_REQUEST['from_version'])) $_REQUEST['from_version'] = PLATFORM_DATABASE_VERSION;
  }

// List supported upgrades
  $supported_versions = array('1.0' => '1.0');
  foreach (glob('upgrade_patches/*') as $file) {
    preg_match('#\/(.*).(inc.php|sql)$#', $file, $matches);
    $supported_versions[$matches[1]] = $matches[1];
  }
  usort($supported_versions, function($a, $b) {
    return version_compare($a, $b, '>');
  });

  echo '<h1>Upgrade</h1>' . PHP_EOL;

  if (!empty($_REQUEST['upgrade'])) {

    if (empty($_REQUEST['from_version'])) die('You must select the version you are migrating from.');

    #############################################

    foreach ($supported_versions as $version) {
      if (version_compare($_REQUEST['from_version'], $version, '<')) {
        if (file_exists('upgrade_patches/'. $version .'.sql')) {
          echo '<p>Upgrading database to '. $version .'... ' . PHP_EOL;
          $sql = file_get_contents('upgrade_patches/'. $version .'.sql');
          $sql = str_replace('`lc_', '`'.DB_TABLE_PREFIX, $sql);

          $sql = explode('-- --------------------------------------------------------', $sql);

          foreach ($sql as $query) {
            $query = preg_replace('#--.*\s#', '', $query);
            if (!empty($query)) {
              database::query($query);
            }
          }
        }

        if (file_exists('upgrade_patches/'. $version .'.inc.php')) {
          echo '<p>Upgrading system to '. $version .'... ' . PHP_EOL;
          include('upgrade_patches/'. $version .'.inc.php');
        }
      }
    }

    #############################################

    echo '<p>Set platform database version...';

    if (defined('PLATFORM_VERSION')) {

      database::query(
        "update ". str_replace('`lc_', '`'.DB_TABLE_PREFIX, '`lc_settings`') ."
        set `value` = '". database::input(PLATFORM_VERSION) ."'
        where `key` = 'platform_database_version'
        limit 1;"
      );

      echo ' <strong>'. PLATFORM_VERSION .'</strong></p>' . PHP_EOL;

    } else {
      echo ' <span class="error">[Error: Not defined]</span></p>' . PHP_EOL;
    }

    #############################################

    echo '<p>Preparing CSS files...' . PHP_EOL;

    if (!empty($_REQUEST['development_type']) && $_REQUEST['development_type'] == 'advanced') {

      $files_to_delete = array(
        '../includes/templates/default.catalog/css/app.css',
        '../includes/templates/default.catalog/css/checkout.css',
        '../includes/templates/default.catalog/css/framework.css',
        '../includes/templates/default.catalog/css/printable.css',
      );

      foreach ($files_to_delete as $file) {
        echo 'Delete '. $file;
        if (file_delete($file)) {
          echo ' <span class="ok">[OK]</span></p>' . PHP_EOL;
        } else {
          echo '<span class="error">[Error]</span></p>' . PHP_EOL;
        }
      }

    } else {

      $files_to_delete = array(
        '../includes/templates/default.catalog/less/',
        '../includes/templates/default.catalog/css/*.min.css',
        '../includes/templates/default.catalog/css/*.min.css.map',
      );

      foreach ($files_to_delete as $file) {
        echo 'Delete '. $file;
        if (file_delete($file)) {
          echo ' <span class="ok">[OK]</span></p>' . PHP_EOL;
        } else {
          echo '<span class="error">[Error]</span></p>' . PHP_EOL;
        }
      }

      foreach (glob('../includes/templates/default.catalog/layouts/*.inc.php') as $file) {
        echo 'Modify '. $file . PHP_EOL;
        $contents = file_get_contents($file);
        $search_replace = array(
          'app.min.css'  => 'app.css',
          'checkout.min.css'  => 'checkout.css',
          'framework.min.css' => 'framework.css',
          'printable.min.css' => 'printable.css',
        );
        file_put_contents($file, strtr($contents, $search_replace));
      }
    }

    #############################################

    echo '<p>Clear cache... ';

    database::query(
      "update ". DB_TABLE_SETTINGS ."
      set value = '1'
      where `key` = 'cache_clear'
      limit 1;"
    );

    foreach (glob(FS_DIR_APP . 'vqmod/vqcache/*.php') as $file) {
      if (is_file($file)) unlink($file);
    }

    if (is_file($file = FS_DIR_APP . 'vqmod/chekced.cache')) unlink($file);
    if (is_file($file = FS_DIR_APP . 'vqmod/mods.cache')) unlink($file);

    echo '<span class="ok">[OK]</span></p>' . PHP_EOL;

    #############################################

    if (!empty($_REQUEST['redirect'])) {
      header('Location: '. $_REQUEST['redirect']);
      exit;
    }

    echo PHP_EOL . '<h2>Complete</h2>' . PHP_EOL
       . '<p style="font-weight: bold;">Upgrade complete! Please delete the <strong>~/install/</strong> folder.</p>' . PHP_EOL;

    require('includes/footer.inc.php');
    exit;
  }

?>
<style>
input[name="development_type"] {
  display: none;
}
input[name="development_type"] + div {
  display: inline-block;
  padding: 15px;
  margin: 7.5px;
  border: 1px solid rgba(0,0,0,0.1);
  border-radius: 15px;
  width: 250px;
  height: 145px;
  text-align: center;
  cursor: pointer;
}
input[name="development_type"] + div .type {
  font-size: 1.5em;
  line-height: 1.5em;
}
input[name="development_type"] + div .title {
  font-size: 1.25em;
  font-weight: bold;
  line-height: 2em;
}
input[name="development_type"]:checked + div {
  border-color: #333;
}
</style>

<p>Upgrade to <?php echo PLATFORM_NAME; ?> <?php echo PLATFORM_VERSION; ?> from any older version listed.</p>

<p class="alert alert-danger"><strong>Backup your files and database before you continue!</strong> Selecting the wrong version will most likely damage your data.</p>

<form name="upgrade_form" method="post">
  <h3>Installed Version</h3>

  <div class="form-group">
    <label>Select the <?php echo PLATFORM_NAME; ?> version you are upgrading from:</label>
    <select class="form-control" name="from_version">
      <option value="">-- Select Version --</option>
      <?php foreach ($supported_versions as $version) echo '<option value="'. $version .'"'. ((isset($_REQUEST['from_version']) && $_REQUEST['from_version'] == $version) ? 'selected="selected"' : '') .'>LiteCart '. $version . ((defined('PLATFORM_DATABASE_VERSION') && PLATFORM_DATABASE_VERSION == $version) ? ' (Detected)' : '') .'</option>' . PHP_EOL; ?>
    </select>
  </div>

  <h3>Development</h3>

  <div class="form-group" style="display: flex;">

    <label>
      <input name="development_type" value="standard" type="radio" checked="checked" />
      <div>
        <div class="type">Standard</div>
        <div class="title">.css</div>
        <div class="description">Uncompressed CSS files</div>
      </div>
    </label>

    <label>
      <input name="development_type" value="advanced" type="radio" />
      <div>
        <div class="type">Advanced</div>
        <div class="title">.less + .min.css</div>
        <div class="description">
          Compressed CSS files<br />
          (Requires a <a href="https://wiki.litecart.net/doku.php?id=how_to_change_the_look_of_your_store" target="_blank">LESS compiler</a>)
        </div>
      </div>
    </label>
  </div>

  <button class="btn btn-default btn-block" type="submit" name="upgrade" value="true" onclick="if(!confirm('Warning! The procedure cannot be undone.')) return false;" />Upgrade To <?php echo PLATFORM_VERSION; ?></button>
</form>
<?php
  require('includes/footer.inc.php');
