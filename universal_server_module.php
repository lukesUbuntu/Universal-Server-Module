<?php
/**
 * Universal Module
 *
 * A module that can be customized to request any fields and post them to any
 * URL or email address
 *
 * @package blesta
 * @subpackage blesta.components.modules.universal_server_module
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */

class UniversalServerModule extends Module {
	
	/**
	 * @var string The version of this module
	 */
	private static $version = "1.2.2";
	/**
	 * @var string The authors of this module
	 */
	private static $authors = array(array('name' => "CyanDark, Inc.", 'url' => "http://www.cyandark.com"));
	/**
	 * @var string A set of reserved form fields that will not be wrapped in a meta[] array
	 */
	private static $reserved_fields = array('qty');
	
	/**
	 * Initializes the module
	 */
	public function __construct() {
		// Load components required by this module
		Loader::loadComponents($this, array("Input"));
		
		// Load the language required by this module
		Language::loadLang("universal_server_module", null, dirname(__FILE__) . DS . "language" . DS);
	}
	
	/**
	 * Returns the name of this module
	 *
	 * @return string The common name of this module
	 */
	public function getName() {
		if ($row = $this->getModuleRow())
			return $row->meta->name;
		
		return Language::_("UniversalServerModule.name", true);
	}
	
	/**
	 * Returns the version of this gateway
	 *
	 * @return string The current version of this module
	 */
	public function getVersion() {
		return self::$version;
	}
	
	/**
	 * Returns the name and url of the authors of this module
	 *
	 * @return array The name and url of the authors of this module
	 */
	public function getAuthors() {
		return self::$authors;
	}
	
	/**
	 * Returns the value used to identify a particular service
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @return string A value used to identify this service amongst other similar services
	 */
	public function getServiceName($service) {
		static $rows = array();
		if (!isset($rows[$service->module_row_id]))
			$row[$service->module_row_id] = $this->getModuleRow($service->module_row_id);
			
		// Fetch the first service field
		$key = null;
		if (isset($row[$service->module_row_id]->meta->service_field_name_0))
			$key = $row[$service->module_row_id]->meta->service_field_name_0;
		
		// If the key is set, set the value if it is scalar
		$value = null;
		if ($key != null) {
			$fields = $this->serviceFieldsToObject($service->fields);
			
			if (isset($fields->{$key}) && is_scalar($fields->{$key}))
				$value = $fields->{$key};
		}
		
		// If the value could not be found, set it to the first scalar field instead, if any
		if ($value === null) {
			foreach ($service->fields as $field) {
				if (is_scalar($field->value)) {
					$value = $field->value;
					$key = $field->key;
					break;
				}
			}
		}
		
		// Find and set the service value label (if any), otherwise default to the service value itself
		if ($key) {
			// Fetch module row meta data for finding the service value label
			$module_row_id = (isset($service->module_row_id) ? $service->module_row_id : null);
			$row = $this->getModuleRow($module_row_id);
			
			if ($row && isset($row->meta))
				$value = $this->formatServiceName($row->meta, $key, $value);
		}
		
		return $value;
	}
	
	/**
	 * Returns a noun used to refer to a module row
	 *
	 * @return string The noun used to refer to a module row
	 */
	public function moduleRowName() {
		return Language::_("UniversalServerModule.module_row", true);
	}
	
	/**
	 * Returns a noun used to refer to a module row in plural form
	 *
	 * @return string The noun used to refer to a module row in plural form
	 */
	public function moduleRowNamePlural() {
		return Language::_("UniversalServerModule.module_row_plural", true);
	}
	
	/**
	 * Returns a noun used to refer to a module group
	 *
	 * @return string The noun used to refer to a module group
	 */
	public function moduleGroupName() {
		return Language::_("UniversalServerModule.module_group", true);
	}
	
	/**
	 * Returns the key used to identify the primary field from the set of module row meta fields.
	 *
	 * @return string The key used to identify the primary field from the set of module row meta fields
	 */
	public function moduleRowMetaKey() {
		return "name";
	}
	
	/**
	 * Returns the value used to identify a particular package service which has
	 * not yet been made into a service. This may be used to uniquely identify
	 * an uncreated services of the same package (i.e. in an order form checkout)
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @return string The value used to identify this package service
	 * @see Module::getServiceName()
	 */
	public function getPackageServiceName($package, array $vars=null) {
		
		// Set the service name to the first scalar meta value
		$key = null;
		$value = null;
		if (isset($vars['meta']) && is_array($vars['meta'])) {
			foreach ($vars['meta'] as $meta_key => $meta_value) {
				if (is_scalar($meta_value)) {
					$value = $meta_value;
					$key = $meta_key;
					break;
				}
			}
		}
		
		// Format the service name by changing the value to its label if necessary (e.g. drop-down options)
		if ($key) {
			// Fetch module row meta data for finding the service value label
			$module_row_id = (isset($package->module_row) ? $package->module_row : null);
			$row = $this->getModuleRow($module_row_id);
			
			if ($row && isset($row->meta) && $key)
				$value = $this->formatServiceName($row->meta, $key, $value);
		}
		
		return $value;
	}
	
	/**
	 * Adds the service to the remote server. Sets Input errors on failure,
	 * preventing the service from being added.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being added (if the current service is an addon service service and parent service has already been provisioned)
	 * @param string $status The status of the service being added. These include:
	 * 	- active
	 * 	- canceled
	 * 	- pending
	 * 	- suspended
	 * @return array A numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function addService($package, array $vars=null, $parent_package=null, $parent_service=null, $status="pending") {
		$meta = $this->processService("add", $vars, $package);

		if ($this->Input->errors())
			return;

		return $meta;
	}
	
	/**
	 * Edits the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being edited.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being edited (if the current service is an addon service)
	 * @return array A numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function editService($package, $service, array $vars=array(), $parent_package=null, $parent_service=null) {
		$meta = $this->processService("edit", $vars, $package);
	
		if ($this->Input->errors())
			return;
		
		return $meta;
	}
	
	/**
	 * Cancels the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being canceled.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being canceled (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function cancelService($package, $service, $parent_package=null, $parent_service=null) {
		
		if (!$this->sendNotification("service_notice_cancel", $service->fields, $package->module_row, null, $package->meta)) {
			$this->Input->setErrors(array('service_notice_cancel' => array('failed' => Language::_("UniversalServerModule.!error.service_notice_cancel.failed", true))));
			return;
		}
		
		return null;
	}
	
	/**
	 * Suspends the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being suspended.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being suspended (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function suspendService($package, $service, $parent_package=null, $parent_service=null) {
		
		if (!$this->sendNotification("service_notice_suspend", $service->fields, $package->module_row, null, $package->meta)) {
			$this->Input->setErrors(array('service_notice_suspend' => array('failed' => Language::_("UniversalServerModule.!error.service_notice_suspend.failed", true))));
			return;
		}
		
		return null;
	}
	
	/**
	 * Unsuspends the service on the remote server. Sets Input errors on failure,
	 * preventing the service from being unsuspended.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being unsuspended (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function unsuspendService($package, $service, $parent_package=null, $parent_service=null) {
		
		if (!$this->sendNotification("service_notice_unsuspend", $service->fields, $package->module_row, null, $package->meta)) {
			$this->Input->setErrors(array('service_notice_unsuspend' => array('failed' => Language::_("UniversalServerModule.!error.service_notice_unsuspend.failed", true))));
			return;
		}
		
		return null;
	}
	
	/**
	 * Allows the module to perform an action when the service is ready to renew.
	 * Sets Input errors on failure, preventing the service from renewing.
	 *
	 * @param stdClass $package A stdClass object representing the current package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being renewed (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function renewService($package, $service, $parent_package=null, $parent_service=null) {
		
		if (!$this->sendNotification("service_notice_renew", $service->fields, $package->module_row, null, $package->meta)) {
			$this->Input->setErrors(array('service_notice_renew' => array('failed' => Language::_("UniversalServerModule.!error.service_notice_renew.failed", true))));
			return;
		}
		
		return null;
	}
	
	/**
	 * Updates the package for the service on the remote server. Sets Input
	 * errors on failure, preventing the service's package from being changed.
	 *
	 * @param stdClass $package_from A stdClass object representing the current package
	 * @param stdClass $package_to A stdClass object representing the new package
	 * @param stdClass $service A stdClass object representing the current service
	 * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
	 * @param stdClass $parent_service A stdClass object representing the parent service of the service being changed (if the current service is an addon service)
	 * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function changeServicePackage($package_from, $package_to, $service, $parent_package=null, $parent_service=null) {
		
		if (!$this->sendNotification("service_notice_package_change", $service->fields, $package_to->module_row, null, $package_to->meta)) {
			$this->Input->setErrors(array('service_notice_package_change' => array('failed' => Language::_("UniversalServerModule.!error.service_notice_package_change.failed", true))));
			return;
		}
		
		return null;
	}
	
	/**
	 * Validates input data when attempting to add a package, returns the meta
	 * data to save when adding a package. Performs any action required to add
	 * the package on the remote server. Sets Input errors on failure,
	 * preventing the package from being added.
	 *
	 * @param array An array of key/value pairs used to add the package
	 * @return array A numerically indexed array of meta fields to be stored for this package containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function addPackage(array $vars=null) {
		$meta = $this->processPackage("add", $vars);
	
		if ($this->Input->errors())
			return;

		return $meta;
	}
	
	/**
	 * Validates input data when attempting to edit a package, returns the meta
	 * data to save when editing a package. Performs any action required to edit
	 * the package on the remote server. Sets Input errors on failure,
	 * preventing the package from being edited.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array An array of key/value pairs used to edit the package
	 * @return array A numerically indexed array of meta fields to be stored for this package containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 * @see Module::getModule()
	 * @see Module::getModuleRow()
	 */
	public function editPackage($package, array $vars=null) {
		$meta = $this->processPackage("edit", $vars, $package);
	
		if ($this->Input->errors())
			return;

		return $meta;
	}
	
	/**
	 * Returns the rendered view of the manage module page
	 *
	 * @param mixed $module A stdClass object representing the module and its rows
	 * @param array $vars An array of post data submitted to or on the manager module page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the manager module page
	 */
	public function manageModule($module, array &$vars) {
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("manage", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));

		$this->view->set("module", $module);
		
		return $this->view->fetch();
	}
	
	/**
	 * Returns the rendered view of the add module row page
	 *
	 * @param array $vars An array of post data submitted to or on the add module row page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the add module row page
	 */
	public function manageAddRow(array &$vars) {
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("add_row", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		if (!isset($vars['package_email_html']))
			$vars['package_email_html'] = "{% debug %}";
		if (!isset($vars['package_email_text']))
			$vars['package_email_text'] = "{% debug %}";
		if (!isset($vars['service_email_html']))
			$vars['service_email_html'] = "{% debug %}";
		if (!isset($vars['service_email_text']))
			$vars['service_email_text'] = "{% debug %}";
		
		$this->view->set("required_options", array('true' => Language::_("UniversalServerModule.true", true), 'false' => Language::_("UniversalServerModule.false", true)));
		$this->view->set("encrypt_options", array('true' => Language::_("UniversalServerModule.true", true), 'false' => Language::_("UniversalServerModule.false", true)));
		$this->view->set("field_types", $this->getFieldTypes());
		$this->view->set("package_notices", $this->getPackageNotices());
		$this->view->set("service_notices", $this->getServiceNotices());
		$this->view->set("vars", (object)$vars);
		return $this->view->fetch();
	}
	
	/**
	 * Returns the rendered view of the edit module row page
	 *
	 * @param stdClass $module_row The stdClass representation of the existing module row
	 * @param array $vars An array of post data submitted to or on the edit module row page (used to repopulate fields after an error)
	 * @return string HTML content containing information to display when viewing the edit module row page
	 */	
	public function manageEditRow($module_row, array &$vars) {
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("edit_row", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html", "Widget"));
		
		if (empty($vars))
			$vars = $this->formatModuleRowFields($module_row->meta);

		$this->view->set("required_options", array('true' => Language::_("UniversalServerModule.true", true), 'false' => Language::_("UniversalServerModule.false", true)));
		$this->view->set("encrypt_options", array('true' => Language::_("UniversalServerModule.true", true), 'false' => Language::_("UniversalServerModule.false", true)));
		$this->view->set("field_types", $this->getFieldTypes());
		$this->view->set("package_notices", $this->getPackageNotices());
		$this->view->set("service_notices", $this->getServiceNotices());		
		$this->view->set("vars", (object)$vars);
		return $this->view->fetch();
	}
	
	/**
	 * Adds the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being added.
	 *
	 * @param array $vars An array of module info to add
	 * @return array A numerically indexed array of meta fields for the module row containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function addModuleRow(array &$vars) {
		$this->Input->setRules($this->getModuleRowRules($vars));
		
		if ($this->Input->validates($vars))
			return $this->formatRowMeta($vars);
	}
	
	/**
	 * Edits the module row on the remote server. Sets Input errors on failure,
	 * preventing the row from being updated.
	 *
	 * @param stdClass $module_row The stdClass representation of the existing module row
	 * @param array $vars An array of module info to update
	 * @return array A numerically indexed array of meta fields for the module row containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	public function editModuleRow($module_row, array &$vars) {
		$this->Input->setRules($this->getModuleRowRules($vars));
		
		if ($this->Input->validates($vars))
			return $this->formatRowMeta($vars);
	}
	
	/**
	 * Returns all fields used when adding/editing a package, including any
	 * javascript to execute when the page is rendered with these fields.
	 *
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getPackageFields($vars=null) {
		$fields = new ModuleFields();

		if (isset($vars->module_row) && $vars->module_row > 0) {
			$row = $this->getModuleRow($vars->module_row);
			
			$row_fields = array();
			if ($row->meta) {
				$row_fields = $this->formatModuleRowFields($row->meta);
				
				$field_data = array();
				// Reformat package fields into a more usable format
				foreach ($row_fields['package_fields'] as $key => $values) {
					foreach ($values as $i => $value) {
						$field_data[$i][$key] = $value;
					}
				}
				
				$this->setModuleFields($fields, $field_data, $vars);
			}
		}
		elseif (isset($vars->module_id)) {
			$rows = $this->getModuleRows();
			
			if (empty($rows)) {
				$uri = WEBDIR . Configure::get("Route.admin") . "/settings/company/modules/addrow/" . $vars->module_id;
				$fields->setHtml(Language::_("UniversalServerModule.getPackageFields.empty_module_row", true, $uri));
			}
			else {
				$fields->setHtml("
					<script type=\"text/javascript\">
						$(document).ready(function() {
							// Fetch initial module options
							fetchModuleOptions();
						});
					</script>
				");
			}
		}
		else {
			$fields->setHtml("
				<script type=\"text/javascript\">
					$(document).ready(function() {
						// Fetch initial module options
						fetchModuleOptions();
					});
				</script>
			");
		}
		
		return $fields;
	}
	
	/**
	 * Returns an array of key values for fields stored for a module, package,
	 * and service under this module, used to substitute those keys with their
	 * actual module, package, or service meta values in related emails.
	 *
	 * @return array A multi-dimensional array of key/value pairs where each key is one of 'module', 'package', or 'service' and each value is a numerically indexed array of key values that match meta fields under that category.
	 * @see Modules::addModuleRow()
	 * @see Modules::editModuleRow()
	 * @see Modules::addPackage()
	 * @see Modules::editPackage()
	 * @see Modules::addService()
	 * @see Modules::editService()
	 */
	public function getEmailTags() {
		return array('module' => array("*"), 'package' => array("*"), 'service' => array("*"));
	}
	
	/**
	 * Returns all fields to display to an admin attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */
	public function getAdminAddFields($package, $vars=null) {
		$fields = new ModuleFields();
		
		if (!isset($vars->meta))
			$vars->meta = array();
		
		if (isset($package->module_row) && $package->module_row > 0) {
			$row = $this->getModuleRow($package->module_row);
			
			// Set the module row, which will allow us to reference it later when getName() is invoked
			$this->setModuleRow($row);
			
			$row_fields = array();
			if ($row->meta) {
				$row_fields = $this->formatModuleRowFields($row->meta);
				
				$field_data = array();
				$row_fields = json_decode(str_replace("hidden","text",json_encode($row_fields)),true);
				// Reformat package fields into a more usable format
				foreach ($row_fields['service_fields'] as $key => $values) {
					foreach ($values as $i => $value) {
						$field_data[$i][$key] = $value;
					}
				}
				
				$this->setModuleFields($fields, $field_data, $vars);
			}
		}

		return $fields;
	}
	
	/**
	 * Returns all fields to display to a client attempting to add a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getClientAddFields($package, $vars=null) {
		$fields = new ModuleFields();
		
		if (!isset($vars->meta))
			$vars->meta = array();
		
		if (isset($package->module_row) && $package->module_row > 0) {
			$row = $this->getModuleRow($package->module_row);
			
			// Set the module row, which will allow us to reference it later when getName() is invoked
			$this->setModuleRow($row);
			
			$row_fields = array();
			if ($row->meta) {
				$row_fields = $this->formatModuleRowFields($row->meta);
				
				$field_data = array();
				// Reformat package fields into a more usable format
				foreach ($row_fields['service_fields'] as $key => $values) {
					foreach ($values as $i => $value) {
						$field_data[$i][$key] = $value;
					}
				}
				
				$this->setModuleFields($fields, $field_data, $vars);
			}
		}

		return $fields;	}
	
	/**
	 * Returns all fields to display to an admin attempting to edit a service with the module
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param $vars stdClass A stdClass object representing a set of post fields
	 * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
	 */	
	public function getAdminEditFields($package, $vars=null) {
		// Same as adding
		return $this->getAdminAddFields($package, $vars);
	}
	
	/**
	 * Fetches the HTML content to display when viewing the service info in the
	 * admin interface.
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @param stdClass $package A stdClass object representing the service's package
	 * @return string HTML content containing information to display when viewing the service info
	 */
	public function getAdminServiceInfo($service, $package) {
		$row = $this->getModuleRow();
		
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("admin_service_info", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));
		
		$this->view->set("module_row", $row);
		$this->view->set("package", $package);
		$this->view->set("service", $service);
		$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));
        //$this->view->set("licenses", $this->getLicenseTypes());
		
		return $this->view->fetch();
	}
	
	/**
	 * Fetches the HTML content to display when viewing the service info in the
	 * client interface.
	 *
	 * @param stdClass $service A stdClass object representing the service
	 * @param stdClass $package A stdClass object representing the service's package
	 * @return string HTML content containing information to display when viewing the service info
	 */
	public function getClientServiceInfo($service, $package) {
		$row = $this->getModuleRow();
		
		// Load the view into this object, so helpers can be automatically added to the view
		$this->view = new View("client_service_info", "default");
		$this->view->base_uri = $this->base_uri;
		$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("module_row", $row);
		$this->view->set("package", $package);
		$this->view->set("service", $service);
		$this->view->set("service_fields", $this->serviceFieldsToObject($service->fields));
		
		return $this->view->fetch();
	}
	
	/**
	 * Attempts to validate service info. This is the top-level error checking method. Sets Input errors on failure.
	 *
	 * @param stdClass $package A stdClass object representing the selected package
	 * @param array $vars An array of user supplied info to satisfy the request
	 * @param boolean $edit True if this is an edit, false otherwise
	 * @return boolean True if the service validates, false otherwise. Sets Input errors when false.
	 */
	public function validateService($package, array $vars=null, $edit=false) {
		if ($package)
			$module_row_id = $package->module_row;
		else
			$module_row_id = isset($vars['module_row']) ? $vars['module_row'] : null;
		$row = $this->getModuleRow($module_row_id);
		
		if (!array_key_exists("meta", (array)$vars))
			$vars['meta'] = $vars;
		
		$rules = array();
		if ($row && $row->meta->service_rules != "" && isset($vars['meta'])) {
			Loader::loadComponents($this, array("Json"));
			$rules = $this->Json->decode($row->meta->service_rules, true);
		}

		$fields = $this->formatModuleRowFields($row->meta);
		
		// Set required rules
		if (isset($fields['service_fields']['required'])) {
			foreach ($fields['service_fields']['required'] as $i => $required) {
				$name = $fields['service_fields']['name'][$i];
				if ($required == "true") {
					$is_array = (isset($vars['meta'][$name]) && is_array($vars['meta'][$name]));
					$rules[$name]['required'] = array(
						'rule' => $is_array ? "count" : "isEmpty",
						'negate' => !$is_array,
						'message' => Language::_("UniversalServerModule.!error.service_field.required", true, $fields['service_fields']['label'][$i])
					);
				}
			}
		}
		
		if (!isset($vars['meta']))
			$vars['meta'] = array();
		
		$this->Input->setRules($rules);
		$validation_fields = array_merge($vars['meta'], array_intersect_key($vars, array_flip(self::$reserved_fields)));
		return $this->Input->validates($validation_fields);
	}
	
	/**
	 * Process Packages add/edit
	 *
	 * @param string $type The type of process (add/edit)
	 * @param array $vars An array of key/value pairs
	 * @return array A numerically indexed array of meta fields to be stored for this package containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	private function processPackage($type, array $vars, $package = null) {
		$module_row_id = null;
		if (isset($vars['module_row']) && $vars['module_row'])
			$module_row_id = $vars['module_row'];
		elseif ($package)
			$module_row_id = $package->module_row;
		$row = $this->getModuleRow($module_row_id);

		if (!$row) {
			$this->Input->setErrors(array(
				'module_row' => array(
					'invalid' => Language::_("UniversalServerModule.!error.module_row.invalid", true)
				)
			));
			return;
		}
		
		$rules = array();
		if (isset($row->meta->package_rules) && $row->meta->package_rules != "" && isset($vars['meta'])) {
			Loader::loadComponents($this, array("Json"));
			$rules = $this->Json->decode($row->meta->package_rules, true);
		}

		$fields = $this->formatModuleRowFields($row->meta);
		
		// Set required rules
		if (isset($fields['package_fields']['required'])) {
			foreach ($fields['package_fields']['required'] as $i => $required) {
				$name = $fields['package_fields']['name'][$i];
				if ($required == "true") {
					$is_array = (isset($vars['meta'][$name]) && is_array($vars['meta'][$name]));
					$rules[$name]['required'] = array(
						'rule' => $is_array ? "count" : "isEmpty",
						'negate' => !$is_array,
						'message' => Language::_("UniversalServerModule.!error.package_field.required", true, $fields['package_fields']['label'][$i])
					);
				}
			}
		}
		
		$this->Input->setRules($rules);
		if (!$this->Input->validates($vars['meta']))
			return;
		
		$meta = array();
		if (isset($fields['package_fields']['name'])) {
			foreach ($fields['package_fields']['name'] as $i => $value) {
				if ($fields['package_fields']['type'][$i] == "secret")
					continue;
				
				$meta[] = array(
					'key' => $value,
					'value' => isset($vars['meta'][$value]) ? $vars['meta'][$value] : "",
					'encrypted' => $fields['package_fields']['encrypt'][$i] == "true" ? 1 : 0
				);
			}
		}
		
		if (!$this->sendNotification("package_notice_" . $type, $meta, $module_row_id, $vars)) {
			$this->Input->setErrors(array('package_notice_' . $type => array('failed' => Language::_("UniversalServerModule.!error.package_notice_" . $type . ".failed", true))));
			return;
		}
		
		return $meta;
	}
	
	/**
	 * Process Services add/edit
	 *
	 * @param string $type The type of process (add/edit)
	 * @param array $vars An array of key/value pairs
	 * @return array A numerically indexed array of meta fields to be stored for this service containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	private function processService($type, array $vars, $package = null) {
		if ($package)
			$module_row_id = $package->module_row;
		else
			$module_row_id = isset($vars['module_row']) ? $vars['module_row'] : null;
		$this->validateService($package, $vars, $type == "edit");
		
		if ($this->Input->errors())
			return;
		
		$row = $this->getModuleRow($module_row_id);
		$fields = $this->formatModuleRowFields($row->meta);
		
		$meta = null;
		if (isset($fields['service_fields']['name'])) {
			foreach ($fields['service_fields']['name'] as $i => $value) {
				if ($fields['service_fields']['type'][$i] == "secret")
					continue;
	
				if (isset($vars['meta'][$value]) || isset($vars[$value])) {
					if (!$meta)
						$meta = array();
						
					$meta[] = array(
						'key' => $value,
						'value' => isset($vars['meta'][$value]) ? $vars['meta'][$value] : $vars[$value],
						'encrypted' => $fields['service_fields']['encrypt'][$i] == "true" ? 1 : 0
					);
				}
			}
		}
		
		if (isset($vars['use_module']) && $vars['use_module'] == "true") {
			if (!$this->sendNotification("service_notice_" . $type, $meta, $module_row_id, $vars, $package->meta)) {
				$this->Input->setErrors(array('service_notice_' . $type => array('failed' => Language::_("UniversalServerModule.!error.service_notice_" . $type . ".failed", true))));
				return;
			}
		}
		
		return $meta;
	}
	
	/**
	 * Sets fields into the ModuleFields object according to $field_data
	 *
	 * @param ModuleFields $fields The ModuleFields object to set fields to
	 * @param array $field_data A numerically indexed array of field data including:
	 * 	- type
	 * 	- label
	 * 	- name
	 * 	- values
	 * @param stdClass $vars A stdClass object representing input fields
	 */
	private function setModuleFields(ModuleFields $fields, array $field_data, $vars=null) {
		Loader::loadHelpers($this, array("Html"));

		foreach ($field_data as $field) {
			$options = $this->unserializeMetaValues($field['values']);

			$field_type = "field" . ucfirst($field['type']);
			$field_name = "meta[" . $field['name'] . "]";
			$field_value = $this->Html->ifSet($vars->meta[$field['name']], $this->Html->ifSet($vars->{$field['name']}, $field['values']));
			
			if (in_array($field['name'], self::$reserved_fields)) {
				$field_name = $field['name'];
				$field_value = $this->Html->ifSet($vars->{$field['name']});
			}
			
			switch ($field['type']) {
				case "text":
				case "hidden":
				case "textarea":
					$label = $fields->label($field['label'], "uni_" . $field['name']);
					$label->attach($fields->{$field_type}($field_name,
						$field_value, array('id'=>"uni_" . $field['name'])));
					$fields->setField($label);
					break;
				case "password":
					$label = $fields->label($field['label'], "uni_" . $field['name']);
					$label->attach($fields->{$field_type}($field_name,
						array('id'=>"uni_" . $field['name'], 'value' => $field_value)));
					$fields->setField($label);
					break;
				case "select":
					$label = $fields->label($field['label'], "uni_" . $field['name']);
					$label->attach($fields->{$field_type}($field_name, $options,
						$field_value, array('id'=>"uni_" . $field['name'])));
					$fields->setField($label);
					break;
				case "radio":
				case "checkbox":
					$label = $fields->label($field['label'], "uni_" . $field['name']);
					foreach ($options as $key => $value) {
						
						$field_label = $fields->label($value, "uni_" . $field['name'] . "_" . $key);
						
						$checked = in_array($key, (array)$field_value);
						
						$label->attach($fields->{$field_type}($field_name . ($field['type'] == "checkbox" ? "[]" : ""),
							$key, $checked, array('id'=>"uni_" . $field['name'] . "_" . $key), $field_label));
					}
					$fields->setField($label);
					break;
			}
		}
	}
	
	/**
	 * Sends notification for the given action if supported by the module row
	 *
	 * @param string $action The action to send a notification for
	 * @param array $meta A numerically indexed array of meta fields containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * @param int $module_row_id The ID of the module row to send the notification for
	 * @param array $additional_fields An array of key/value pairs to send in the notification
	 * @param stdClass $package_meta A stdClass object of package meta data (if any)
	 * @return boolean True if the notice was successful, false otherwise
	 */
	private function sendNotification($action, $meta, $module_row_id = null, $additional_fields = null, $package_meta = null) {
		$row = $this->getModuleRow($module_row_id);
		
		if ($row) {
			$tags = $this->serviceFieldsToObject((array)$meta);
			
			if ($package_meta) {
				foreach ($package_meta as $key => $value) {
					if (!isset($tags->{$key}))
						$tags->{$key} = $value;
				}
			}
			
			// Look for 'secret' package fields to append
			$meta_fields = $this->formatModuleRowFields($row->meta);
			if (isset($meta_fields['package_fields']['type'])) {
				foreach ($meta_fields['package_fields']['type'] as $i => $type) {
					$key = $meta_fields['package_fields']['name'][$i];
					if ($type == "secret" && !isset($tags->{$key}))
						$tags->{$key} = $meta_fields['package_fields']['values'][$i];
				}
			}
			
			// Look for 'secret' service fields to append if this is a service notice
			if (strpos($action, "service") !== false && isset($meta_fields['service_fields']['type'])) {
				foreach ($meta_fields['service_fields']['type'] as $i => $type) {
					if ($type == "secret")
						$tags->{$meta_fields['service_fields']['name'][$i]} = $meta_fields['service_fields']['values'][$i];
				}
			}

			$tags->_other = $additional_fields;
			
			if (isset($row->meta->{$action}) && trim($row->meta->{$action}) != "") {
				
				if ($this->isUrl($row->meta->{$action})) {
					$code = str_replace("notice", "code", $action);
					$response = str_replace("notice", "response", $action);
					
					return $this->sendHttpNotice($row->meta->{$action}, (array)$tags, $row->meta->{$code}, $row->meta->{$response});
				}
				else
					$this->sendEmailNotice($action, (array)$tags, $row->meta);
			}
		}
		
		return true;
	}
	
	/**
	 * Sends an email notification to the given address with the given tags
	 *
	 * @param string $action The action to send the notification for
	 * @param array $tags A key/value pairs of tags and their replacement data
	 * @param stdClass $meta A stdClass object of module row meta field data
	 * @return boolean True if the email was successfully sent, false otherwise
	 */
	private function sendEmailNotice($action, $tags, $meta) {
		
		Loader::loadModels($this, array("Emails"));
		
		$to = $meta->{$action};
		$from = null;
		$subject = null;
		$body = null;
		
		if (strpos($action, "service") !== false) {
			$from = $meta->service_email_from;
			$subject = $meta->service_email_subject;
			$body = array('text' => $meta->service_email_text, 'html' => $meta->service_email_html);
		}
		else {
			$from = $meta->package_email_from;
			$subject = $meta->package_email_subject;
			$body = array('text' => $meta->package_email_text, 'html' => $meta->package_email_html);			
		}
		
		$this->Emails->sendCustom($from, $from, $to, $subject, $body, $tags);
		
		if (($errors = $this->Emails->errors())) {
			$this->Input->setErrors($errors);
			return false;
		}
		return true;
	}
	
	/**
	 * Sends an HTTP POST request to the given URL with the given arguments
	 *
	 * @param string $url The URL to post
	 * @param array $args An array of key/value post fields
	 * @param string $response_code The response code to accept for successful responses
	 * @param string $response The response to expect for successful responses, may be a regular expression
	 * @return boolean True on success, false on error
	 */
	private function sendHttpNotice($url, $args, $response_code = null, $response = null) {
		// Log request
		$this->log($url, serialize($args), "input", true);
		
		$pass = true;
		$output = $this->httpRequest("POST", $url, $args);
		
		if ($response_code != "") {
			if (isset($this->Http)) {
				if ($response_code != $this->Http->responseCode())
					$pass = false;
			}
		}
		
		if ($response != "") {
			if (strpos($output, $response) === false)
				$pass = false;
		}
		
		// Log output
		$this->log($url, $output, "output", $pass);

		return $pass;
	}
	
	/**
	 * Formats module row input fields into a proper format required by Module::addModuleRow() and Module::editModuleRow().
	 *
	 * @param array An array of input key/value pairs
	 * @return array A numerically indexed array of meta fields for the module row containing:
	 * 	- key The key for this meta field
	 * 	- value The value for this key
	 * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
	 */
	private function formatRowMeta(array &$vars) {
		$meta = array();
		$meta_fields = array("name", "package_rules", "service_rules",
			"package_email_from", "package_email_subject", "package_email_html", "package_email_text",
			"service_email_from", "service_email_subject", "service_email_html", "service_email_text");
		
		foreach ($this->getPackageNotices() as $key => $value) {
			$meta_fields[] = "package_notice_" . $key;
			$meta_fields[] = "package_code_" . $key;
			$meta_fields[] = "package_response_" . $key;
		}
		
		foreach ($this->getServiceNotices() as $key => $value) {
			$meta_fields[] = "service_notice_" . $key;
			$meta_fields[] = "service_code_" . $key;
			$meta_fields[] = "service_response_" . $key;
		}
		
		foreach ($vars as $key => $value) {
			if (in_array($key, $meta_fields)) {
				$meta[] = array(
					'key'=>$key,
					'value'=>$value
				);
			}
		}
		
		if (isset($vars['package_fields'])) {
			for ($j=0, $i=0; $i<count($vars['package_fields']['label']); $i++) {
				
				if ($vars['package_fields']['name'][$i] == "")
					continue;
				
				$meta[] = array(
					'key' => "package_field_label_" . $j,
					'value' => $vars['package_fields']['label'][$i]
				);
				$meta[] = array(
					'key' => "package_field_name_" . $j,
					'value' => $vars['package_fields']['name'][$i]
				);
				$meta[] = array(
					'key' => "package_field_type_" . $j,
					'value' => $vars['package_fields']['type'][$i]
				);
				$meta[] = array(
					'key' => "package_field_values_" . $j,
					'value' => $vars['package_fields']['values'][$i]
				);
				$meta[] = array(
					'key' => "package_field_required_" . $j,
					'value' => $vars['package_fields']['required'][$i]
				);
				$meta[] = array(
					'key' => "package_field_encrypt_" . $j,
					'value' => $vars['package_fields']['encrypt'][$i]
				);
				
				$j++;
			}
		}
		
		if (isset($vars['service_fields'])) {
			for ($j=0, $i=0; $i<count($vars['service_fields']['label']); $i++) {
				
				if ($vars['service_fields']['name'][$i] == "")
					continue;
				
				$meta[] = array(
					'key' => "service_field_label_" . $j,
					'value' => $vars['service_fields']['label'][$i]
				);
				$meta[] = array(
					'key' => "service_field_name_" . $j,
					'value' => $vars['service_fields']['name'][$i]
				);
				$meta[] = array(
					'key' => "service_field_type_" . $j,
					'value' => $vars['service_fields']['type'][$i]
				);
				$meta[] = array(
					'key' => "service_field_values_" . $j,
					'value' => $vars['service_fields']['values'][$i]
				);
				$meta[] = array(
					'key' => "service_field_required_" . $j,
					'value' => $vars['service_fields']['required'][$i]
				);
				$meta[] = array(
					'key' => "service_field_encrypt_" . $j,
					'value' => $vars['service_fields']['encrypt'][$i]
				);
				
				$j++;
			}
		}
		
		return $meta;
	}
	
	/**
	 * Converts module row meta fields from key/value pairs to array sets suitable
	 * for use in forms.
	 *
	 * @param stdClass $module_row_meta An object of module row meta fields
	 * @return array An array of formatted module row meta fields
	 */
	private function formatModuleRowFields($module_row_meta) {
		$fields = array('package_fields' => array(), 'service_fields' => array());
		foreach ($module_row_meta as $key => $value) {
			$index = ltrim(strrchr($key, "_"), "_");
			if (substr($key, 0, 14) == "package_field_") {
				$key = str_replace("_" . $index, "", str_replace("package_field_", "", $key));
				$fields['package_fields'][$key][$index] = $value;
			}
			elseif (substr($key, 0, 14) == "service_field_") {
				$key = str_replace("_" . $index, "", str_replace("service_field_", "", $key));
				$fields['service_fields'][$key][$index] = $value;
			}
			else {
				$fields[$key] = $value;
			}
		}
		return $fields;
	}
	
	/**
	 * Formats the service name value to its label, if any
	 *
	 * @param stdClass $meta An stdClass object containing of key/value meta data to be used to determine the service name
	 * @param string $key The meta key selected
	 * @param string $value The meta value selected
	 */
	private function formatServiceName(stdClass $meta, $key, $value) {
		$value = ($value ? $value : null);
		
		// Determine if the value should be set to a service field name
		if ($key && !empty($meta)) {
			$row_fields = $this->formatModuleRowFields($meta);
			
			// Reformat service fields into a more usable format
			$field_data = array();
			foreach ($row_fields['service_fields'] as $field_key => $values) {
				foreach ($values as $i => $field_value) {
					$field_data[$i][$field_key] = $field_value;
				}
			}
			
			// Find the matching service field whose values are used as the service field name
			$field_values = array();
			foreach ($field_data as $fields) {
				if (isset($fields['name']) && $fields['name'] == $key) {
					// Format the meta values
					$field_values = $this->unserializeMetaValues($fields['values']);
					break;
				}
			}
			
			// Set the service field name to the field values' label
			if (isset($field_values[$value]))
				$value = $field_values[$value];
		}
		
		return $value;
	}
	
	/**
	 * Unserialize meta values of the format key2:value|key2:value2
	 *
	 * @param string $values A serialized set of values
	 * @return array An array of key/value pairs
	 */
	private function unserializeMetaValues($values) {
		if ($values == "")
			return array();
			
		$pairs = preg_split('~(?<!\\\)' . preg_quote("|", '~') . '~', $values);
		
		$options = array();
		foreach ($pairs as $pair) {
			$pair = preg_split('~(?<!\\\)' . preg_quote(":", '~') . '~', $pair);
			//$pair = preg_split('~\\\\.(*SKIP)(*FAIL)|\:~s', $pair);
			if (count($pair) == 2)
				$options[stripslashes($pair[0])] = stripslashes($pair[1]);
		}
		
		return $options;
	}
	
	/**
	 * Returns a key/value pair of package notices, which are events that trigger
	 * a HTTP POST or Email to a given location
	 *
	 * @return array An array of key/value pair package notices, where each key is the notice type and each value is its name
	 */
	private function getPackageNotices() {
		return array(
			'add' => Language::_("UniversalServerModule.getpackagenotices.add", true),
			'edit' => Language::_("UniversalServerModule.getpackagenotices.edit", true)
		);
	}

	/**
	 * Returns a key/value pair of service notices, which are events that trigger
	 * a HTTP POST or Email to a given location
	 *
	 * @return array An array of key/value pair service notices, where each key is the notice type and each value is its name
	 */	
	private function getServiceNotices() {
		return array(
			'add' => Language::_("UniversalServerModule.getservicenotices.add", true),
			'edit' => Language::_("UniversalServerModule.getservicenotices.edit", true),
			'suspend' => Language::_("UniversalServerModule.getservicenotices.suspend", true),
			'unsuspend' => Language::_("UniversalServerModule.getservicenotices.unsuspend", true),
			'cancel' => Language::_("UniversalServerModule.getservicenotices.cancel", true),
			'renew' => Language::_("UniversalServerModule.getservicenotices.renew", true),
			'package_change' => Language::_("UniversalServerModule.getservicenotices.package_change", true),
			'on_button' => Language::_("UniversalServerModule.on_button", true),
			'off_button' => Language::_("UniversalServerModule.off_button", true),
			'reset_button' => Language::_("UniversalServerModule.reset_button", true),
		);
	}
	
	/**
	 * Returns a key/value pair of all input field types supported
	 *
	 * @return array An array of key/value pairs of input field types supported, where each key is the field type and each value it its name
	 */
	private function getFieldTypes() {
		return array(
			'text' => Language::_("UniversalServerModule.getfieldtypes.text", true),
			'textarea' => Language::_("UniversalServerModule.getfieldtypes.textarea", true),
			'password' => Language::_("UniversalServerModule.getfieldtypes.password", true),
			'select' => Language::_("UniversalServerModule.getfieldtypes.select", true),
			'radio' => Language::_("UniversalServerModule.getfieldtypes.radio", true),
			'checkbox' => Language::_("UniversalServerModule.getfieldtypes.checkbox", true),
			'hidden' => Language::_("UniversalServerModule.getfieldtypes.hidden", true),
			'secret' => Language::_("UniversalServerModule.getfieldtypes.secret", true),
		);
	}
	
	/**
	 * Returns all rules to validate when adding/edit a module row
	 *
	 * @return array An array of rules to validate when adding/editing a module row
	 */
	private function getModuleRowRules(array $vars) {
		$rules = array(
			'name' => array(
				'empty' => array(
					'rule' => "isEmpty",
					'negate' => true,
					'message' => Language::_("UniversalServerModule.!error.name.empty", true)
				)
			)
		);
		
		foreach ($vars as $key => $value) {
			if (strpos($key, "service_notice_") !== false && $value != "" && !$this->isUrl($value)) {
				$rules['service_email_from']['required'] = array(
					'rule' => "isEmail",
					'message' => Language::_("UniversalServerModule.!error.service_email_from.required", true)
				);
			}
			elseif (strpos($key, "package_notice_") !== false && $value != "" && !$this->isUrl($value)) {
				$rules['package_email_from']['required'] = array(
					'rule' => "isEmail",
					'message' => Language::_("UniversalServerModule.!error.package_email_from.required", true)
				);
			}
		}
		
		return $rules;
	}
	

    /**
     * The More Info tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function moreinfo($package, $service, array $get=null, array $post=null, array $files=null) {
         $row = $this->getModuleRow($package->module_row);
         $servicefields = array();
         foreach ($row->meta as $key=>$value ) {
              if (strpos($key, "service_field_name") !== false) {
                // Here we match the service field label with their key and value
                foreach($service->fields as $field) {
                  if($field->key == $value) {
                     $name = 'service_field_label_' . str_replace('service_field_name_', '', $key);
                     $servicefields[] = array("label" => $row->meta->$name,
                                              "key" => $value,
                                              "value" => $field->value); 
                  }
                }
              }
         }
                  
	    //Get SSH Functions
		include('api/functions.php');
		
	    //Get Variables
		global $ded_ip; global $root_pass; global $control_url; global $prod_id;
        foreach($servicefields as $field) {
            if(preg_match("/ded_ips/i", $field['key'])){  $ded_ip = $field['value']; $ded_ip = explode("-", $ded_ip); $ded_ip = trim($ded_ip[0]); }
            if(preg_match("/root_pass/i", $field['key'])){ $root_pass = $field['value']; }
           	if(preg_match("/control_url/i", $field['key'])){ $control_url = $field['value']; } 
           	if(preg_match("/prod_id/i", $field['key'])){ $prod_id = $field['value']; }
        }
		
        //SSH Access Data
		$server = $ded_ip; $port = 22; $user = 'root'; $pass = $root_pass; 
				
        //On Button Action
        if($_GET['on'] == 'true'){
			if(!empty($row->meta->service_notice_on_button)){
				if (!$this->sendNotification("service_notice_on_button", $service->fields, $package->module_row, null, $package->meta)) {
					$this->Input->setErrors(array('service_notice_on_button' => array('failed' => Language::_("UniversalServerModule.server_on_error", true))));
					$server_on_exe = false;
				} else {
				    $server_on_exe = true;
				}
			} else {
				$server_on_exe = false;
			}
		}
        //Off Button Action
        if($_GET['off'] == 'true'){
			if(!empty($row->meta->service_notice_off_button)){
				if (!$this->sendNotification("service_notice_off_button", $service->fields, $package->module_row, null, $package->meta)) {
					$this->Input->setErrors(array('service_notice_off_button' => array('failed' => Language::_("UniversalServerModule.server_off_error", true))));
					$server_off_exe = false;
				} else {
					$server_off_exe = true;
				}
			} else {
				if(ItsSshActivated($server, $port, $user, $pass)){
					offServerSSH($server, $port, $user, $pass);
					$server_off_exe = true;
				} else {
					$server_off_exe = false;
				}
			}
        }
        //Reset Button Action
        if($_GET['reset'] == 'true'){
			if(!empty($row->meta->service_notice_reset_button)){
				if (!$this->sendNotification("service_notice_reset_button", $service->fields, $package->module_row, null, $package->meta)) {
					$this->Input->setErrors(array('service_notice_reset_button' => array('failed' => Language::_("UniversalServerModule.server_reset_error", true))));
					$server_reset_exe = false;
				} else {
					$server_reset_exe = true;
				}
			} else {
				if(ItsSshActivated($server, $port, $user, $pass)){
					rebootServerSSH($server, $port, $user, $pass);
					$server_reset_exe = true;
				} else {
					$server_reset_exe = false;
				}
			}
        }
		 // Detect Control Panel 
				$cpanel = 'Without Panel';
				//cPanel
              	if(ping($ded_ip, 2082, 1) && $cpanel == 'Without Panel'){ $cpanel = 'cPanel'; 
              	if(empty($control_url)){ $control_url = 'https://'.$ded_ip.':2083';} }
				//DirectAdmin
              	if(ping($ded_ip, 2222, 1) && $cpanel == 'Without Panel'){ $cpanel = 'DirectAdmin'; 
              	if(empty($control_url)){ $control_url = 'http://'.$ded_ip.':2222';} }
				//Webuzo
              	if(ping($ded_ip, 2002, 1) && $cpanel == 'Without Panel'){ $cpanel = 'Webuzo'; 
              	if(empty($control_url)){ $control_url = 'http://'.$ded_ip.':2002';} }
				//Kloxo-MR
              	if(ping($ded_ip, 7777, 1) && $cpanel == 'Without Panel'){ $cpanel = 'Kloxo-MR'; 
              	if(empty($control_url)){ $control_url = 'https://'.$ded_ip.':7777';} }
				//VestaCP
              	if(ping($ded_ip, 8083, 1) && $cpanel == 'Without Panel'){ $cpanel = 'Vesta Control Panel'; 
              	if(empty($control_url)){ $control_url = 'https://'.$ded_ip.':8083';} }
				//CWP
              	if(ping($ded_ip, 2030, 1) && $cpanel == 'Without Panel'){ $cpanel = 'CentOS Web Panel'; 
              	if(empty($control_url)){ $control_url = 'http://'.$ded_ip.':2030';} }
				//SolusVM
              	if(ping($ded_ip, 5353, 1) && $cpanel == 'Without Panel'){ $cpanel = 'SolusVM'; 
              	if(empty($control_url)){ $control_url = 'https://'.$ded_ip.':5656/admincp';} }
              	//Get Status Server 
              	if(serverIsUp($ded_ip) || ping($ded_ip, 80, 1)){ $status_ded = Language::_("UniversalServerModule.active", true); $color_ded_sta = '#008000';} else { $status_ded = Language::_("UniversalServerModule.down", true); $color_ded_sta = '#800000';}
        	  //Get Screenshoot
         	  if(ping($ded_ip, 80, 1)){            
            		$api_key = 'decd546b9159161f';
					$f = json_decode(file_get_contents('https://api.page2images.com/restfullink?p2i_url=http://'.$ded_ip.'&p2i_key='.$api_key), true);
			  }
        
        $this->view = new View("dashboard", "default");
		$this->view->base_uri = $this->base_uri;
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("vars", (object)$vars);
		$this->view->set("servicefields", $servicefields);
		$this->view->set("service_id", $service->id);
		$this->view->set("this", $this);
		$this->view->set("this->base_uri", $this->base_uri);
		$this->view->set("f", $f);
		$this->view->set("server_on_exe", $server_on_exe);
		$this->view->set("server_off_exe", $server_off_exe);
		$this->view->set("server_reset_exe", $server_reset_exe);
		$this->view->set("server_reset_exe", $server_reset_exe);
		$this->view->set("cpanel", $cpanel);
		$this->view->set("server", $server);
		$this->view->set("user", $user);
		$this->view->set("port", $port);
		$this->view->set("pass", $pass);
		$this->view->set("control_url", $control_url);
		$this->view->set("color_ded_sta", $color_ded_sta);
		$this->view->set("status_ded", $status_ded);

		$this->view->set("view", $this->view->view);
		$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
		return $this->view->fetch();
    }
    
    /**
     * SSH Web Consoles
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function ssh_console($package, $service, array $get=null, array $post=null, array $files=null) {
         $row = $this->getModuleRow($package->module_row);
         $out = '';
         $servicefields = array();
         
         foreach ($row->meta as $key=>$value ) {
              if (strpos($key, "service_field_name") !== false) {
                // Here we match the service field label with their key and value
                foreach($service->fields as $field) {
                  if($field->key == $value) {
                     $name = 'service_field_label_' . str_replace('service_field_name_', '', $key);
                     $servicefields[] = array("label" => $row->meta->$name,
                                              "key" => $value,
                                              "value" => $field->value); 
                  }
                }
              }
         }

         foreach($servicefields as $field) {
         	global $ded_ip;
		    //Get Variables
            if(preg_match("/ded_ips/i", $field['key'])){ $ded_ip = $field['value']; $ded_ip = explode("-", $ded_ip); $ded_ip = trim($ded_ip[0]); }
         }
         
        $this->view = new View("ssh", "default");
		$this->view->base_uri = $this->base_uri;
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));
		$this->view->set("vars", (object)$vars);
		$this->view->set("service_fields", $service_fields);
		$this->view->set("service_id", $service->id);
		$this->view->set("ded_ip", $ded_ip);

		$this->view->set("view", $this->view->view);
		$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
		return $this->view->fetch();
    }
    
    
    /**
     * FTP Web Client
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    
    public function ftp_client($package, $service, array $get=null, array $post=null, array $files=null) {
         $row = $this->getModuleRow($package->module_row);
         $servicefields = array();
         
        $this->view = new View("ftp", "default");
		$this->view->base_uri = $this->base_uri;
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("vars", (object)$vars);
		$this->view->set("service_fields", $service_fields);
		$this->view->set("service_id", $service->id);
		$this->view->set("this", $this);
		$this->view->set("this->base_uri", $this->base_uri);

		$this->view->set("view", $this->view->view);
		$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
		return $this->view->fetch();
    }
    
     /**
     * Server Statistics 
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function stats($package, $service, array $get=null, array $post=null, array $files=null) {
    	 set_time_limit(10);
         $row = $this->getModuleRow($package->module_row);
         $servicefields = array();
         foreach ($row->meta as $key=>$value ) {
              if (strpos($key, "service_field_name") !== false) {
                // Here we match the service field label with their key and value
                foreach($service->fields as $field) {
                  if($field->key == $value) {
                     $name = 'service_field_label_' . str_replace('service_field_name_', '', $key);
                     $servicefields[] = array("label" => $row->meta->$name,
                                              "key" => $value,
                                              "value" => $field->value); 
                  }
                }
              }
         }
         
	    //Get SSH Functions
		include('api/functions.php');
		
	    //Get Variables
		global $ded_ip; global $root_pass; global $control_url;
        foreach($servicefields as $field) {
            if(preg_match("/ded_ips/i", $field['key'])){ $ded_ip = $field['value']; $ded_ip = explode("-", $ded_ip); $ded_ip = trim($ded_ip[0]); }
            if(preg_match("/root_pass/i", $field['key'])){ $root_pass = $field['value']; }
        }
		
        //SSH Access Data
		$server = $ded_ip; $port = 22; $user = 'root'; $pass = $root_pass;                       	

    	//Get Info
		if(ItsSshActivated($server, $port, $user, $pass)){
    		$cpu = getCpuUsage($server, $port, $user, $pass);
    		$mem_total = getTotalMemory($server, $port, $user, $pass);
    		$mem_used = getUsedMemory($server, $port, $user, $pass);
    		$hdd_total = getTotalDisk($server, $port, $user, $pass);
    		$hdd_used = getUsedDisk($server, $port, $user, $pass);
    	  
        	$this->view = new View("stats", "default");
			$this->view->base_uri = $this->base_uri;
			// Load the helpers required for this view
			Loader::loadHelpers($this, array("Form", "Html"));

			$this->view->set("vars", (object)$vars);
			$this->view->set("service_fields", $service_fields);
			$this->view->set("service_id", $service->id);
			$this->view->set("this", $this);

			$this->view->set("cpu", $cpu);
			$this->view->set("mem_total", $mem_total);
			$this->view->set("mem_used", $mem_used);
			$this->view->set("hdd_total", $hdd_total);
			$this->view->set("hdd_used", $hdd_used);

			$this->view->set("view", $this->view->view);
			$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
			return $this->view->fetch();
		} else {
			return "<h4>".Language::_("UniversalServerModule.server_stats", true)."</h4><br><p>".Language::_("UniversalServerModule.!error.service_notice_edit.failed", true)."</p>";
		}
    }
    
    /**
     * Colocation
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function colocation($package, $service, array $get=null, array $post=null, array $files=null) {
         $row = $this->getModuleRow($package->module_row);
         $servicefields = array();
         
         foreach ($row->meta as $key=>$value ) {
              if (strpos($key, "service_field_name") !== false) {
                // Here we match the service field label with their key and value
                foreach($service->fields as $field) {
                  if($field->key == $value) {
                     $name = 'service_field_label_' . str_replace('service_field_name_', '', $key);
                     $servicefields[] = array("label" => $row->meta->$name,
                                              "key" => $value,
                                              "value" => $field->value); 
                  }
                }
              }
         }

         foreach($servicefields as $field) {   
         	global $ded_hostname, $colo_u, $colo_pos, $colo_weight, $colo_datacenter, $colo_floor, $colo_room;
            if(preg_match("/ded_hostname/i", $field['key'])){ $ded_hostname = $field['value']; }
            if(preg_match("/colo_u/i", $field['key'])){ $colo_u = $field['value']; }
            if(preg_match("/colo_pos/i", $field['key'])){ $colo_pos = $field['value']; }
            if(preg_match("/colo_weight/i", $field['key'])){ $colo_weight = floatval($field['value']); }
            if(preg_match("/colo_datacenter/i", $field['key'])){ $colo_datacenter = $field['value']; }
            if(preg_match("/colo_floor/i", $field['key'])){ $colo_floor = $field['value']; }
            if(preg_match("/colo_room/i", $field['key'])){ $colo_room = $field['value']; }
            if(preg_match("/colo_rack/i", $field['key'])){ $colo_rack = $field['value']; }
         }
            
        $this->view = new View("colocation", "default");
		$this->view->base_uri = $this->base_uri;
		
		// Load the helpers required for this view
		Loader::loadHelpers($this, array("Form", "Html"));

		$this->view->set("vars", (object)$vars);
		$this->view->set("service_fields", $service_fields);
		$this->view->set("service_id", $service->id);
		$this->view->set("ded_hostname", $ded_hostname);
		$this->view->set("colo_u", $colo_u);
		$this->view->set("colo_pos", $colo_pos);
		$this->view->set("colo_weight", $colo_weight);
		$this->view->set("colo_datacenter", $colo_datacenter);
		$this->view->set("colo_floor", $colo_floor);
		$this->view->set("colo_room", $colo_room);
		$this->view->set("colo_rack", $colo_rack);
		$this->view->set("colo_rack", $colo_rack);

		$this->view->set("view", $this->view->view);
		$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
		return $this->view->fetch();
    }
    
     /**
     * Server Options
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function serveroptions($package, $service, array $get=null, array $post=null, array $files=null) {		
         $row = $this->getModuleRow($package->module_row);
         $servicefields = array();
         foreach ($row->meta as $key=>$value ) {
              if (strpos($key, "service_field_name") !== false) {
                // Here we match the service field label with their key and value
                foreach($service->fields as $field) {
                  if($field->key == $value) {
                     $name = 'service_field_label_' . str_replace('service_field_name_', '', $key);
                     $servicefields[] = array("label" => $row->meta->$name,
                                              "key" => $value,
                                              "value" => $field->value); 
                  }
                }
              }
         }
         
	    //Get SSH Functions
		include('api/functions.php');
		
	    //Get Variables
		global $ded_ip; global $root_pass; global $ded_os;
        foreach($servicefields as $field) {
            if(preg_match("/ded_ips/i", $field['key'])){ $ded_ip = $field['value']; $ded_ip = explode("-", $ded_ip); $ded_ip = trim($ded_ip[0]); }
            if(preg_match("/root_pass/i", $field['key'])){ $root_pass = $field['value']; }
            if(preg_match("/ded_os/i", $field['key'])){ $ded_os = $field['value']; }
        }
		
        //SSH Access Data
		$server = $ded_ip; $port = 22; $user = 'root'; $pass = $root_pass;               
		if(preg_match("/centos/i", $ded_os) || preg_match("/fedora/i", $ded_os) || preg_match("/scientific/i", $ded_os) || preg_match("/cloudlinux/i", $ded_os)){ $os = 'rhel'; } else { if(preg_match("/freebsd/i", $ded_os)){ $os = 'bsd'; } else { $os = 'debian'; } }
        	
    	//Get Info
		if(ItsSshActivated($server, $port, $user, $pass)){	
			//Update Software
			if(isset($_GET['update'])){ $result = upgradeSoftwareSSH($server, $port, $user, $pass, $os); }
			
			//Clean Software
			if(isset($_GET['clean'])){ $result = cleanSoftwareSSH($server, $port, $user, $pass, $os); }
			
			//Change Hostname or Pass
			if(!empty($post)){
				if($post['password'] == $post['confirm_password'] && !empty($post['password'])){
					$result = changeServerPasswordSSH($server, $port, $user, $pass, $post['password']);
					if(!isset($this->Record))
					Loader::loadComponents($this, array("Record"));
					$this->Record->query("UPDATE `service_fields` SET `value` = '" . $this->ModuleManager->systemEncrypt($post['password']) . "' WHERE `key` = 'root_pass' AND `service_id` =" . $service->id);
				}
				if(!empty($post['hostname'])){
					$result = changeServerHostnameSSH($server, $port, $user, $pass, $post['hostname']);
					if(!isset($this->Record))
					Loader::loadComponents($this, array("Record"));
					$this->Record->query("UPDATE `service_fields` SET `value` = '" . $post['hostname']. "' WHERE `key` = 'ded_hostname' AND `service_id` =" . $service->id);
				}
			}
			
        	$this->view = new View("server_options", "default");
			$this->view->base_uri = $this->base_uri;
			
			// Load the helpers required for this view
			Loader::loadHelpers($this, array("Form", "Html"));
			$this->view->set("vars", (object)$vars);
			$this->view->set("service_fields", $service_fields);
			$this->view->set("this", $this);
			$this->view->set("result", $result);
			$this->view->set("view", $this->view->view);
			
			$this->view->setDefaultView("components" . DS . "modules" . DS . "universal_server_module" . DS);
			return $this->view->fetch();
		} else {
			return "<h4>".Language::_("UniversalServerModule.add_row.service_title", true)."</h4><br><p>".Language::_("UniversalServerModule.!error.service_notice_edit.failed", true)."</p>";
		}
    }
    
         /**
         * Returns all tabs to display to a client when managing a service whose
         * package uses this module
         *
         * @param stdClass $package A stdClass object representing the selected package
         * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
         */
        public function getClientTabs($package) {
        	$var_dump = strtolower(print_r($package, true));
            if((preg_match("/dedicated/i", $var_dump) && preg_match("/server/i", $var_dump) && !preg_match("/colocation/i", $var_dump)) || (preg_match("/dedicated/i", $var_dump) && preg_match("/servers/i", $var_dump) && !preg_match("/colocation/i", $var_dump))){
                return array(
                    'moreinfo' => array('name' => Language::_("UniversalServerModule.moreinfo", true) , 'icon' => "fa fa-gears"),
                    'ssh_console' => array('name' => Language::_("UniversalServerModule.ssh_console", true), 'icon' => "fa fa-terminal"),
                    'ftp_client' => array('name' => Language::_("UniversalServerModule.ftp_client", true), 'icon' => "fa fa-dashboard"),
                    'stats' => array('name' => Language::_("UniversalServerModule.server_stats", true), 'icon' => "fa fa-area-chart"),
                    'serveroptions' => array('name' => Language::_("UniversalServerModule.add_row.service_title", true), 'icon' => "fa fa-cloud")
                );
            } else {
                return array(
                	'moreinfo' => array('name' => Language::_("UniversalServerModule.moreinfo", true) , 'icon' => "fa fa-gears"),
                	'ssh_console' => array('name' => Language::_("UniversalServerModule.ssh_console", true), 'icon' => "fa fa-terminal"),
                    'ftp_client' => array('name' => Language::_("UniversalServerModule.ftp_client", true), 'icon' => "fa fa-dashboard"),
                 	'stats' => array('name' => Language::_("UniversalServerModule.server_stats", true), 'icon' => "fa fa-area-chart"),
                 	'colocation' => array('name' => Language::_("UniversalServerModule.colocation", true), 'icon' => "fa fa-building")
                );
            }
        }
        
	/**
	 * Verifies whether or not the givne str is a URL
	 *
	 * @param string $str A string
	 * @return boolean True if $str is a URL, false otherwise
	 */
	private function isUrl($str) {
		return preg_match("#^\S+://\S+\.\S+.+$#", $str);
	}
}
?>