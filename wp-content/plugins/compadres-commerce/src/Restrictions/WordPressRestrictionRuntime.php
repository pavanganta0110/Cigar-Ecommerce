<?php

declare(strict_types=1);

namespace Compadres\Commerce\Restrictions;

use DateTimeImmutable;
use DateTimeZone;

final class WordPressRestrictionRuntime {

	public function repository(): RestrictionRepository {
		global $wpdb;
		return new WordPressRestrictionRepository( $wpdb, new RestrictionRuleHydrator() );
	}

	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
