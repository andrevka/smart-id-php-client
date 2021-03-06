<?php
namespace Sk\SmartId\Tests\Api;

use Sk\SmartId\Api\Data\AuthenticationSessionResponse;
use Sk\SmartId\Api\Data\CertificateLevelCode;
use Sk\SmartId\Api\Data\SessionCertificate;
use Sk\SmartId\Api\Data\SessionEndResultCode;
use Sk\SmartId\Api\Data\SessionSignature;
use Sk\SmartId\Api\Data\SessionStatus;
use Sk\SmartId\Api\Data\SessionStatusCode;
use Sk\SmartId\Api\Data\SignableData;
use Sk\SmartId\Api\Data\SmartIdAuthenticationResponse;
use Sk\SmartId\Api\SessionStatusFetcherBuilder;
use Sk\SmartId\Tests\Rest\SmartIdConnectorSpy;
use Sk\SmartId\Tests\Setup;

class SessionStatusFetcherBuilderTest extends Setup
{
  /**
   * @var SmartIdConnectorSpy
   */
  private $connector;

  /**
   * @var SessionStatusFetcherBuilder
   */
  private $builder;

  protected function setUp()
  {
    $this->connector = new SmartIdConnectorSpy();
    $this->connector->authenticationSessionResponseToRespond = $this->createDummyAuthenticationSessionResponse();
    $this->connector->sessionStatusToRespond = $this->createDummySessionStatusResponse();
    $this->builder = new SessionStatusFetcherBuilder( $this->connector );
  }

  /**
   * @test
   */
  public function getAuthenticationResponse()
  {
    $sessionId = '97f5058e-e308-4c83-ac14-7712b0eb9d86';
    $dataToSign = new SignableData( $GLOBALS[ 'data_to_sign' ] );
    $authenticationResponse = $this->builder->withSignableData( $dataToSign )
        ->withSessionId( $sessionId )
        ->getAuthenticationResponse();
    $this->assertCorrectSessionRequestMade();
    $this->assertAuthenticationResponseCorrect( $authenticationResponse );
  }

  /**
   * @test
   */
  public function getAuthenticationResponse_withNetworkInterfaceInPlace()
  {
    $sessionId = '97f5058e-e308-4c83-ac14-7712b0eb9d86';
    $dataToSign = new SignableData( $GLOBALS[ 'data_to_sign' ] );
    $authenticationResponse = $this->builder->withSignableData( $dataToSign )
        ->withSessionId( $sessionId )
        ->withNetworkInterface( 'eth0' )
        ->getAuthenticationResponse();
    $this->assertCorrectSessionRequestMade();
    $this->assertAuthenticationResponseCorrect( $authenticationResponse );
  }

  /**
   * @test
   */
  public function getSessionStatus()
  {
    $sessionStatusFetcher = $this->builder->build();
    $sessionStatus = $sessionStatusFetcher->getSessionStatus();

    $this->assertFalse( $sessionStatus->isRunningState() );
  }

  /**
   * @test
   */
  public function getSessionStatus_withNetworkInterfaceInPlace()
  {
    $sessionStatusFetcher = $this->builder->withNetworkInterface( 'eth0' )->build();
    $sessionStatus = $sessionStatusFetcher->getSessionStatus();

    $this->assertFalse( $sessionStatus->isRunningState() );
  }

  /**
   * @return AuthenticationSessionResponse
   */
  private function createDummyAuthenticationSessionResponse()
  {
    $response = new AuthenticationSessionResponse();
    $response->setSessionID( '97f5058e-e308-4c83-ac14-7712b0eb9d86' );
    return $response;
  }

  /**
   * @return SessionStatus
   */
  private function createDummySessionStatusResponse()
  {
    $signature = new SessionSignature();
    $signature->setValue( 'c2FtcGxlIHNpZ25hdHVyZQ0K' );
    $signature->setAlgorithm( 'sha512WithRSAEncryption' );

    $certificate = new SessionCertificate();
    $certificate->setCertificateLevel( CertificateLevelCode::QUALIFIED );
    $certificate->setValue( DummyData::CERTIFICATE );

    $status = new SessionStatus();
    $status->setState( SessionStatusCode::COMPLETE )
        ->setResult( DummyData::createSessionEndResult() )
        ->setSignature( $signature )
        ->setCert( $certificate );
    return $status;
  }

  private function assertCorrectSessionRequestMade()
  {
    $this->assertEquals( '97f5058e-e308-4c83-ac14-7712b0eb9d86', $this->connector->sessionIdUsed );
  }

  /**
   * @param SmartIdAuthenticationResponse $authenticationResult
   */
  private function assertAuthenticationResponseCorrect( SmartIdAuthenticationResponse $authenticationResult )
  {
    $this->assertNotNull( $authenticationResult );
    $this->assertEquals( SessionEndResultCode::OK, $authenticationResult->getEndResult() );
    $this->assertEquals( 'c2FtcGxlIHNpZ25hdHVyZQ0K', $authenticationResult->getValueInBase64() );
    $this->assertEquals( 'sha512WithRSAEncryption', $authenticationResult->getAlgorithmName() );
    $this->assertEquals( DummyData::CERTIFICATE, $authenticationResult->getCertificate() );
    $this->assertEquals( CertificateLevelCode::QUALIFIED, $authenticationResult->getCertificateLevel() );
  }
}
