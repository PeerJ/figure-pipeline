<?php

require __DIR__ . '/OAuthClient.php';

class FlickrClient extends OAuthClient {
  /**
   * OAuth endpoint URLs
   *
   * @var array
   */
  protected $urls = array(
    'request_token' => 'https://api.flickr.com/services/oauth/request_token',
    'authorize' => 'https://api.flickr.com/services/oauth/authorize',
    'access_token' => 'https://api.flickr.com/services/oauth/access_token',
  );

  /**
   * Upload an image file
   *
   * Note: can't use $this->oauth->fetch directly, as the 'photo' parameter must not be included in the signature
   */
  public function upload($path, $params = array()) {
    $url = 'https://up.flickr.com/services/upload/';

    $curl = curl_init($url);

    curl_setopt_array($curl, array(
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $params + array(
        'photo' => new CurlFile($path, 'image/png', basename($path))
      ),
      CURLOPT_HTTPHEADER => array(
        'Authorization: ' . $this->oauth->getRequestHeader('POST', $url, $params)
      )
    ));

    $result = curl_exec($curl);
    $info = curl_getinfo($curl);

    //print_r($info);
    //print_r($result);

    $xml = simplexml_load_string($result);

    if ($xml['stat'] != 'ok') {
      exit('Not OK!');
    }

    return (string) $xml->photoid;
  }

  /*
  public function check_status() {
    try {
      $this->oauth->fetch('https://api.flickr.com/services/rest/?' . http_build_query(array(
        'method' => 'flickr.people.getUploadStatus',
        'format' => 'json',
        'nojsoncallback' => '1'
      )));
    } catch (Exception $e) {
      print_r($this->oauth->getLastResponseInfo());
    }

    return json_decode($this->oauth->getLastResponse(), true);
  }
  */
}

