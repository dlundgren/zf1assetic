<?php
/**
 * Zend Framework 1 Assetic Extension
 *
 * @author  David Lundgren
 * @license MIT
 */
namespace ZfAssetic\View\Helper;

/**
 * View Helper for obtaining assets
 *
 * @author  David Lundgren
 * @package ZfAssetic\View\Helper
 */
class Assets
{
	/**
	 * @var \ZfAssetic\Manager
	 */
	private $assetManager;

	/**
	 * Sets the asset service that the views will use to retrieve the assets
	 *
	 * @param \ZfAssetic\Manager $assetManager
	 * @return $this
	 */
	public function setAssetService(\ZfAssetic\Manager $assetManager)
	{
		$this->assetManager = $assetManager;

		return $this;
	}

	/**
	 * Returns the url
	 *
	 * @param string $type  The type of the asset to retrieve
	 * @param string $asset The name of the asset in the system
	 * @return string
	 */
	public function assets($type = null, $asset = null)
	{
		if (empty($type) && empty($asset)) {
			return $this;
		}

		$asset[0] === '@' && $asset = substr($asset, 1);

		return $this->assetManager ? ($this->assetManager->getUrl("{$type}_{$asset}") ?: '') : '';
	}
}