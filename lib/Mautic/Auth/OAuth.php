<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     MIT http://opensource.org/licenses/MIT
 */

namespace Mautic\Auth;

use Mautic\Exception\IncorrectParametersReturnedException;
use Mautic\Exception\UnexpectedResponseFormatException;

/**
 * OAuth Client modified from https://code.google.com/p/simple-php-oauth/
 *
 * Class Oauth
 *
 */
class OAuth extends ApiAuth implements AuthInterface
{

    /**
     * @var string Consumer or client key
     */
    protected $_client_id;

    /**
     * @var string Consumer or client secret
     */
    protected $_client_secret;

    /**
     * @var string Callback or Redirect URL
     */
    protected $_callback;

    /**
     * @var string Access token returned by OAuth server
     */
    protected $_access_token;

    /**
     * @var string Access token secret returned by OAuth server
     */
    protected $_access_token_secret;

    /**
     * @var string Unix timestamp for when token expires
     */
    protected $_expires;

    /**
     * @var string OAuth2 refresh token
     */
    protected $_refresh_token;

    /**
     * @var string OAuth2 token type
     */
    protected $_token_type;

    /**
     * @var bool Set to true if a refresh token was used to update an access token
     */
    protected $_access_token_updated = false;

    /**
     * @var string OAuth2 redirect type
     */
    protected $_redirect_type = 'code';

    /**
     * @var string OAuth2 scope
     */
    protected $_scope = array();

    /**
     * @var string Authorize URL
     */
    protected $_authorize_url;

    /**
     * @var string Access token URL
     */
    protected $_access_token_url;

    /**
     * @var string Request token URL for OAuth1
     */
    protected $_request_token_url;

    /**
     * If set to true, $_SESSION['debug'] will be populated
     *
     * @var bool
     */
    protected $_debug = false;

    /**
     * @param null   $clientKey
     * @param null   $clientSecret
     * @param null   $accessToken
     * @param null   $accessTokenSecret
     * @param null   $accessTokenExpires
     * @param null   $callback
     * @param null   $accessTokenUrl
     * @param null   $authorizationUrl
     * @param null   $requestTokenUrl
     * @param null   $scope
     * @param null   $refreshToken
     */
    public function setup ($clientKey = null, $clientSecret = null, $accessToken = null, $accessTokenSecret = null,
                           $accessTokenExpires = null, $callback = null, $accessTokenUrl = null,
                           $authorizationUrl = null, $requestTokenUrl = null, $scope = null, $refreshToken = null)
    {
        $this->_client_id           = $clientKey;
        $this->_client_secret       = $clientSecret;
        $this->_access_token        = $accessToken;
        $this->_access_token_secret = $accessTokenSecret;
        $this->_callback            = $callback;
        $this->_access_token_url    = $accessTokenUrl;
        $this->_request_token_url   = $requestTokenUrl;
        $this->_authorize_url       = $authorizationUrl;

        if (!empty($scope)) {
            $this->setScope($scope);
        }

        if (!empty($accessToken)) {
            $this->setAccessTokenDetails(array(
                'access_token'        => $accessToken,
                'access_token_secret' => $accessTokenSecret,
                'expires'             => $accessTokenExpires,
                'refresh_token'       => $refreshToken
            ));
        }
    }

    /**
     * Set authorization URL
     *
     * @param $url
     */
    public function setAuthorizeUrl ($url)
    {
        $this->_authorize_url = $url;
    }

    /**
     * Set request token URL
     *
     * @param $url
     */
    public function setRequestTokenUrl ($url)
    {
        $this->_request_token_url = $url;
    }

    /**
     * Set access token URL
     *
     * @param $url
     */
    public function setAccessTokenUrl ($url)
    {
        $this->_access_token_url = $url;
    }

    /**
     * Set redirect type for OAuth2
     *
     * @param $type
     */
    public function setRedirectType ($type)
    {
        $this->_redirect_type = $type;
    }

    /**
     * Set OAuth2 scope
     *
     * @param array|string $scope
     */
    public function setScope ($scope)
    {
        if (!is_array($scope)) {
            $this->_scope = explode(',', $scope);
        } else {
            $this->_scope = $scope;
        }
    }

    /**
     * Set an existing/already retrieved access token
     *
     * @param array $accessTokenDetails
     */
    public function setAccessTokenDetails (array $accessTokenDetails)
    {
        $this->_access_token        = isset($accessTokenDetails['access_token']) ? $accessTokenDetails['access_token'] : null;
        $this->_access_token_secret = isset($accessTokenDetails['access_token_secret']) ? $accessTokenDetails['access_token_secret'] : null;
        $this->_expires             = isset($accessTokenDetails['expires']) ? $accessTokenDetails['expires'] : null;
        $this->_refresh_token       = isset($accessTokenDetails['refresh_token']) ? $accessTokenDetails['refresh_token'] : null;
    }

    /**
     * Returns access token data
     */
    public function getAccessTokenData ()
    {
        if ($this->isOauth1()) {
            return array(
                "access_token"        => $this->_access_token,
                "access_token_secret" => $this->_access_token_secret,
                "expires"             => $this->_expires,
            );
        } else {
            return array(
                "access_token"        => $this->_access_token,
                "expires"             => $this->_expires,
                "token_type"          => $this->_token_type,
                "refresh_token"       => $this->_refresh_token
            );
        }
    }

    /**
     * Enables debug mode
     */
    public function enableDebugMode ()
    {
        $this->_debug = true;
    }

    /**
     * Check to see if the access token was updated from a refresh token
     *
     * @return bool
     */
    public function accessTokenUpdated()
    {
        return $this->_access_token_updated;
    }

    /**
     * Returns $_SESSION['oauth']['debug'] if $this->_debug = true
     *
     * @return array
     */
    public function getDebugInfo ()
    {
        return ($this->_debug && !empty($_SESSION['oauth']['debug'])) ? $_SESSION['oauth']['debug'] : array();
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function isAuthorized ()
    {
        //Check for existing access token
        if (!empty($this->_request_token_url)) {
            if (strlen($this->_access_token) > 0 && strlen($this->_access_token_secret) > 0) {
                return true;
            }
        } else {
            //Check to see if token in session has expired
            if (!empty($this->_expires) && $this->_expires < time()) {
                return false;
            } elseif (strlen($this->_access_token) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate existing access token
     *
     * @return bool
     */
    public function validateAccessToken ()
    {
        $this->log('validateAccessToken()');

        //Check to see if token in session has expired
        if (!empty($this->_expires) && $this->_expires < time()) {
            $this->log('access token expired so reauthorize');

            if (strlen($this->_refresh_token) > 0) {
                //use a refresh token to get a new token
                return $this->requestAccessToken();
            } else {
                //Reauthorize
                $this->authorize($this->_scope);

                return false;
            }
        }

        //Check for existing access token
        if (strlen($this->_access_token) > 0) {
            $this->log('has access token');

            return true;
        }

        //Reauthorize if no token was found
        if (strlen($this->_access_token) == 0) {
            $this->log('access token empty so authorize');

            //OAuth flows
            if ($this->isOauth1()) {
                //OAuth 1.0
                $this->log('authorizing with OAuth1.0a spec');

                //Request token and authorize app
                if (!isset($_GET['oauth_token']) && !isset($_GET['oauth_verifier'])) {
                    $this->log('initializing authorization');

                    //Request token
                    $this->requestToken();
                    //Authorize token
                    $this->authorize();

                    return false;
                } else {
                    //Request access token
                    if ($_GET['oauth_token'] != $_SESSION['oauth']['token']) {
                        unset($_SESSION['oauth']['token'], $_SESSION['oauth']['token_secret']);

                        return false;
                    } else {
                        $this->requestAccessToken();
                        unset($_SESSION['oauth']['token'], $_SESSION['oauth']['token_secret']);

                        return true;
                    }
                }
            } else {
                //OAuth 2.0
                $this->log('authorizing with OAuth2 spec');
                //Authorize app
                if (!isset($_GET['state']) && !isset($_GET['code'])) {
                    $this->authorize($this->_scope);

                    return false;
                } else {
                    if ($this->_debug) {
                        $_SESSION['oauth']['debug']['received_state'] = $_GET['state'];
                    }

                    //Request an access token
                    if ($_GET['state'] != $_SESSION['oauth']['state']) {
                        unset($_SESSION['oauth']['state']);

                        return false;
                    } else {
                        unset($_SESSION['oauth']['state']);
                        $this->requestAccessToken('GET', array(), 'json');

                        return true;
                    }
                }
            }
        }
    }

    /**
     * Request token for OAuth1
     *
     * @param string $responseType
     * @param array  $values
     *
     * @throws IncorrectParametersReturnedException
     */
    protected function requestToken ($responseType = 'flat')
    {
        $this->log('requestToken()');

        //Make the request
        $settings = array(
            'responseType'    => $responseType,
            'includeCallback' => true,
            'includeVerifier' => false
        );
        $params = $this->makeRequest($this->_request_token_url, array(), 'POST', $settings);

        //Add token and secret to the session
        if (is_array($params) && isset($params['oauth_token']) && isset($params['oauth_token_secret'])) {
            $this->log('token set as ' . $params['oauth_token']);

            $_SESSION['oauth']['token']        = $params['oauth_token'];
            $_SESSION['oauth']['token_secret'] = $params['oauth_token_secret'];

            if ($this->_debug) {
                $_SESSION['oauth']['debug']['token']        = $params['oauth_token'];
                $_SESSION['oauth']['debug']['token_secret'] = $params['oauth_token_secret'];
            }
        } else {
            //Throw exception if the required parameters were not found
            $this->log('request did not return oauth tokens');

            if (is_array($params)) {
                if (isset($params['error'])) {
                    $response = $params['error'];
                } else {
                    $response = '???';
                }
            } else {
                $response = $params;
            }

            throw new IncorrectParametersReturnedException('Incorrect access token parameters returned: ' . $response);
        }
    }

    /**
     * Request access token
     *
     * @param string $method
     * @param array  $params
     * @param string $responseType
     *
     * @throws IncorrectParametersReturnedException
     */
    protected function requestAccessToken ($method = 'GET', array $params = array(), $responseType = 'flat')
    {
        $this->log('requestAccessToken()');

        //Set OAuth flow parameters
        if ($this->isOauth1()) {
            //OAuth 1.0
            $this->log('using OAuth1.0a spec');

            $parameters = array('oauth_verifier' => $_GET['oauth_verifier']);
            $parameters = array_merge($parameters, $params);
        } else {
            //OAuth 2.0
            $this->log('using OAuth2 spec');

            $parameters = array(
                'client_id'     => $this->_client_id,
                'redirect_uri'  => $this->_callback,
                'client_secret' => $this->_client_secret,
                'code'          => $_GET['code'],
                'grant_type'    => 'authorization_code'
            );

            if (strlen($this->_refresh_token) > 0) {
                $this->log('Using refresh token');
                $parameters['grant_type']    = 'refresh_token';
                $parameters['refresh_token'] = $this->_refresh_token;
            }

            $parameters = array_merge($parameters, $params);
        }

        //Make the request
        $settings = array(
            'responseType'    => $responseType,
            'includeCallback' => true,
            'includeVerifier' => true
        );

        $params = $this->makeRequest($this->_access_token_url, $parameters, $method, $settings);

        //Add the token and secret to session
        if (is_array($params)) {
            if ($this->isOauth1()) {
                //OAuth 1.0a
                if (isset($params['oauth_token']) && isset($params['oauth_token_secret'])) {
                    $this->log('access token set as ' . $params['oauth_token']);

                    $this->_access_token         = $params['oauth_token'];
                    $this->_access_token_secret  = $params['oauth_token_secret'];
                    $this->_access_token_updated = true;

                    if ($this->_debug) {
                        $_SESSION['oauth']['debug']['tokens']['access_token']        = $params['oauth_token'];
                        $_SESSION['oauth']['debug']['tokens']['access_token_secret'] = $params['oauth_token_secret'];
                    }

                    return true;
                }
            } else {
                //OAuth 2.0
                if (isset($params['access_token']) && isset($params['expires_in'])) {
                    $this->log('access token set as ' . $params['access_token']);

                    $this->_access_token         = $params['access_token'];
                    $this->_expires              = time() + $params['expires_in'];
                    $this->_token_type           = (isset($params['token_type'])) ? $params['token_type'] : null;
                    $this->_refresh_token        = (isset($params['refresh_token'])) ? $params['refresh_token'] : null;
                    $this->_access_token_updated = true;

                    if ($this->_debug) {
                        $_SESSION['oauth']['debug']['tokens']['access_token']   = $params['access_token'];
                        $_SESSION['oauth']['debug']['tokens']['expires_in']     = $params['expires_in'];
                        $_SESSION['oauth']['debug']['tokens']['token_type']     = $params['token_type'];
                        $_SESSION['oauth']['debug']['tokens']['refresh_token']  = $params['refresh_token'];
                    }

                    return true;
                }
            }
        }

        $this->log('response did not have an access token');

        //Throw exception if required parameters were not found
        $s = array();
        if (is_array($params)) {
            foreach ($params as $k => $v) {
                $s[] = $k . '=' . $v;
            }
            $response = implode('&', $s);
        } else {
            $response = $params;
        }
        throw new IncorrectParametersReturnedException('Incorrect access token parameters returned: ' . $response);
    }

    /**
     * Authorize app
     *
     * @param array  $scope
     * @param string $scope_separator
     * @param null   $attach
     */
    protected function authorize (array $scope = array(), $scope_separator = ',', $attach = null)
    {
        $authUrl = $this->_authorize_url;

        //Build authorization URL
        if ($this->isOauth1()) {
            //OAuth 1.0
            $authUrl .= '?oauth_token=' . $_SESSION['oauth']['token'];
        } else {
            //OAuth 2.0
            $authUrl .= '?client_id=' . $this->_client_id . '&redirect_uri=' . $this->_callback;
            $state                      = md5(time() . mt_rand());
            $_SESSION['oauth']['state'] = $state;
            if ($this->_debug) {
                $_SESSION['oauth']['debug']['generated_state'] = $state;
            }

            $authUrl .= '&state=' . $state . '&scope=' . implode($scope_separator, $scope) . $attach;
            $authUrl .= '&response_type=' . $this->_redirect_type;
        }
        $this->log('redirecting to auth url ' . $authUrl);

        //Redirect to authorization URL
        header('Location: ' . $authUrl);
        exit;
    }

    /**
     * Make a request to API/OAuth server
     *
     * @param        $url
     * @param array  $parameters
     * @param string $method
     * @param array  $settings
     *
     * @return mixed
     * @throws UnexpectedResponseFormatException
     */
    public function makeRequest ($url, array $parameters = array(), $method = 'GET', array $settings = array())
    {
        $this->log('makeRequest(' . $url . ', ' . http_build_query($parameters) . ', ' . $method . ',...)');

        $includeCallback = (isset($settings['includeCallback'])) ? $settings['includeCallback'] : false;
        $includeVerifier = (isset($settings['includeVerifier'])) ? $settings['includeVerifier'] : false;

        //make sure $method is capitalized for congruency
        $method = strtoupper($method);

        //Set OAuth parameters/headers
        if ($this->isOauth1()) {
            //OAuth 1.0
            $this->log('making request using OAuth1.0a spec');

            //Get standard OAuth headers
            $headers = $this->getOauthHeaders($includeCallback);

            if ($includeVerifier && isset($_GET['oauth_verifier'])) {
                $headers['oauth_verifier'] = $_GET['oauth_verifier'];
                if ($this->_debug) {
                    $_SESSION['oauth']['debug']['oauth_verifier'] = $_GET['oauth_verifier'];
                }
            }

            //Add the parameters
            $headers                    = array_merge($headers, $parameters);
            $base_info                  = $this->buildBaseString($url, $method, $headers);
            $composite_key              = $this->getCompositeKey();
            $headers['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
            $header                     = array($this->buildAuthorizationHeader($headers), 'Expect:');
            if ($this->_debug) {
                $_SESSION['oauth']['debug']['basestring'] = $base_info;
                $_SESSION['oauth']['debug']['headers']    = $headers;
            }
        } else {
            //OAuth 2.0
            $this->log('making request using OAuth2 spec');

            $parameters['access_token'] = $this->_access_token;
        }

        //Create a querystring for GET/DELETE requests
        if (count($parameters) > 0 && in_array($method, array('GET', 'DELETE')) && strpos($url, '?') === false) {
            $url = $url . '?' . http_build_query($parameters);
            $this->log('URL updated to ' . $url);
        }

        //Set default CURL options
        $options = array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER         => true
        );

        // CURLOPT_FOLLOWLOCATION cannot be activated when an open_basedir is set
        if (ini_get('open_basedir')) {
            $options[CURLOPT_FOLLOWLOCATION] = false;
        } else {
            $options[CURLOPT_FOLLOWLOCATION] = true;
        }

        //Set CURL headers for oauth 1.0 requests
        if ($this->isOauth1()) {
            $options[CURLOPT_HTTPHEADER] = $header;
        }

        //Set custom REST method if not GET or POST
        if (!in_array($method, array('GET', 'POST'))) {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            $options[CURLOPT_POSTFIELDS] = http_build_query($parameters);
        }

        //Set post fields for POST/PUT/PATCH requests
        if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($parameters);
            $this->log('Posted parameters = ' . $options[CURLOPT_POSTFIELDS]);
        }

        //Make CURL request
        $curl = curl_init();
        curl_setopt_array($curl, $options);

        $response      = curl_exec($curl);
        $responseArray = explode("\r\n\r\n", $response);
        $body          = array_pop($responseArray);
        $header        = implode("\r\n\r\n", $responseArray);
        $info          = curl_getinfo($curl);

        curl_close($curl);

        if ($this->_debug) {
            $_SESSION['oauth']['debug']['info']            = $info;
            $_SESSION['oauth']['debug']['returnedHeaders'] = $header;
            $_SESSION['oauth']['debug']['returnedBody']    = $body;
        }

        $responseGood = false;

        //Check to see if the response is JSON

        $parsed = json_decode($body, true);
        if ($parsed === null) {
            if (strpos($body, '=') !== false) {
                parse_str($body, $parsed);
                $responseGood = true;
            }
        } else {
            $responseGood = true;
        }

        //Show error when http_code is not appropriate
        if (!in_array($info['http_code'], array(200, 201))) {

            if ($responseGood) {
                return $parsed;
            } else {
                throw new UnexpectedResponseFormatException($body);
            }
        } else {
            return ($responseGood) ? $parsed : $body;
        }
    }

    /**
     * Get composite key for OAuth 1 signature signing
     *
     * @return string
     */
    private function getCompositeKey ()
    {
        if (isset($this->_access_token_secret) && strlen($this->_access_token_secret) > 0) {
            $composite_key = $this->encode($this->_client_secret) . '&' . $this->encode($this->_access_token_secret);
        } else if (isset($_SESSION['oauth']['token_secret'])) {
            $composite_key = $this->encode($this->_client_secret) . '&' . $this->encode($_SESSION['oauth']['token_secret']);
        } else {
            $composite_key = $this->encode($this->_client_secret) . '&';
        }

        return $composite_key;
    }

    /**
     * Get OAuth 1.0 Headers
     *
     * @param bool $includeCallback
     *
     * @return array
     */
    private function getOauthHeaders ($includeCallback = false)
    {
        $oauth = array(
            'oauth_consumer_key'     => $this->_client_id,
            'oauth_nonce'            => $this->generateNonce(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => time(),
            'oauth_version'          => '1.0'
        );
        if (isset($this->_access_token)) {
            $oauth['oauth_token'] = $this->_access_token;
        } else if (isset($_SESSION['oauth']['token'])) {
            $oauth['oauth_token'] = $_SESSION['oauth']['token'];
        }
        if ($includeCallback) {
            $oauth['oauth_callback'] = $this->_callback;
        }

        return $oauth;
    }

    /**
     * Build base string for OAuth 1 signature signing
     *
     * @param $baseURI
     * @param $method
     * @param $params
     *
     * @return string
     */
    private function buildBaseString ($baseURI, $method, $params)
    {
        $r = $this->normalizeParameters($params);

        return $method . '&' . $this->encode($baseURI) . '&' . $this->encode($r);
    }

    /**
     * Build header for OAuth 1 authorization
     *
     * @param $oauth
     *
     * @return string
     */
    private function buildAuthorizationHeader ($oauth)
    {
        $r      = 'Authorization: OAuth ';
        $values = $this->normalizeParameters($oauth, true, true);
        $r .= implode(', ', $values);

        return $r;
    }

    /**
     * Normalize parameters
     *
     * @param      $parameters
     * @param bool $encode
     * @param bool $returnarray
     *
     * @return string
     */
    private function normalizeParameters ($parameters, $encode = false, $returnarray = false, $normalized = array(), $key = '')
    {
        //Sort by key
        ksort($parameters);

        foreach ($parameters as $k => $v) {
            if (is_array($v)) {
                $normalized = $this->normalizeParameters($v, $encode, true, $normalized, $k);
            } else {
                if ($key) {
                    //Multidimensional array; using foo=baz&foo=bar rather than foo[bar]=baz&foo[baz]=bar as this is
                    //what the server expects when creating the signature
                    $k = $key;
                }
                if ($encode) {
                    $normalized[] = $this->encode($k) . '=' . $this->encode($v);
                } else {
                    $normalized[] = $k . '=' . $v;
                }
            }
        }

        return $returnarray ? $normalized : implode('&', $normalized);
    }

    /**
     * Returns an encoded string according to the RFC3986.
     *
     * @param $string
     */
    private function encode ($string)
    {
        return str_replace('%7E', '~', rawurlencode($string));
    }

    /**
     * OAuth1.0 nonce generator
     *
     * @param int $bits
     *
     * @return string
     */
    private function generateNonce ($bits = 64)
    {
        $result          = '';
        $accumulatedBits = 0;
        $random          = mt_getrandmax();
        for ($totalBits = 0; $random != 0; $random >>= 1) {
            ++$totalBits;
        }
        $usableBits = intval($totalBits / 8) * 8;

        while ($accumulatedBits < $bits) {
            $bitsToAdd = min($totalBits - $usableBits, $bits - $accumulatedBits);
            if ($bitsToAdd % 4 != 0) {
                // add bits in whole increments of 4
                $bitsToAdd += 4 - $bitsToAdd % 4;
            }

            // isolate leftmost $bits_to_add from mt_rand() result
            $moreBits = mt_rand() & ((1 << $bitsToAdd) - 1);

            // format as hex (this will be safe)
            $format_string = '%0' . ($bitsToAdd / 4) . 'x';
            $result .= sprintf($format_string, $moreBits);
            $accumulatedBits += $bitsToAdd;
        }

        return $result;
    }

    /**
     * @param $message
     */
    protected function log($message)
    {
        if ($this->_debug) {
            $_SESSION['oauth']['debug']['flow'][date('m-d H:i:s')][] = $message;
        }
    }

    /**
     * @return bool
     */
    protected function isOauth1()
    {
        return strlen($this->_request_token_url) > 0;
    }
}