<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DesignRequest;
use App\Enum\DesignRequestType;
use App\Enum\EnquiryTalk;
use PHPUnit\Framework\TestCase;

/**
 * "Begin the Conversation" enquiries saved as requests (Step 7c.2).
 */
final class DesignRequestEnquiryTest extends TestCase
{
    public function testAnEnquiryKeepsItsDetailsAndOnlyThePostcode(): void
    {
        $enquiry = DesignRequest::enquiry('Ada Lovelace', 'ada@example.test', null, EnquiryTalk::EMAIL, null, 'A tall hallway.', 'pinterest.com/ada/hall', 'IM1 1AA', 'IM');

        self::assertTrue($enquiry->isEnquiry());
        self::assertSame(DesignRequestType::ENQUIRY, $enquiry->getType());
        self::assertMatchesRegularExpression('/^LR-\d{6}-\d{4}$/', $enquiry->getRequestNumber());
        self::assertNull($enquiry->getPhone());
        self::assertSame('Email', $enquiry->getTalkLabel());
        self::assertSame('A tall hallway.', $enquiry->getDescription());
        self::assertSame('pinterest.com/ada/hall', $enquiry->getBoardLink());
        // The quote's delivery estimate reads the postcode; the address is asked on the quote page.
        self::assertSame('IM1 1AA', $enquiry->getPostcode());
        self::assertNull($enquiry->getAddressLine1());
        self::assertSame(['IM1 1AA', 'Isle of Man'], $enquiry->getAddressLines());
        self::assertFalse($enquiry->isGift());
    }

    public function testAReservationIsNotAnEnquiry(): void
    {
        $request = new DesignRequest('Ada Lovelace', 'ada@example.test', '07700 900123');

        self::assertFalse($request->isEnquiry());
        self::assertSame(DesignRequestType::RESERVATION, $request->getType());
        self::assertNull($request->getTalkLabel());
        self::assertSame([], $request->getAddressLines());
    }

    public function testAPhoneOrVideoCallNeedsThePhone(): void
    {
        self::assertFalse(EnquiryTalk::EMAIL->needsPhone());
        self::assertTrue(EnquiryTalk::PHONE->needsPhone());
        self::assertTrue(EnquiryTalk::VIDEO->needsPhone());
        self::assertSame('Video call', EnquiryTalk::VIDEO->label());
    }
}
