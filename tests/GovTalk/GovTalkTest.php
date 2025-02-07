<?php

/*
 * This file is part of the GovTalk package
 *
 * (c) Justin Busschau
 *
 * For the full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 */

namespace GovTalk;

use XMLWriter;

/**
 * The base class for all GovTalk tests
 */
class GovTalkTest extends TestCase
{
    /**
     * The gateway user ID
     */
    private $gatewayUserID;

    /**
     * The gateway user password
     */
    private $gatewayUserPassword;

    /**
     * The gateway address
     */
    private $gatewayURL;

    /** @var GovTalk */
    private $gtService;


    /**
     * Set up the test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        /**
         * The user name (Sender ID) and password given below are not valid for
         * either the live or any of the test/dev gateways. If you want to run
         * this test suite against actual servers, please contact the relevant
         * agency (HMRC / Companies House / etc.) and apply for valid credentials.
         */
        $this->gatewayUserID = 'XMLGatewayTestUserID';
        $this->gatewayUserPassword = 'XMLGatewayTestPassword';


        /**
         * By default messages to/from the gateway are mocked for this test suite.
         * Provide a legitimate test endpoint and remove all the calls to the
         * setMockHttpResponse function in order to test against actual gateways.
         */
        $this->gatewayURL = 'https://secure.dev.gateway.gov.uk/submission';

        /**
         * The following call sets up the service object used to interact with the
         * Government Gateway. Setting parameter 4 to null will force the test to
         * use the httpClient created on the fly within the GovTalk class and may
         * also effectively disable mockability.
         * Set parameter 5 to a valid path in order to log messages
         */
        $this->gtService = $this->setUpService();
    }

    public function testSettingTestFlag(): void
    {
        $this->assertTrue($this->gtService->setTestFlag(false));
        $this->assertFalse($this->gtService->getTestFlag());

        $this->assertFalse($this->gtService->setTestFlag('yes'));
        $this->assertFalse($this->gtService->getTestFlag());

        $this->assertTrue($this->gtService->setTestFlag(true));
        $this->assertTrue($this->gtService->getTestFlag());
    }

    public function testMessageAuthentication(): void
    {
        $this->assertTrue($this->gtService->setMessageAuthentication('alternative'));
        $this->assertEquals($this->gtService->getMessageAuthentication(), 'alternative');

        $this->assertFalse($this->gtService->setMessageAuthentication('someOther'));
        $this->assertEquals($this->gtService->getMessageAuthentication(), 'alternative');

        $this->assertTrue($this->gtService->setMessageAuthentication('clear'));
        $this->assertEquals($this->gtService->getMessageAuthentication(), 'clear');
    }

    public function testSettingEmailAddress(): void
    {
        $this->assertTrue($this->gtService->setSenderEmailAddress('jane@doeofjohn.com'));
        $this->assertEquals($this->gtService->getSenderEmailAddress(), 'jane@doeofjohn.com');

        $this->assertFalse($this->gtService->setSenderEmailAddress('joebloggscom'));
        $this->assertEquals($this->gtService->getSenderEmailAddress(), 'jane@doeofjohn.com');

        $this->assertTrue($this->gtService->setSenderEmailAddress('joe@bloggs.com'));
        $this->assertEquals($this->gtService->getSenderEmailAddress(), 'joe@bloggs.com');
    }

    public function testAddingMessageKey(): void
    {
        $this->assertTrue($this->gtService->addMessageKey('VATRegNo', '999900001'));
        $this->assertFalse($this->gtService->addMessageKey(array('VATRegNo'), '999900001'));
    }

    public function testDeletingMessageKey(): void
    {
        $this->assertTrue($this->gtService->addMessageKey('MyKey', '123456789'));
        $this->assertEquals($this->gtService->deleteMessageKey('MyKey'), 1);
    }

    public function testResettingMessageKeys(): void
    {
        $this->assertTrue($this->gtService->addMessageKey('VATRegNo', '999900001'));
        $this->assertTrue($this->gtService->addMessageKey('MyKey', '123456789'));
        $this->assertTrue($this->gtService->resetMessageKeys());
    }

    public function testSettingMessageClass(): void
    {
        $this->assertFalse($this->gtService->setMessageClass('HVD'));
        $this->assertTrue($this->gtService->setMessageClass('HMRC-VAT-DEC'));
    }

    public function testSettingMessageQualifier(): void
    {
        $this->assertTrue($this->gtService->setMessageQualifier('error'));
        $this->assertFalse($this->gtService->setMessageQualifier('other'));
        $this->assertTrue($this->gtService->setMessageQualifier('request'));
    }

    public function testSettingMessageFunction(): void
    {
        $this->assertTrue($this->gtService->setMessageFunction('submit'));
    }

    public function testAddingChannelRoute(): void
    {
        $this->assertFalse($this->gtService->addChannelRoute(array('uri' => 'a', 'product' => 'b', 'version' => 'c')));
        $this->assertTrue($this->gtService->addChannelRoute('a', 'b', 'c', array(array('1','2','3')), '2014-04-04T12:28.123'));
        $this->assertTrue($this->gtService->addChannelRoute('d', 'e', 'f', null, '', true));
    }

    public function testSettingChannelRoute(): void
    {
        $this->setMockHttpResponse('GiftAidResponseAck.txt');
        // Re-call this to actually replace the service with the one that has the non-empty
        // mock response queue.
        $this->gtService = $this->setUpService();
        $this->gtService->setMessageBody('');

        $this->gtService->setTestFlag(true);
        $this->gtService->setMessageClass('HMRC-CHAR-CLM');
        $this->gtService->setMessageAuthentication('clear');
        $this->gtService->setMessageQualifier('request');
        $this->gtService->setMessageFunction('submit');
        $this->gtService->setMessageCorrelationId('');
        $this->gtService->setMessageTransformation('XML');
        $this->gtService->addTargetOrganisation('IR');
        $this->gtService->addMessageKey('CHARID', 'CD67890');

        // Note that the `$id` array is actually invalid, which for now serves as a crude, implicit
        // check that this route is *not* used since providing [['1', '2', '3']] causes a crash!
        $this->assertTrue($this->gtService->addChannelRoute('a', 'b', 'c', [['1','2','3']], '2014-04-04T12:28.123'));
        $this->assertTrue(
            $this->gtService->setChannelRoute(
                '9999',
                'DownstreamApp',
                '0.0',
                ['type' => 'some type', 'value' => 'some value'],
                '2021-12-07T00:00.000',
            )
        );

        // TODO This test should ideally use the HTTP client mock to verify that the sent payload
        // includes the second ChannelRouting's info ("URI" 9999 etc.) and not the first. But for
        // now, as noted above, the slightly brittle message construction approach means that
        // we are implicitly verifying this in a more crude way.
        $this->assertTrue($this->gtService->sendMessage());
    }

    public function testSettingMessageBody(): void
    {
        $this->assertFalse($this->gtService->setMessageBody(array('')));
        $this->assertTrue($this->gtService->setMessageBody(new XMLWriter));
        $this->assertTrue($this->gtService->setMessageBody(''));
        $this->assertTrue(
            $this->gtService->setMessageBody(
                file_get_contents(__DIR__.'/Messages/VatReturnIREnvelope.txt')
            )
        );
    }

    public function testConstructAndSendMessage(): void
    {
        $this->setMockHttpResponse('VatReturnAuthFailure.txt');

        $this->gtService = $this->setUpService();
        $this->assertTrue($this->gtService->setTestFlag(true));
        $this->assertTrue($this->gtService->setMessageAuthentication('clear'));
        $this->assertTrue($this->gtService->setSenderEmailAddress('joe@bloggs.com'));
        $this->assertTrue($this->gtService->addMessageKey('VATRegNo', '999900001'));
        $this->assertTrue($this->gtService->setMessageClass('HMRC-VAT-DEC'));
        $this->assertTrue($this->gtService->setMessageQualifier('request'));
        $this->assertTrue($this->gtService->setMessageFunction('submit'));
        $this->gtService->addChannelRoute('http://fakeurl.com/fakeGateway', 'A fake channel route', '0.0.1');
        $this->assertTrue(
            $this->gtService->setMessageBody(
                file_get_contents(__DIR__.'/Messages/VatReturnIREnvelope.txt')
            )
        );
        $this->assertTrue($this->gtService->sendMessage());
        $this->assertTrue($this->gtService->responseHasErrors());
    }

    public function testSendPrebuiltMessage(): void
    {
        $preBuiltMessage = $this->makeGiftAidSubmission();
        $this->assertSame($preBuiltMessage, $this->gtService->getFullXMLRequest());
        $this->assertTrue(is_string($this->gtService->getFullXMLResponse()));
    }

    public function testGivenNoSubmission_getResponseQualifier_ReturnsFalse(): void
    {
        $this->assertFalse( $this->gtService->getResponseQualifier() );
    }

    public function testGivenSubmission_getResponseQualifier_ReturnsAcknowledgement(): void
    {
        $this->makeGiftAidSubmission();
        $this->assertSame( GovTalk::QUALIFIER_ACKNOWLEDGEMENT, $this->gtService->getResponseQualifier() );
    }

    /**
     * Create *and send* a mock Gift Aid claim submission, returning the request
     * body for now.
     *
     * @return string
     */
    protected function makeGiftAidSubmission(): string
    {
        $this->setMockHttpResponse('GiftAidResponseAck.txt');
        // Re-call this to actually replace the service with the one that has the non-empty
        // mock response queue.
        $this->gtService = $this->setUpService();

        $preBuiltMessage = file_get_contents(__DIR__ . '/Messages/GiftAidRequest.txt');

        $this->gtService->sendMessage($preBuiltMessage);
        return $preBuiltMessage;
    }

    private function setUpService(): GovTalk
    {
        return new GovTalk(
            $this->gatewayURL,
            $this->gatewayUserID,
            $this->gatewayUserPassword,
            $this->getHttpClient()
        );
    }
}

/**
 * TODO: The following public functions need tests:
 * - errorCount
 * - getErrors
 * - getLastError
 * - clearErrors
 * - responseHasErrors [-1]
 * - getTransactionId
 * - getFullXMLRequest [-1]
 * - getFullXMLResponse
 * - getResponseQualifier
 * - getGatewayTimestamp
 * - getResponseCorrelationId
 * - getResponseEndpoint
 * - getResponseErrors
 * - getResponseBody
 * - setGovTalkServer
 * - setSchemaLocation
 * - setSchemaValidation
 * - setMessageCorrelationId
 * - setMessageTransformation
 * - addTargetOrganisation
 * - deleteTargetOrganisation
 * - resetTargetOrganisations
 * - sendDeleteRequest
 * - sendListRequest
 */
