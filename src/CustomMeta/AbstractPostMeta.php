<?php

/**
 * Base class for native (non-ACF) post meta registration.
 *
 * Eightshift-libs only ships an ACF abstraction; this registers meta with
 * `register_post_meta` so it is exposed to the block editor through the REST
 * API (`show_in_rest` + schema) and edited by the theme's editor sidebar
 * (assets/editor/application-blocks-editor.js).
 *
 * @package GoingGreenTransportTheme\CustomMeta
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\CustomMeta;

use GoingGreenTransportThemeVendor\EightshiftLibs\Services\ServiceInterface;

/**
 * Class AbstractPostMeta
 */
abstract class AbstractPostMeta implements ServiceInterface
{
	/**
	 * Meta key prefix shared by all theme meta.
	 *
	 * @var string
	 */
	public const PREFIX = 'ggt_';

	/**
	 * Register all the hooks.
	 *
	 * @return void
	 */
	public function register(): void
	{
		\add_action('init', [$this, 'registerMeta']);
	}

	/**
	 * Post type the meta belongs to.
	 *
	 * @return string
	 */
	abstract protected function getPostTypeSlug(): string;

	/**
	 * Meta definitions: key (without prefix) => register_post_meta args.
	 * Use the static helpers below to build the args.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	abstract protected function getFields(): array;

	/**
	 * Register every field.
	 *
	 * @return void
	 */
	public function registerMeta(): void
	{
		foreach ($this->getFields() as $key => $args) {
			\register_post_meta(
				$this->getPostTypeSlug(),
				self::PREFIX . $key,
				\array_merge(
					[
						'single' => true,
						'auth_callback' => static fn(): bool => \current_user_can('edit_posts'),
					],
					$args
				)
			);
		}
	}

	/**
	 * Plain string field.
	 *
	 * @param string $sanitize Sanitize callback.
	 *
	 * @return array<string, mixed>
	 */
	protected static function stringField(string $sanitize = 'sanitize_text_field'): array
	{
		return [
			'type' => 'string',
			'default' => '',
			'sanitize_callback' => $sanitize,
			'show_in_rest' => true,
		];
	}

	/**
	 * URL field.
	 *
	 * @return array<string, mixed>
	 */
	protected static function urlField(): array
	{
		return self::stringField('esc_url_raw');
	}

	/**
	 * Integer field (attachment IDs, sizes).
	 *
	 * @return array<string, mixed>
	 */
	protected static function integerField(): array
	{
		return [
			'type' => 'integer',
			'default' => 0,
			'sanitize_callback' => 'absint',
			'show_in_rest' => true,
		];
	}

	/**
	 * Single object field with a fixed property schema.
	 *
	 * @param array<string, string> $properties name => json type.
	 *
	 * @return array<string, mixed>
	 */
	protected static function objectField(array $properties): array
	{
		return [
			'type' => 'object',
			'default' => [],
			'sanitize_callback' => static fn($value): array => self::sanitizeObject((array) $value, $properties),
			'show_in_rest' => [
				'schema' => [
					'type' => 'object',
					'properties' => self::schemaProperties($properties),
				],
			],
		];
	}

	/**
	 * Repeater: list of objects sharing one property schema.
	 *
	 * @param array<string, string> $properties name => json type.
	 *
	 * @return array<string, mixed>
	 */
	protected static function objectListField(array $properties): array
	{
		return [
			'type' => 'array',
			'default' => [],
			'sanitize_callback' => static fn($value): array => \array_values(\array_map(
				static fn($row): array => self::sanitizeObject((array) $row, $properties),
				\is_array($value) ? $value : []
			)),
			'show_in_rest' => [
				'schema' => [
					'type' => 'array',
					'items' => [
						'type' => 'object',
						'properties' => self::schemaProperties($properties),
					],
				],
			],
		];
	}

	/**
	 * Array-of-strings meta field (e.g. nearby city names).
	 *
	 * @return array<string, mixed>
	 */
	protected static function stringListField(): array
	{
		return [
			'type' => 'array',
			'default' => [],
			'sanitize_callback' => static fn($value): array => \array_values(\array_filter(\array_map('sanitize_text_field', \is_array($value) ? $value : []))),
			'show_in_rest' => [
				'schema' => [
					'type' => 'array',
					'items' => ['type' => 'string'],
				],
			],
		];
	}

	/**
	 * Float meta field (coordinates).
	 *
	 * @return array<string, mixed>
	 */
	protected static function numberField(): array
	{
		return [
			'type' => 'number',
			'default' => 0,
			'sanitize_callback' => static fn($value): float => (float) $value,
			'show_in_rest' => true,
		];
	}

	/**
	 * List of integers (e.g. related post IDs).
	 *
	 * @return array<string, mixed>
	 */
	protected static function integerListField(): array
	{
		return [
			'type' => 'array',
			'default' => [],
			'sanitize_callback' => static fn($value): array => \array_values(\array_map('absint', \is_array($value) ? $value : [])),
			'show_in_rest' => [
				'schema' => [
					'type' => 'array',
					'items' => ['type' => 'integer'],
				],
			],
		];
	}

	/**
	 * Build a REST schema `properties` map.
	 *
	 * @param array<string, string> $properties name => json type.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function schemaProperties(array $properties): array
	{
		$out = [];
		foreach ($properties as $name => $type) {
			$out[$name] = ['type' => $type];
		}
		return $out;
	}

	/**
	 * Sanitize one object against its property schema.
	 *
	 * @param array<string, mixed>  $value      Raw object.
	 * @param array<string, string> $properties name => json type.
	 *
	 * @return array<string, mixed>
	 */
	private static function sanitizeObject(array $value, array $properties): array
	{
		$out = [];
		foreach ($properties as $name => $type) {
			$raw = $value[$name] ?? null;
			$out[$name] = match ($type) {
				'integer' => \absint($raw),
				'number' => (float) $raw,
				'boolean' => (bool) $raw,
				default => \sanitize_textarea_field((string) $raw),
			};
		}
		return $out;
	}
}
