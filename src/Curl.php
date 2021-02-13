<?php

namespace Hhxsv5\PhpMultiCurl;

class Curl
{
  protected $id;
  protected $handle;
  protected $maxGetSize = 0;
  protected $bufferSize = 0;
  protected $meetPhp55 = false;
  protected $maxRedirectCount = 5;
  protected $method = 'GET';
  /**
   * @var Response
   */
  protected $response;

  /**
   * @var array
   */
  protected $metaData = [];

  protected $multi = false;

  protected $options = [];

  protected static $defaultOptions = [
    //bool
    CURLOPT_HEADER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => true,

    //int
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 6,
    CURLOPT_CONNECTTIMEOUT => 3,

    //string
    CURLOPT_USERAGENT => 'Multi-cURL client v1.5.0',
  ];

  public function __construct($id = null, array $options = [])
  {
    $this->id = $id;
    $this->options = $options + self::$defaultOptions;
    $this->meetPhp55 = version_compare(PHP_VERSION, '5.5.0', '>=');
  }

  protected function init()
  {
    if ($this->meetPhp55) {
      if ($this->handle === null) {
        $this->handle = curl_init();
      } else {
        curl_reset($this->handle); //Reuse cUrl handle: since 5.5.0
      }
    } else {
      if ($this->handle !== null) {
        curl_close($this->handle);
      }
      $this->handle = curl_init();
    }
    curl_setopt_array($this->handle, $this->options);
  }

  public function getId()
  {
    return $this->id;
  }

  public function makeGet($url, $params = null, array $headers = [])
  {
    $this->init();
    $this->method = 'GET';
    if (is_string($params) || is_array($params)) {
      is_array($params) and $params = http_build_query($params);
      $url = rtrim($url, '?');
      if (strpos($url, '?') !== false) {
        $url .= '&' . $params;
      } else {
        $url .= '?' . $params;
      }
    }


    if ($this->bufferSize) {
      curl_setopt($this->handle, CURLOPT_BUFFERSIZE, $this->bufferSize);
    }

    if ($this->maxGetSize) {
      curl_setopt($this->handle, CURLOPT_NOPROGRESS, false);
      curl_setopt($this->handle, CURLOPT_PROGRESSFUNCTION, function ($handle, $downloadSize, $downloaded, $uploadSize, $uploaded) {
        return ($downloaded >= $this->maxGetSize) ? 1 : 0;
      });
    }

    curl_setopt($this->handle, CURLOPT_URL, $url);
    curl_setopt($this->handle, CURLOPT_HTTPGET, true);

    if (!empty($headers)) {
      curl_setopt($this->handle, CURLOPT_HTTPHEADER, $headers);
    }
  }

  public function makePost($url, $params = null, array $headers = [])
  {
    $this->init();
    $this->method = 'POST';
    curl_setopt_array($this->handle, [CURLOPT_URL => $url, CURLOPT_POST => true]);

    //CURLFile support
    if (is_array($params)) {
      $hasUploadFile = false;
      if ($this->meetPhp55) {//CURLFile: since 5.5.0
        foreach ($params as $k => $v) {
          if ($v instanceof \CURLFile) {
            $hasUploadFile = true;
            break;
          }
        }
      }
      $hasUploadFile or $params = http_build_query($params);
    }

    //$params: array => multipart/form-data, string => application/x-www-form-urlencoded
    if (!empty($params)) {
      curl_setopt($this->handle, CURLOPT_POSTFIELDS, $params);
    }

    if (!empty($headers)) {
      curl_setopt($this->handle, CURLOPT_HTTPHEADER, $headers);
    }
  }

  /**
   * @param array $options
   * @return Response
   */
  public function exec($options = [])
  {
    $this->metaData = [];
    if ($this->multi) {
      $responseStr = curl_multi_getcontent($this->handle);
    } else {
      $responseStr = curl_exec($this->handle);
    }

    foreach ($options as $option) {
      switch ($option) {
        case CURLINFO_FILETIME:
          $fileTime = curl_getinfo($this->handle, CURLINFO_FILETIME);
          $this->metaData['fileTime'] = $fileTime != -1 ? date('Y-m-d H:i:s', $fileTime) : null;
          break;
        case CURLINFO_REDIRECT_COUNT:
          $this->metaData['redirectCount'] = curl_getinfo($this->handle, CURLINFO_REDIRECT_COUNT);
          break;
        case CURLINFO_TOTAL_TIME:
          $this->metaData['totalTime'] = curl_getinfo($this->handle, CURLINFO_TOTAL_TIME);
          break;
        case CURLINFO_NAMELOOKUP_TIME:
          $this->metaData['nameLookupTime'] = curl_getinfo($this->handle, CURLINFO_NAMELOOKUP_TIME);
          break;
        case CURLINFO_CONNECT_TIME:
          $this->metaData['connectTime'] = curl_getinfo($this->handle, CURLINFO_CONNECT_TIME);
          break;
        case CURLINFO_PRETRANSFER_TIME:
          $this->metaData['preTransferTime'] = curl_getinfo($this->handle, CURLINFO_PRETRANSFER_TIME);
          break;
        case CURLINFO_REDIRECT_TIME:
          $this->metaData['redirectTime'] = curl_getinfo($this->handle, CURLINFO_REDIRECT_TIME);
          break;
        case CURLINFO_STARTTRANSFER_TIME:
          $this->metaData['startTransferTime'] = curl_getinfo($this->handle, CURLINFO_STARTTRANSFER_TIME);
          break;
        case CURLINFO_REDIRECT_URL:
          $this->metaData['redirectUrl'] = curl_getinfo($this->handle, CURLINFO_REDIRECT_URL);
          break;
        case CURLINFO_EFFECTIVE_URL:
          $this->metaData['redirectEffectiveUrl'] = curl_getinfo($this->handle, CURLINFO_EFFECTIVE_URL);
          break;
        case CURLINFO_PRIMARY_IP:
          $this->metaData['ipAddress'] = curl_getinfo($this->handle, CURLINFO_PRIMARY_IP);
          break;
        case CURLINFO_SPEED_DOWNLOAD:
          $this->metaData['speedDownload'] = curl_getinfo($this->handle, CURLINFO_SPEED_DOWNLOAD);
          break;
        case CURLINFO_SPEED_UPLOAD:
          $this->metaData['speedUpload'] = curl_getinfo($this->handle, CURLINFO_SPEED_UPLOAD);
          break;
        case CURLINFO_SSL_VERIFYRESULT:
          $this->metaData['sslVerifyResult'] = curl_getinfo($this->handle, CURLINFO_SSL_VERIFYRESULT);
          break;
        case CURLINFO_SSL_ENGINES:
          $this->metaData['sslEngine'] = curl_getinfo($this->handle, CURLINFO_SSL_ENGINES);
          break;
        case CURLINFO_CONTENT_TYPE:
          $this->metaData['contentType'] = curl_getinfo($this->handle, CURLINFO_CONTENT_TYPE);
          break;
        case CURLINFO_LOCAL_IP:
          $this->metaData['localIp'] = curl_getinfo($this->handle, CURLINFO_LOCAL_IP);
          break;
        case CURLINFO_LOCAL_PORT:
          $this->metaData['localPort'] = curl_getinfo($this->handle, CURLINFO_LOCAL_PORT);
          break;
        case CURLINFO_SIZE_UPLOAD:
          $this->metaData['sizeUpload'] = curl_getinfo($this->handle, CURLINFO_SIZE_UPLOAD);
          break;
        case CURLINFO_SIZE_DOWNLOAD:
          $this->metaData['sizeDownload'] = curl_getinfo($this->handle, CURLINFO_SIZE_DOWNLOAD);
          break;
      }
    }

    $this->metaData['errno'] = curl_errno($this->handle);
    $this->metaData['errstr'] = curl_error($this->handle);//Fix: curl_errno() always return 0 when fail
    $this->metaData['url'] = curl_getinfo($this->handle, CURLINFO_EFFECTIVE_URL);
    $this->metaData['code'] = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);

    if (in_array($this->metaData['code'], [301, 302])) {
      if ($this->metaData['redirectCount'] == $this->maxRedirectCount) {
        return $this->response;
      }

      if ($this->method === 'GET') {
        $this->response = $this->makeGet($this->metaData['url']);
        
      } elseif ($this->method = 'POST') {
        $this->response = $this->makePost($this->metaData['url']);
      }

      $this->response = $this->exec($options);
    }

    $headerSize = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
    $this->response = Response::make($this->metaData['url'], $this->metaData['code'], $responseStr, $headerSize, [$this->metaData['errno'], $this->metaData['errstr']],);

    return $this->response;
  }

  public function getMetaData()
  {
    return $this->metaData;
  }

  public function setMaxGetSize($maxSize)
  {
    $this->maxGetSize = $maxSize;
  }

  public function setMaxRedirectCount($maxRedirectCount)
  {
    $this->maxRedirectCount = $maxRedirectCount;
  }

  public function setBufferSize($bufferSize)
  {
    $this->bufferSize = $bufferSize;
  }

  public function setMulti($isMulti)
  {
    $this->multi = (bool)$isMulti;
  }

  public function responseToFile($filename)
  {
    $folder = dirname($filename);
    if (!file_exists($folder)) {
      mkdir($folder, 0777, true);
    }
    return file_put_contents($filename, $this->getResponse()->getBody());
  }

  public function getResponse()
  {
    return $this->response;
  }

  public function getHandle()
  {
    return $this->handle;
  }

  public function __destruct()
  {
    if ($this->handle !== null) {
      curl_close($this->handle);
    }
  }
}