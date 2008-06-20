<?php

		/**
		 * Elgg plugins library
		 * Contains functions for managing plugins
		 * 
		 * @package Elgg
		 * @subpackage Core
		 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
		 * @author Curverider Ltd
		 * @copyright Curverider Ltd 2008
		 * @link http://elgg.org/
		 */


		/**
		 * PluginException
		 *  
		 * A plugin Exception, thrown when an Exception occurs relating to the plugin mechanism. Subclass for specific plugin Exceptions.
		 * 
		 * @package Elgg
		 * @subpackage Exceptions
		 */
		class PluginException extends Exception {}
		
		/**
		 * @class ElggPlugin Object representing a plugin's settings for a given site.
		 * This class is currently a stub, allowing a plugin to saving settings in an object's metadata for each site.
		 * @author Marcus Povey
		 */
		class ElggPlugin extends ElggObject
		{
			protected function initialise_attributes()
			{
				parent::initialise_attributes();
				
				$this->attributes['subtype'] = "plugin";
			}
			
			public function __construct($guid = null) 
			{			
				parent::__construct($guid);
			}
		}

		/**
		 * For now, loads plugins directly
		 *
		 * @todo Add proper plugin handler that launches plugins in an admin-defined order and activates them on admin request
		 * @package Elgg
		 * @subpackage Core
		 */
		function load_plugins() {

			global $CONFIG;
			if (!empty($CONFIG->pluginspath)) {
				
				if ($handle = opendir($CONFIG->pluginspath)) {
					while ($mod = readdir($handle)) {
						if (!in_array($mod,array('.','..','.svn','CVS')) && is_dir($CONFIG->pluginspath . "/" . $mod)) {
							if (!@include($CONFIG->pluginspath . $mod . "/start.php"))
								throw new PluginException(sprintf(elgg_echo('PluginException:MisconfiguredPlugin'), $mod));
							if (is_dir($CONFIG->pluginspath . $mod . "/views/default")) {
								autoregister_views("",$CONFIG->pluginspath . $mod . "/views/default",$CONFIG->pluginspath . $mod . "/views/");
							}
							if (is_dir($CONFIG->pluginspath . $mod . "/languages")) {
								register_translations($CONFIG->pluginspath . $mod . "/languages/");
							}
						}
					}
				}
				
			}
			
		}
		
		/**
		 * Get the name of the most recent plugin to be called in the call stack (or the plugin that owns the current page, if any).
		 * 
		 * i.e., if the last plugin was in /mod/foobar/, get_plugin_name would return foo_bar.
		 *
		 * @param boolean $mainfilename If set to true, this will instead determine the context from the main script filename called by the browser. Default = false. 
		 * @return string|false Plugin name, or false if no plugin name was called
		 */
		function get_plugin_name($mainfilename = false) {
			if (!$mainfilename) {
				if ($backtrace = debug_backtrace()) { 
					foreach($backtrace as $step) {
						$file = $step['file'];
						$file = str_replace("\\","/",$file);
						$file = str_replace("//","/",$file);
						if (preg_match("/mod\/([a-zA-Z0-9\-\_]*)\/start\.php$/",$file,$matches)) {
							return $matches[1];
						}
					}
				}
			} else {
				$file = $_SERVER["SCRIPT_NAME"];
				$file = str_replace("\\","/",$file);
				$file = str_replace("//","/",$file);
				if (preg_match("/mod\/([a-zA-Z0-9\-\_]*)\//",$file,$matches)) {
					return $matches[1];
				}
			}
			return false;
		}
		
		/**
		 * Register a plugin with a manifest.
		 *
		 * It is passed an associated array of values. Currently the following fields are recognised:
		 * 
		 * 'author', 'description', 'version', 'website' & 'copyright'.
		 * 
		 * @param array $manifest An associative array representing the manifest.
		 */
		function register_plugin_manifest(array $manifest)
		{
			global $CONFIG;
			
			if (!is_array($CONFIG->plugin_manifests))
				$CONFIG->plugin_manifests = array();
				
			$plugin_name = get_plugin_name();
			
			if ($plugin_name)
			{
				$CONFIG->plugin_manifests[$plugin_name] = $manifest;
			}
			else
				throw new PluginException(elgg_echo('PluginException:NoPluginName'));
		}
		
		/**
		 * Register a basic plugin manifest.
		 *
		 * @param string $author The author.
		 * @param string $description A description of the plugin (don't forget to internationalise this string!)
		 * @param string $version The version
		 * @param string $website A link to the plugin's website
		 * @param string $copyright Copyright information
		 * @return bool
		 */
		function register_plugin_manifest_basic($author, $description, $version, $website = "", $copyright = "")
		{
			return register_plugin_manifest(array(
				'version' => $version,
				'author' => $author,
				'description' => $description,
				'website' => $website,
				'copyright' => $copyright
			));
		}
		
		/**
		 * Shorthand function for finding the plugin settings.
		 */
		function find_plugin_settings()
		{
			$plugins = get_entities('object', 'plugin');
			$plugin_name = get_plugin_name();
			
			if ($plugins)
			{
				foreach ($plugins as $plugins)
					if (strcmp($plugin->title, $plugin_name)==0)
						return $plugin;
			}
			
			return false;
		}
			
		/**
		 * Set a setting for a plugin.
		 *
		 * @param string $name The name - note, can't be "title".
		 * @param mixed $value The value.
		 */
		function set_plugin_setting($name, $value)
		{
			$plugin = find_plugin_settings();
			
			if (!$plugin)
				$plugin = new ElggPlugin();
				
			if ($name!='title') 
			{
				$plugin->$name = $value;
				
				$plugin->save();
			}
			
			return false;
		}
		
		/**
		 * Get setting for a plugin.
		 *
		 * @param string $name The name.
		 */
		function get_plugin_setting($name)
		{
			$plugin = find_plugin_settings();
			
			if ($plugin)
				return $plugin->$name;
			
			return false;
		}
		
		/**
		 * Clear a plugin setting.
		 *
		 * @param string $name The name.
		 */
		function clear_plugin_setting($name)
		{
			$plugin = find_plugin_settings();
			
			if ($plugin)
				return $plugin->clearMetaData($name);
			
			return false;
		}
		
		/**
		 * Run once and only once.
		 */
		function plugin_run_once()
		{
			// Register a class
			add_subtype("object", "plugin", "ElggPlugin");	
		}
		
		/** 
		 * Initialise the file modules. 
		 * Listens to system boot and registers any appropriate file types and classes 
		 */
		function plugin_init()
		{
			// Now run this stuff, but only once
			run_function_once("plugin_run_once");
		}
		
		// Register a startup event
		register_elgg_event_handler('init','system','plugin_init');	
?>