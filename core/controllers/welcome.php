<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2008 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class Welcome_Controller extends Template_Controller {
  public $template = "welcome.html";

  function index() {
    $this->template->syscheck = new View("welcome_syscheck.html");
    $this->template->syscheck->errors = $this->_get_config_errors();

    try {
      $this->template->syscheck->modules = $this->_readModules();
    } catch (Exception $e) {
      $this->template->syscheck->modules = array();
    }
      $this->_create_directories();
  }

  /**
   * Create an array of all the modules that are install or available and the version number
   * @return array(moduleId => version)
   */
  private function _readModules() {
    $modules = array();
    try {
      $installed = ORM::factory("module")->find_all();
      foreach ($installed as $installedModule) {
        $modules[$installedModule->name] = $installedModule->version;
      }
    } catch (Exception $e) {}

    if (!empty($modules['core'])) {
      if ($dh = opendir(MODPATH)) {
        while (($file = readdir($dh)) !== false) {
         if ($file[0] != '.' && 
             file_exists(MODPATH . "$file/helpers/{$file}_installer.php")) {
            if (empty($modules[$file])) {
              $modules[$file] = 0;
            }
          }
        }
      }
      closedir($dh);
    }

    return $modules;
  }
  
  private function _find_available_modules() {
    $installed = ORM::factory("module")->find_all();
    $moduleList = array();
    foreach ($installed as $installedModule) {
      $moduleList[$installedModule->name] = 1;
    }
    
    var_dump($moduleList);
    $modules = array();
    $paths = Kohana::config('core.modules');
    foreach ($paths as $path) {
      $module = substr($path, strrpos($path, "/") + 1);
      var_dump($module, "$path/helpers/{$module}_installer.php");
      if (file_exists("$path/helpers/{$module}_installer.php")) {
        $modules[$module] = !empty($moduleList[$module]) ? "install" : "unintall";
      }
//      $installer_directory = "$module_path/helpers";
//      if (is_dir($controller_directory)) {
//        if ($dh = opendir($controller_directory)) {
//          while (($controller = readdir($dh)) !== false) {
//            if ($controller[0] == ".") {
//              continue;
//            }
//            $matches = array();
//            if (preg_match("#^admin_([a-zA-Z][a-z_A-Z0-9]*)\.php$#", $controller, $matches)) {
//              $descriptor = $this->_get_descriptor("Admin_" . ucfirst($matches[1]) . '_Controller');
//              if (!empty($descriptor)) {
//                $admin_controllers["admin/$matches[1]"] = $descriptor;
//              }
//            }
//          }
//          closedir($dh);
//         }
//       }
    }

    return $modules;
  }
  
  function install($module) {
    call_user_func(array("{$module}_installer", "install"));
    url::redirect("welcome");
  }

  function uninstall($module) {
    call_user_func(array("{$module}_installer", "uninstall"));
    url::redirect("welcome");
  }

  function add($count) {
    srand(time());
    $parents = ORM::factory("item")->where("type", "album")->find_all()->as_array();
    for ($i = 0; $i < $count; $i++) {
      $parent = $parents[array_rand($parents)];
      switch(rand(0, 1)) {
      case 0:
        $album = album::create($parent->id, "rnd_" . rand(), "Rnd $i", "rnd $i");
        $parents[] = $album;
        break;

      case 1:
        photo::create($parent->id, DOCROOT . "themes/default/images/thumbnail.jpg",
                      "thumbnail.jpg", "rnd_" . rand(), "sample thumbnail");
        break;
      }

      print "$i ";
      if (!($i % 100)) {
        set_time_limit(30);
      }
    }
    print "<br/>";
    print html::anchor("welcome", "return");
    $this->auto_render = false;
  }

  private function _get_config_errors() {
    $errors = array();
    if (!file_exists(VARPATH)) {
      $error = new stdClass();
      $error->message = "Missing: " . VARPATH;
      $error->instructions[] = "mkdir " . VARPATH;
      $error->instructions[] = "chmod 777 " . VARPATH;
      $errors[] = $error;
    } else if (!is_writable(VARPATH)) {
      $error = new stdClass();
      $error->message = "Not writable: " . VARPATH;
      $error->instructions[] = "chmod 777 " . VARPATH;
      $errors[] = $error;
    }

    $db_php = VARPATH . "database.php";
    if (!file_exists($db_php)) {
      $error = new stdClass();
      $error->message = "Missing: $db_php";
      $error->instructions[] = "cp kohana/config/database.php $db_php";
      $error->instructions[] = "chmod 644 $db_php";
      $error->message2 = "Then edit this file and enter your database configuration settings.";
      $errors[] = $error;
    } else if (!is_readable($db_php)) {
      $error = new stdClass();
      $error->message = "Not readable: $db_php";
      $error->instructions[] = "chmod 644 $db_php";
      $error->message2 = "Then edit this file and enter your database configuration settings.";
      $errors[] = $error;
    } else {
      $old_handler = set_error_handler(array("Welcome_Controller", "_error_handler"));
      try {
        Database::instance()->connect();
      } catch (Exception $e) {
        $error = new stdClass();
        $error->message = "Database error: {$e->getMessage()}";
        $db_name = Kohana::config("database.default.connection.database");
        if (strchr($error->message, "Unknown database")) {
          $error->instructions[] = "mysqladmin -uroot create $db_name";
        } else {
          $error->instructions = array();
          $error->message2 = "Check " . VARPATH . "database.php";
        }
        $errors[] = $error;
      }
      set_error_handler($old_handler);
    }

    return $errors;
  }

  function _error_handler($x) {
  }

  function _create_directories() {
    foreach (array("logs") as $dir) {
      @mkdir(VARPATH . "$dir");
    }
  }
}
