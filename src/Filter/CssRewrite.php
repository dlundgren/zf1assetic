<?php
/**
 * Zend Framework 1 Assetic Extension
 *
 * @author  David Lundgren
 * @license MIT
 */
namespace ZfAssetic\Filter;

use Assetic\Asset\AssetInterface;
use Assetic\Filter\BaseCssFilter;
use Zend_Filter_Interface;

/**
 * Fixes relative CSS urls.
 *
 * @todo investigate using another name instead of of Filter for the "file finder" ^ dlundgren
 * @author David Lundgren
 */
class CssRewrite
	extends BaseCssFilter
{
	/**
	 * @var Zend_Filter_Interface The filter used to find files
	 */
	private $fileFilter;

	/**
	 * Constructor
	 *
	 * @param Zend_Filter_Interface $fileFilter
	 */
	public function __construct(Zend_Filter_Interface $fileFilter)
	{
		$this->fileFilter = $fileFilter;
	}

	/**
	 * Required by the interface
	 *
	 * @param AssetInterface $asset
	 */
	public function filterLoad(AssetInterface $asset)
	{
	}

	/**
	 * Dumps the asset information
	 *
	 * @param AssetInterface $asset
	 */
	public function filterDump(AssetInterface $asset)
	{
		/**
		 * @NOTE 5.3 has limitations where it can't use $this in Closures, so this works around that ^ dlundgren
		 */
		$fileFilter = $this->fileFilter;
		$callback     = function ($matches) use ($fileFilter) {
			// don't touch root relative or others
			if (false !== strpos($matches['url'], '://') || 0 === strpos($matches['url'], '//') ||
				0 === strpos($matches['url'], 'data:') ||
				(isset($matches['url'][0]) && '/' == $matches['url'][0])
			) {
				// absolute or protocol-relative or data uri
				return $matches[0];
			}

			$resolvedUrl = $fileFilter->filter($matches['url']);

			return ($resolvedUrl === $matches['url']) ? $matches[0] : str_replace($matches['url'], $resolvedUrl, $matches[0]);
		};

		// we only handle url rewriting currently
		$content = $this->filterUrls($asset->getContent(), $callback);

		$asset->setContent($content);
	}
}
