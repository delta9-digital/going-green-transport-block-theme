<?php

/**
 * Block-editor enqueue: the post-meta sidebar panels.
 *
 * Loads the `applicationBlocksEditor` build entry (assets/editor/) on every
 * block-editor screen; the script itself decides which panel to render from
 * the current post type.
 *
 * @package GoingGreenTransportTheme\Enqueue\Editor
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\Enqueue\Editor;

use GoingGreenTransportTheme\Config\Config;
use GoingGreenTransportThemeVendor\EightshiftLibs\Cache\ManifestCacheInterface;
use GoingGreenTransportThemeVendor\EightshiftLibs\Enqueue\AbstractAssets;

/**
 * Class EnqueueEditor
 */
class EnqueueEditor extends AbstractAssets
{
	/**
	 * Built asset keys (see public/manifest.json).
	 *
	 * @var string
	 */
	public const EDITOR_SCRIPT_URI = 'applicationBlocksEditor.js';
	public const EDITOR_STYLE_URI = 'applicationBlocksEditor.css';

	/**
	 * Create a new instance.
	 *
	 * @param ManifestCacheInterface $manifestCache Inject manifest cache which holds data about assets from manifest.json.
	 */
	public function __construct(ManifestCacheInterface $manifestCache)
	{
		$this->manifestCache = $manifestCache;
	}

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
	}

	/**
	 * Enqueue the sidebar script + styles in the block editor.
	 *
	 * @return void
	 */
	public function enqueueEditorAssets(): void
	{
		$prefix = $this->getAssetsPrefix();

		$scriptHandle = "{$prefix}-editor-meta";
		\wp_register_script(
			$scriptHandle,
			$this->setAssetsItem(static::EDITOR_SCRIPT_URI),
			[
				'wp-plugins',
				'wp-editor',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-core-data',
				'wp-i18n',
				'wp-block-editor',
			],
			$this->getAssetsVersion(),
			true
		);
		\wp_enqueue_script($scriptHandle);

		$styleHandle = "{$prefix}-editor-meta-style";
		\wp_register_style(
			$styleHandle,
			$this->setAssetsItem(static::EDITOR_STYLE_URI),
			['wp-components'],
			$this->getAssetsVersion()
		);
		\wp_enqueue_style($styleHandle);
	}

	/**
	 * Method that returns assets name used to prefix asset handlers.
	 *
	 * @return string
	 */
	public function getAssetsPrefix(): string
	{
		return Config::getProjectName();
	}

	/**
	 * Method that returns assets version for versioning asset handlers.
	 *
	 * @return string
	 */
	public function getAssetsVersion(): string
	{
		return Config::getProjectVersion();
	}
}
