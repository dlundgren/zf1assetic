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
class HeadScript
	extends \Zend_View_Helper_HeadScript
{
	/**
	 * Is the script provided valid?
	 *
	 * @param  mixed $value
	 * @return bool
	 */
	protected function _isValid($value)
	{
		if ((!$value instanceof \stdClass)
			|| !isset($value->type)
			|| (!isset($value->source) && !isset($value->attributes)))
		{
			return false;
		}

		// only perform this on javascript with src attribute
		if ($value->type === 'text/javascript' && isset($value->attributes['src']) && ($value->attributes['src'][0] === '@')) {
			// Our Assetic Overlord will handle these for us
			$asset = $this->view->assets(Manager::TYPE_SCRIPT, $value->attributes['src']);
			!empty($asset) && ($value->attributes['src'] = $asset);
		}

		return true;
	}
}