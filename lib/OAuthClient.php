<?php

/**
 * Generic OAuth 1.0 client class
 *
 * Uses the PECL OAuth library (`pecl install oauth`)
 */
class OAuthClient {
  /**
   * @var OAuth
   */
  protected $oauth;

  /**
   * OAuth endpoint URLs (must be set in the child class)
   *
   * @var array
   */
  protected $urls = array(
    'request_token' => null,
    'authorize' => null,
    'access_token' => null,
  );

  /**
   * Create the OAuth client and set the access token + secret.
   *
   * Fetch a request token or access token if needed.
   */
  public function __construct($config) {
    $this->oauth = new OAuth($config['consumer_key'], $config['consumer_secret'], OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);

    if (!($config['oauth_token'] && $config['oauth_token_secret'])) {
      $request_token = isset($config['request_token']) ? $config['request_token'] : $this->request_token();
      $access_token = $this->access_token($request_token);
      exit(print_r($access_token, true)); // write the result to the config file manually
    }

    $this->oauth->setToken($config['oauth_token'], $config['oauth_token_secret']);
  }

  /**
   * Get a request token
   */
  protected function request_token() {
    try {
      return $this->oauth->getRequestToken($this->urls['request_token'], 'oob');
    } catch (Exception $e) {
      exit(print_r($this->oauth->getLastResponseInfo(), true));
    }
  }

  /**
   * Get an access token, using the request token and authorisation code
   */
  protected function access_token($request_token) {
    printf("Authorize: %s\n", $this->urls['authorize'] . '?' . http_build_query(array(
      'oauth_token' => $request_token['oauth_token']
    )));

    fwrite(STDOUT, "Enter the PIN: ");
    $code = trim(fgets(STDIN));

    $this->oauth->setToken($request_token['oauth_token'], $request_token['oauth_token_secret']);

    return $this->oauth->getAccessToken($this->urls['access_token'], NULL, $code);
  }
}

