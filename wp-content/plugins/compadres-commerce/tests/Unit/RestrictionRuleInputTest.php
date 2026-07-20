<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\Restrictions\RestrictionRuleInput;
use DateTimeZone;
use DomainException;
use PHPUnit\Framework\TestCase;

final class RestrictionRuleInputTest extends TestCase {

	public function testInputNormalizesRequiredRuleFieldsAndTargets(): void {
		$input = RestrictionRuleInput::fromArray(
			array(
				'name'            => ' Fictional launch rule ',
				'enabled'         => '1',
				'priority'        => '25',
				'country'         => 'us',
				'state'           => 'xy, yz',
				'city'            => ' Sample City ',
				'postal_code'     => '12345',
				'postal_prefix'   => '987',
				'product_id'      => '42,42',
				'category_id'     => '7',
				'brand_id'        => '9',
				'effective_at'    => '2026-07-19T09:00',
				'expires_at'      => '2026-07-20T09:00',
				'blocked_message' => ' This cart cannot be shipped to the selected destination. ',
				'notes'           => ' Internal fictional note. ',
				'source_name'     => ' Fictional legal review ',
				'source_url'      => 'https://example.test/review',
				'review_date'     => '2026-07-18',
			),
			new DateTimeZone( 'UTC' )
		);

		self::assertSame( 'Fictional launch rule', $input->rule()['name'] );
		self::assertSame( 'US', $input->rule()['country'] );
		self::assertSame( '2026-07-19 09:00:00', $input->rule()['effective_at'] );
		self::assertSame( array( 'XY', 'YZ' ), $input->targets()['state'] );
		self::assertSame( array( '42' ), $input->targets()['product_id'] );
	}

	public function testInputRejectsCredentialBearingSourceUrl(): void {
		$this->expectException( DomainException::class );
		RestrictionRuleInput::fromArray(
			array(
				'name'            => 'Fictional rule',
				'country'         => 'US',
				'state'           => 'XY',
				'effective_at'    => '2026-07-19T09:00',
				'blocked_message' => 'This cart cannot be shipped to the selected destination.',
				'source_url'      => 'https://user:secret@example.test/review',
			),
			new DateTimeZone( 'UTC' )
		);
	}
}
