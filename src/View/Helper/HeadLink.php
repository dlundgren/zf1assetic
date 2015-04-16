<?php
/**
 * Zend Framework 1 Assetic Extension
 *
 * @author  David Lundgren
 * @license MIT
 */
namespace ZfAssetic\View\Helper;

use ZfAssetic\Manager;

/**
 * Assetic usage from Zend Framework view helpers
 *
 * @author David Lundgren
 */
class HeadLink
	extends \Zend_View_Helper_HeadLink
{
	/**
	 * Check if value is valid
	 *
	 * @param  mixed $value
	 * @return boolean
	 */
	protected function _isValid($value)
	{
		if (!$value instanceof \stdClass) {
			return false;
		}

		if (isset($value->href) && $value->href[0] === '@') {
			$asset = $this->view->assets(Manager::TYPE_STYLE, $value->href);
			!empty($asset) && ($value->href = $asset);
		}

		$vars         = get_object_vars($value);
		$keys         = array_keys($vars);
		$intersection = array_intersect($this->_itemKeys, $keys);
		if (empty($intersection)) {
			return false;
		}

		return true;
	}
}