<?php
/**
 * Zend Framework 1 Assetic Extension
 *
 * @author  David Lundgren
 * @license MIT
 */
namespace ZfAssetic;

use Assetic\Asset\AssetCache;
use Assetic\Asset\AssetCollection;
use Assetic\Asset\AssetInterface;
use Assetic\Asset\AssetReference;
use Assetic\Asset\FileAsset;
use Assetic\Asset\GlobAsset;
use Assetic\AssetManager;
use Assetic\AssetWriter;
use Assetic\Filter\HashableInterface;
use Assetic\FilterManager;

/**
 * Manager for Assets using Assetic
 *
 * @package ZfAssetic
 */
class Manager
{
	/**
	 * Constants for the type of assets that are supported
	 */
	const TYPE_SCRIPT = 'js';
	const TYPE_STYLE  = 'css';
	const TYPE_FONT   = 'font';
	const TYPE_MEDIA  = 'media';

	/**
	 * Constants for the path names that the assets are stored under
	 */
	const PATH_SCRIPT = 'js';
	const PATH_STYLE  = 'css';
	const PATH_FONT   = 'fonts';
	const PATH_MEDIA  = 'media';

	/**
	 * @var array Map of types to their paths
	 */
	protected $typePathMap = array(
		self::TYPE_SCRIPT => self::PATH_SCRIPT,
		self::TYPE_STYLE  => self::PATH_STYLE,
		self::TYPE_FONT   => self::PATH_FONT,
		self::TYPE_MEDIA  => self::PATH_MEDIA,
	);

	/**
	 * @var \Assetic\AssetManager
	 */
	protected $assetManager;

	/**
	 * @var string
	 */
	protected $resourceDirectory;

	/**
	 * @var string
	 */
	protected $assetDirectory;

	/**
	 * @var \Zend_Cache_Core
	 */
	protected $zendCache;

	/**
	 * @var \Assetic\Cache\CacheInterface
	 */
	protected $asseticCache;

	/**
	 * @var \Assetic\FilterManager
	 */
	protected $filterManager;

	/**
	 * @var array
	 */
	protected $filters = array();

	/**
	 * @var string url path to the assets from the /
	 */
	protected $assetUrl;

	/**
	 * Constructor
	 *
	 * @param string                 $resourceDirectory
	 * @param string                 $assetDirectory
	 * @param string                 $assetUrl
	 * @param \Assetic\AssetManager  $assetManager
	 * @param \Assetic\FilterManager $filterManager
	 */
	public function __construct($resourceDirectory, $assetDirectory, $assetUrl, AssetManager $assetManager,
								FilterManager $filterManager)
	{
		$this->resourceDirectory = $resourceDirectory;
		$this->assetDirectory    = $assetDirectory;
		$this->assetUrl          = $assetUrl;
		$this->assetManager      = $assetManager;
		$this->filterManager     = $filterManager;
	}

	/**
	 * Sets the zend cache to use for local storage of data
	 *
	 * @param \Zend_Cache_Core $cache
	 */
	public function setZendCache(\Zend_Cache_Core $cache)
	{
		$this->zendCache = $cache;
	}

	/**
	 * Sets the assetic AssetCache
	 *
	 * @param AssetCache $cache
	 */
	public function setAsseticCache(AssetCache $cache)
	{
		$this->asseticCache = $cache;
	}

	/**
	 * Adds a filter to the system
	 *
	 * @param string $name
	 * @param string $filter
	 * @param string $type
	 * @throws \InvalidArgumentException
	 */
	public function addFilter($name, $filter, $type)
	{
		if (is_string($filter)) {
			$class = "Assetic\\Filter\\$filter";
			if (!class_exists($class)) {
				throw new \InvalidArgumentException("could not find $filter");
			}
			$filter = new $class();
		}
		$name                   = $this->formatName($name, $type);
		$this->filters[$type][] = $name;
		$this->filterManager->set($name, $filter);
	}

	/**
	 * Returns the url for the given named asset
	 *
	 * @param $name
	 * @return false|mixed|string
	 */
	public function getUrl($name)
	{
		if ($this->zendCache && $url = $this->zendCache->load($name)) {
			// the asset already exists so just return the asset itself
			return $url;
		}

		$type         = array_shift(explode('_', $name, 2));
		$asseticAsset = $this->assetManager->get($name);

		if ($this->asseticCache) {
			$cache = new AssetCache($asseticAsset, $this->asseticCache);
			$cache->dump();
			$file = $this->getAsseticCacheKey($asseticAsset, 'dump');
		}
		else {
			$file = sha1($name) . ".$type";
			$asseticAsset->setTargetPath("$type/$file");
			$writer = new AssetWriter($this->assetDirectory);
			$writer->writeAsset($asseticAsset);
		}

		$url = "{$this->assetUrl}/$type/$file";
		$this->zendCache && $this->zendCache->save($url, $name);

		return $url;
	}

	/**
	 * Adds a script collection
	 *
	 * This is used when a group of scripts comprise one script
	 *
	 * Filters are added to the collection and not the individual resources (references MAY have their own filters)
	 *
	 * @param       $name
	 * @param array $resources
	 */
	public function addScriptCollection($name, array $resources)
	{
		$res = array();
		foreach ($resources as $resource) {
			if ($resource[0] === '@') {
				$res[] = $this->newAssetReference($this->formatName($resource, self::TYPE_SCRIPT));
			}
			elseif (strpos($resource, '*') === false) {
				$res[] = $this->newFileAsset($this->formatResourcePath($resource, self::TYPE_SCRIPT));
			}
			else {
				$res[] = $this->newGlobAsset($this->formatResourcePath($resource, self::TYPE_SCRIPT));
			}
		}

		return $this->registerAsset($this->formatName($name, self::TYPE_SCRIPT), $this->newAssetCollection($res, self::TYPE_SCRIPT));
	}

	/**
	 * Adds a script glob asset
	 *
	 * @param $name
	 * @param $pattern
	 * @return \Assetic\Asset\AssetInterface
	 */
	public function addScriptGlob($name, $pattern)
	{
		return $this->registerAsset($this->formatName($name, self::TYPE_SCRIPT), $this->newGlobAsset($this->formatResourcePath($pattern, self::PATH_SCRIPT), self::PATH_SCRIPT));
	}

	/**
	 * Adds a script file asset
	 *
	 * @param $name
	 * @param $file
	 * @return \Assetic\Asset\AssetInterface
	 */
	public function addScriptFile($name, $file)
	{
		return $this->registerAsset($this->formatName($name, self::TYPE_SCRIPT), $this->newFileAsset($this->formatResourcePath($file, self::PATH_SCRIPT), self::PATH_SCRIPT));
	}

	/**
	 * Adds a script reference
	 *
	 * @param $name
	 * @param $reference
	 * @return \Assetic\Asset\AssetInterface
	 */
	public function addScriptReference($name, $reference)
	{
		return $this->registerAsset($this->formatName($name, self::TYPE_SCRIPT), $this->newAssetReference($reference));
	}

	/**
	 * Adds a style collection
	 *
	 * This is used when a group of style comprise one style
	 *
	 * Filters are added to the collection and not the individual resources (references MAY have their own filters)
	 *
	 * @param       $name
	 * @param array $resources
	 * @return \Assetic\Asset\AssetInterface
	 */
	public function addStyleCollection($name, array $resources)
	{
		$res = array();
		foreach ($resources as $resource) {
			if ($resource[0] === '@') {
				$res[] = $this->newAssetReference($this->formatName($resource, self::TYPE_STYLE));
			}
			elseif (strpos($resource, '*') === false) {
				$res[] = $this->newFileAsset($this->formatResourcePath($resource, self::TYPE_STYLE));
			}
			else {
				$res[] = $this->newGlobAsset($this->formatResourcePath($resource, self::TYPE_STYLE));
			}
		}

		return $this->registerAsset($this->formatName($name, self::TYPE_STYLE), $this->newAssetCollection($res, self::TYPE_STYLE));
	}

	/**
	 * Adds a style glob asset
	 *
	 * @param $name
	 * @param $pattern
	 * @return AssetInterface
	 */
	public function addStyleGlob($name, $pattern)
	{
		return $this->registerAsset($this->formatName($name, self::TYPE_STYLE), $this->newGlobAsset($this->formatResourcePath($pattern, self::PATH_STYLE), self::PATH_STYLE));
	}

	/**
	 * Adds a style file asset
	 *
	 * @param $name
	 * @param $file
	 * @return \Assetic\Asset\AssetInterface
	 */
	public function addStyleFile($name, $file)
	{
		return $this->registerAsset($this->formatName($name, self::TYPE_STYLE), $this->newFileAsset($this->formatResourcePath($file, self::PATH_STYLE), self::PATH_STYLE));
	}

	/**
	 * Adds a style reference
	 *
	 * @param $name
	 * @param $reference
	 */
	public function addStyleReference($name, $reference)
	{
		$this->registerAsset($this->formatName($name, self::TYPE_STYLE), $this->newAssetReference($reference));
		// we do not return the asset in this case as the it should not be modified
	}

	/**
	 * Adds a font file asset
	 *
	 * @param string $name
	 * @param string $file
	 */
	public function addFontFile($name, $file)
	{
		$this->registerAsset($this->formatName($name, self::TYPE_FONT), $this->newFileAsset($this->formatResourcePath($file, self::PATH_FONT), self::PATH_FONT));
	}

	/**
	 * Adds a font reference
	 *
	 * @param $name
	 * @param $reference
	 */
	public function addFontReference($name, $reference)
	{
		$this->registerAsset($this->formatName($name, self::TYPE_FONT), $this->newAssetReference($reference));
	}

	/**
	 * Adds a media file asset
	 *
	 * @param string $name
	 * @param string $file
	 */
	public function addMediaFile($name, $file)
	{
		$this->registerAsset($this->formatName($name, self::TYPE_MEDIA), $this->newFileAsset($this->formatResourcePath($file, self::PATH_MEDIA), self::PATH_MEDIA));
	}

	/**
	 * Adds a media reference
	 *
	 * @param $name
	 * @param $reference
	 */
	public function addMediaReference($name, $reference)
	{
		$this->registerAsset($this->formatName($name, self::TYPE_MEDIA), $this->newAssetReference($reference));
	}

	/**
	 * Returns the formatted name for the type of asset
	 *
	 * @param string $name
	 * @param string $type
	 * @return string
	 */
	public function formatName($name, $type)
	{
		$name[0] === '@' && $name = substr($name, 1);

		return "{$type}_" !== substr($name, 0, strlen($type) + 1) ? "{$type}_{$name}" : $name;
	}

	/**
	 * Registers the asset
	 *
	 * This handles registration with the AssetCache and/or the AssetManager depending on how the system is currently
	 * configured.
	 *
	 * @param string         $name
	 * @param AssetInterface $asset
	 * @return AssetInterface
	 */
	private function registerAsset($name, AssetInterface $asset)
	{
		$this->assetManager->set($name, $asset);

		return $asset;
	}

	/**
	 * Returns the AssetCollection
	 *
	 * @param array       $assets
	 * @param null|string $filterType
	 * @return AssetCollection
	 * @throws \InvalidArgumentException
	 */
	private function newAssetCollection(array $assets, $filterType = null)
	{
		foreach ($assets as $asset) {
			if (!($asset instanceof AssetInterface)) {
				throw new \InvalidArgumentException("All of the assets passed to newAssetCollection must implement the AssetInterface");
			}
		}

		return new AssetCollection($assets, ($filterType && isset($this->filters[$filterType]) ? $this->filters[$filterType] : array()));
	}

	/**
	 * Returns an asset reference
	 *
	 * @param string $reference
	 * @return AssetReference
	 */
	private function newAssetReference($reference)
	{
		return new AssetReference($this->assetManager, $reference);
	}

	/**
	 * Returns the Glob asset
	 *
	 * @param string      $pattern
	 * @param null|string $filterType
	 * @return GlobAsset
	 */
	private function newGlobAsset($pattern, $filterType = null)
	{
		return new GlobAsset($pattern, ($filterType && isset($this->filters[$filterType]) ? $this->filters[$filterType] : array()));
	}

	/**
	 * Returns the FileAsset
	 *
	 * @param string      $file
	 * @param null|string $filterType
	 * @return FileAsset
	 */
	private function newFileAsset($file, $filterType = null)
	{
		return new FileAsset($file, ($filterType && isset($this->filters[$filterType]) ? $this->filters[$filterType] : array()));
	}

	/**
	 * Returns the full resource path
	 *
	 * @todo we need to allow url style paths ^ dlundgren
	 *
	 * @param string $resource
	 * @param string $path
	 * @return string
	 */
	private function formatResourcePath($resource, $path)
	{
		$resource[0] !== '/' && $resource = "{$this->resourceDirectory}/{$path}/{$resource}";

		return $resource;
	}

	/**
	 * This should not be, but the method is private in assetic and there is
	 * no easy way to retrieve this information
	 *
	 * @todo Should probably convert this to a filter ^ dlundgren
	 *
	 * @param AssetInterface $asset
	 * @param string         $salt
	 * @return string
	 */
	protected function getAsseticCacheKey(AssetInterface $asset, $salt = '')
	{
		$cacheKey = $asset->getSourceRoot();
		$cacheKey .= $asset->getSourcePath();
		$cacheKey .= $asset->getTargetPath();
		$cacheKey .= $asset->getLastModified();

		foreach ($asset->getFilters() as $filter) {
			$cacheKey .= $filter instanceof HashableInterface ? $filter->hash() : serialize($filter);
		}

		if ($values = $asset->getValues()) {
			asort($values);
			$cacheKey .= serialize($values);
		}

		return md5($cacheKey . $salt);
	}
}