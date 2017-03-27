<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * EE Learning Tools Integration Module Control Panel File
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Module
 * @author		Paul Sijpkes
 * @link		http://sijpkes.site11.com
 */

class Lti_peer_assessment_mcp {

	public $return_data;

	private $_base_url;
	private $module_name = "Lti_peer_assessment";
	private $perpage = 10;

	private $message = false;
	private $oauth_consumer_key = false;
	private $secret = false;
	private $context_id = false;
	private $name = false;
	private $idvalue = false;

	/**
	 * Constructor
	 */
	public function __construct()
	{

		$this->_base_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module='.$this->module_name;

		/*ee()->cp->set_right_nav(array(
        'add_consumer'  => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'
            .AMP.'module='.$this->module_name.AMP.'method=add_consumer'
    	));*/
	}

	// ----------------------------------------------------------------

	/**
	 * Index Function
	 *
	 * @return 	void
	 */
	public function index()
	{

	}


	/**
	 * Start on your custom code here...
	 */

}
/* End of file mcp.learning_tools_integration.php */
/* Location: /system/expressionengine/third_party/learning_tools_integration/mcp.learning_tools_integration.php */
