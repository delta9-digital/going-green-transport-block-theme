<?php

/**
 * Location meta (keys prefixed ggt_): city name as shown in copy, coordinates for maps, and the
 * nearby towns listed under "Service area". The region and its zone come from the
 * `region` taxonomy.
 *
 * @package GoingGreenTransportTheme\CustomMeta
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\CustomMeta;

use GoingGreenTransportTheme\CustomPostType\LocationPostType;

/**
 * Class LocationMeta
 */
class LocationMeta extends AbstractPostMeta
{
	/**
	 * Post type the fields belong to.
	 *
	 * @return string
	 */
	protected function getPostTypeSlug(): string
	{
		return LocationPostType::POST_TYPE_SLUG;
	}

	/**
	 * Field definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function getFields(): array
	{
		return [
			'city' => self::stringField(),
			'lat' => self::numberField(),
			'lng' => self::numberField(),
			'nearby' => self::stringListField(),
		];
	}
}
