<?php

declare(strict_types=1);

namespace Compadres\Commerce\Tests\Unit;

use Compadres\Commerce\AgeVerification\VerificationRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VerificationRequestTest extends TestCase {

	public function testDateOfBirthIsOmittedWhenProviderDoesNotRequireIt(): void {
		$request = VerificationRequest::fromCheckout(
			array(
				'billing_first_name'      => ' Ada ',
				'compadres_date_of_birth' => '1990-01-02',
			),
			false
		);

		self::assertSame( 'Ada', $request->providerData()['first_name'] );
		self::assertArrayNotHasKey( 'date_of_birth', $request->providerData() );
	}

	public function testRequiredDateOfBirthMustBeARealPastCalendarDate(): void {
		$this->expectException( InvalidArgumentException::class );
		VerificationRequest::fromCheckout( array( 'compadres_date_of_birth' => '2025-02-30' ), true );
	}
}
