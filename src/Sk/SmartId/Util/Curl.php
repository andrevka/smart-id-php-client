<?php
namespace Sk\SmartId\Util;

use Exception;

class Curl
{
  static
      $server_ssl_public_keys = self::DEMO_SID_PUBLIC_KEY.";".self::RP_API_PUBLIC_KEY_VALID_FROM_2016_12_20_TO_2020_01_19.";".self::RP_API_PUBLIC_KEY_VALID_FROM_2019_11_01_TO_2021_11_05;

  const
      GET = 1,
      POST = 2,
      PUT = 3,
      DEMO_SID_PUBLIC_KEY = "sha256//QLZIaH7Qx9Rjq3gyznQuNsvwMQb7maC5L4SLu/z5qNU=",
      RP_API_PUBLIC_KEY_VALID_FROM_2016_12_20_TO_2020_01_19 = "sha256//R8b8SIj92sylUdok0DqfxJJN0yW2O3epE0B+5vpo2eM=",
      RP_API_PUBLIC_KEY_VALID_FROM_2019_11_01_TO_2021_11_05 = "sha256//l2uvq6ftLN4LZ+8Un+71J2vH1BT9wTbtrE5+Fj3Vc5g=";

  protected
      $curl,
      $rawData,
      $cookies = array(),
      $postFields = array(),
      $followLocation = 0,
      $requestMethod = self::GET,
      $importCookies = false,
      $includeHeaders = false,
      $curlTimeout = 600;

  /**
   * @throws Exception
   */
  public function __construct()
  {
    if ( !function_exists( 'curl_init' ) )
    {
      throw new Exception( 'curl not installed' );
    }

    $this->curl = curl_init();
  }

  /**
   * @param string $url
   * @param array $params
   *
   * @return Curl
   */
  public function curlGet( $url, array $params = array() )
  {
    if ( count( $params ) )
    {
      $url .= '?' . $this->generatePostFields( $params );
    }

    $this->setCurlParam( CURLOPT_URL, $url );
    $this->requestMethod = self::GET;

    return $this;
  }

  /**
   * @param $followLocation
   * @return Curl
   */
  public function followLocation( $followLocation )
  {
    $this->followLocation = ( (bool)$followLocation ? 1 : 0 );

    return $this;
  }

  /**
   * @param $paramsId
   * @param $paramsValue
   * @return Curl
   */
  public function setCurlParam( $paramsId, $paramsValue )
  {
    curl_setopt( $this->curl, $paramsId, $paramsValue );

    return $this;
  }

  /**
   * @param string $url
   * @param array $postData
   * @param null $rawData
   * @return Curl
   */
  public function curlPost( $url, array $postData = array(), $rawData = null )
  {
    $this->setCurlParam( CURLOPT_URL, $url );
    $this->requestMethod = self::POST;
    $this->postFields = $postData;
    $this->rawData = $rawData;

    return $this;
  }

  /**
   * @param string $url
   * @param array $postData
   * @param null $rawData
   * @return Curl
   */
  public function curlPut( $url, array $postData = array(), $rawData = null )
  {
    $this->setCurlParam( CURLOPT_URL, $url );
    $this->requestMethod = self::PUT;
    $this->postFields = $postData;
    $this->rawData = $rawData;

    return $this;
  }

  /**
   * @param string $savePath
   */
  public function download( $savePath )
  {
    $file = fopen( $savePath, 'w' );

    curl_setopt( $this->curl, CURLOPT_FILE, $file );

    $this->sendRequest();

    $this->closeRequest();

    fclose( $file );
  }

  /**
   * @return mixed
   */
  public function fetch()
  {
    curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, 1 );

    $result = $this->sendRequest();

    return $result;
  }

  public function getCookies()
  {
    $this->importCookies( true );

    curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, 1 );

    $result = $this->sendRequest();

    $this->closeRequest();

    return $this->exportCookies( $result );
  }

  public function setCookies( $cookies )
  {
    $this->cookies = $cookies;
  }

  public function importCookies( $importCookies = true )
  {
    $this->importCookies = (bool)$importCookies;
  }

  public function includeHeaders( $includeHeaders = true )
  {
    $this->includeHeaders = (bool)$includeHeaders;
  }

  protected function sendRequest()
  {
    curl_setopt( $this->curl, CURLOPT_HEADER, ( ( $this->includeHeaders || $this->importCookies ) ? 1 : 0 ) );
    curl_setopt( $this->curl, CURLOPT_FOLLOWLOCATION, $this->followLocation );
    curl_setopt( $this->curl, CURLOPT_TIMEOUT, $this->curlTimeout );
    curl_setopt( $this->curl, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $this->curl, CURLOPT_PINNEDPUBLICKEY, self::$server_ssl_public_keys);

    if ( self::POST === $this->requestMethod )
    {
      // Send POST request
      curl_setopt( $this->curl, CURLOPT_POST, 1 );
      curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $this->getPostFieldsString() );
    }
    elseif ( self::PUT === $this->requestMethod )
    {
      // Send PUT request
      curl_setopt( $this->curl, CURLOPT_CUSTOMREQUEST, 'PUT' );
      curl_setopt( $this->curl, CURLOPT_HTTPHEADER,
          array('Content-Length: ' . strlen( $this->getPostFieldsString() )) );
      curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $this->getPostFieldsString() );
    }

    if ( count( $this->cookies ) )
    {
      // Send cookies
      curl_setopt( $this->curl, CURLOPT_COOKIE, $this->generateCookies( $this->cookies ) );
    }

    return curl_exec( $this->curl );
  }

  public function closeRequest()
  {
    curl_close( $this->curl );
  }

  /**
   * Return mail headers
   */
  public function getHeaders( $source, $continue = true )
  {
    if ( false !== ( $separator_pos = strpos( $source, "\r\n\r\n" ) ) )
    {
      if ( $continue && false !== strpos( $source, 'HTTP/1.1 100 Continue' ) )
      {
        $source = trim( substr( $source, $separator_pos + 4 ) );
        $source = $this->getHeaders( $source, false );
      }
      else
      {
        $source = trim( substr( $source, 0, $separator_pos ) );
      }

      return $source;
    }

    return '';
  }

  public function &removeHeaders( $source, $continue = true )
  {
    if ( false !== ( $separator_pos = strpos( $source, "\r\n\r\n" ) ) )
    {
      if ( $continue && false !== strpos( $source, 'HTTP/1.1 100 Continue' ) )
      {
        $source = trim( substr( $source, $separator_pos + 4 ) );
        $source =& $this->removeHeaders( $source, false );
      }
      else
      {
        $source = trim( substr( $source, $separator_pos + 4 ) );
      }
    }

    return $source;
  }

  /**
   * If cookies were sent, save them
   */
  public function exportCookies( $source )
  {
    $cookies = array();

    if ( preg_match_all( '#Set-Cookie:\s*([^=]+)=([^;]+)#i', $source, $matches ) )
    {
      for ( $i = 0, $cnt = count( $matches[ 1 ] ); $i < $cnt; ++$i )
      {
        $cookies[ trim( $matches[ 1 ][ $i ] ) ] = trim( $matches[ 2 ][ $i ] );
      }
    }

    return $cookies;
  }

  public function getPostFieldsString()
  {
    if ( !empty( $this->rawData ) )
    {
      return $this->rawData;
    }

    return $this->generatePostFields( $this->postFields );
  }

  /**
   * @param array $inputArray
   * @return string
   */
  public function generatePostFields( array $inputArray )
  {
    return http_build_query( $inputArray );
  }

  public function generateCookies( $inputArray )
  {
    $cookies = array();

    foreach ( $inputArray as $field => $value )
    {
      $cookies[] = $field . '=' . $value;
    }

    return implode( ';', $cookies );
  }

  public function prepareCookies()
  {
    if ( count( $this->cookies ) )
    {
      return implode( ';', $this->cookies );
    }
    else
    {
      return false;
    }
  }

  public function getCurlTimeout()
  {
    return $this->curlTimeout;
  }

  public function setCurlTimeout( $curlTimeout )
  {
    $this->curlTimeout = $curlTimeout;
  }

  /**
   * @return bool|string
   */
  public function getError()
  {
    if ( curl_errno( $this->curl ) )
    {
      return curl_error( $this->curl );
    }

    return false;
  }

  public static function useOnlyDemoPublicKey()
  {
      self::$server_ssl_public_keys = self::DEMO_SID_PUBLIC_KEY;
  }

  public static function useOnlyLivePublicKey()
  {
      self::$server_ssl_public_keys =
              self::RP_API_PUBLIC_KEY_VALID_FROM_2016_12_20_TO_2020_01_19 . ";"
              . self::RP_API_PUBLIC_KEY_VALID_FROM_2019_11_01_TO_2021_11_05;
  }

    public static function setPublicKeysFromArray(array $public_keys)
    {
        self::$server_ssl_public_keys = "";
        foreach ($public_keys as $public_key)
        {
            self::$server_ssl_public_keys .= $public_key.";";
        }
        self::$server_ssl_public_keys = substr(self::$server_ssl_public_keys, 0, strlen(self::$server_ssl_public_keys)-1);
    }

    /**
   * @param int $option
   * @return array|mixed
   */
  public function getCurlInfo( $option = null )
  {
    if ( null !== $option )
    {
      return curl_getinfo( $this->curl, $option );
    }

    return curl_getinfo( $this->curl );
  }
}
