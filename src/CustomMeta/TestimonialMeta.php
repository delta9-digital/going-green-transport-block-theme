<?php

/**
 * Testimonial meta (keys prefixed ggt_). The quote is the post content; these fields carry the
 * attribution and, for imported Google reviews, the source details.
 *
 * @package GoingGreenTransportTheme\CustomMeta
 */

declare(strict_types=1);

namespace GoingGreenTransportTheme\CustomMeta;

use GoingGreenTransportTheme\CustomPostType\TestimonialPostType;

/**
 * Class TestimonialMeta
 */
class TestimonialMeta extends AbstractPostMeta
{
	/**
	 * Post type the fields belong to.
	 *
	 * @return string
	 */
	protected function getPostTypeSlug(): string
	{
		return TestimonialPostType::POST_TYPE_SLUG;
	}

	/**
	 * Field definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function getFields(): array
	{
		return [
			'author' => self::stringField(),
			'role' => self::stringField(),
			'rating' => self::integerField(),
			'review_date' => self::stringField(),
			'source' => self::stringField(),
			'source_url' => self::urlField(),
			'source_id' => self::stringField(),
		];
	}
}
