<?php declare(strict_types=1);
# defs {{{
namespace SM;
use SplObjectStorage,Throwable,CURLFile;
use function
  curl_init,curl_setopt_array,curl_errno,curl_error,
  curl_strerror,curl_getinfo,curl_reset,curl_close,
  curl_multi_init,curl_multi_setopt,curl_multi_errno,
  curl_multi_strerror,curl_multi_exec,curl_multi_select,
  curl_multi_add_handle,curl_multi_remove_handle,
  curl_multi_info_read,curl_multi_getcontent,
  curl_multi_close,
  is_scalar,is_array,is_string,is_int,is_bool,
  is_file,strpos,substr,rtrim,ltrim,
  strtolower,strtoupper,strncmp,basename,
  json_encode,json_decode,json_last_error_msg,
  array_search,array_is_list,array_pop,explode,count,
  array_change_key_case,http_build_query;
use const
  CURLOPT_POST,CURLOPT_URL,CURLOPT_HTTPHEADER,
  CURLOPT_HEADER,CURLOPT_POSTFIELDS,
  CURLOPT_TIMEOUT,CURLOPT_TIMEOUT_MS,
  CURLOPT_RETURNTRANSFER,CURLOPT_CUSTOMREQUEST,
  CURLOPT_NAME,CURLOPT_NOBODY,CURLOPT_NOPROGRESS,
  CURLOPT_PROGRESSFUNCTION,CURLOPT_XFERINFOFUNCTION,
  JSON_UNESCAPED_UNICODE,JSON_INVALID_UTF8_IGNORE,
  PHP_QUERY_RFC3986,PHP_VERSION_ID,DIRECTORY_SEPARATOR;
###
require_once __DIR__.DIRECTORY_SEPARATOR.'promise.php';
# }}}
class Hurl # ::METHOD {{{
{
  # TODO: accept value-objects, like HurlRequest? naah.. or what
  # TODO: loggable HurlResponse
  const # {{{
    # data/content types
    TYPE_RAW   = 1,
    TYPE_URL   = 2,
    TYPE_JSON  = 3,
    TYPE_FORM  = 4;
  ###
  # }}}
  # basis {{{
  static ?object $ERROR=null;
  private static ?object $GEAR=null,$RQ=null;
  private function __construct()# interface only
  {}
  static function _init(): ?object
  {
    try
    {
      if (!self::$GEAR)
      {
        self::$GEAR = Loop::gear(new Hurl_Gear());
        self::$RQ = new HurlRequest([]);
      }
      $e = null;
    }
    catch (Throwable $e) {
      self::$ERROR = $e = ErrorEx::from($e);
    }
    return $e;
  }
  private static function _gear(): object
  {
    if (!self::$GEAR &&
        (($o = self::$ERROR) ||
         ($o = self::_init())))
    {
      throw $o;
    }
    return self::$GEAR;
  }
  # }}}
  static function new(array $cfg): object # {{{
  {
    try
    {
      return new Hurl_Instance(
        self::_gear(), new HurlRequest($cfg)
      );
    }
    catch (Throwable $e) {
      return ErrorEx::from($e);
    }
  }
  # }}}
  static function GET(# {{{
    string $url, array $cfg=[]
  ):object
  {
    try
    {
      $cfg['method'] = 'GET';
      $cfg['url']    = $url;
      return new Promise(new Hurl_Action(
        self::_gear(), self::$RQ, '', $cfg
      ));
    }
    catch (Throwable $e) {
      return Promise::from($e);
    }
  }
  # }}}
  static function HEAD(# {{{
    string $url, array $cfg=[]
  ):object
  {
    try
    {
      $cfg['method'] = 'HEAD';
      $cfg['url']    = $url;
      $cfg['curlopt-nobody'] = 1;
      ###
      return new Promise(new Hurl_Action(
        self::_gear(), self::$RQ, '', $cfg
      ));
    }
    catch (Throwable $e) {
      return Promise::from($e);
    }
  }
  # }}}
  static function POST(# {{{
    string $url, array $data, array $cfg=[]
  ):object
  {
    try
    {
      $cfg['method'] = 'POST';
      $cfg['url']    = $url;
      return new Promise(new Hurl_Action(
        self::_gear(), self::$RQ, $data, $cfg
      ));
    }
    catch (Throwable $e) {
      return Promise::from($e);
    }
  }
  # }}}
  static function PUT(# {{{
    string $url, array $data, array $cfg=[]
  ):object
  {
    try
    {
      $cfg['method'] = 'PUT';
      $cfg['url']    = $url;
      return new Promise(new Hurl_Action(
        self::_gear(), self::$RQ, $data, $cfg
      ));
    }
    catch (Throwable $e) {
      return Promise::from($e);
    }
  }
  # }}}
  static function DELETE(# {{{
    string $url, array $cfg=[]
  ):object
  {
    try
    {
      $cfg['method'] = 'DELETE';
      $cfg['url']    = $url;
      return new Promise(new Hurl_Action(
        self::_gear(), self::$RQ, '', $cfg
      ));
    }
    catch (Throwable $e) {
      return Promise::from($e);
    }
  }
  # }}}
}
# }}}
class HurlRequest # {{{
{
  const # {{{
    TYPE = [
      '',# no type/content
      'text/plain',# or raw/custom
      'application/x-www-form-urlencoded',
      'application/json',
      'multipart/form-data'
    ],
    TYPE_ID = [
      'raw'        => 1,
      'rawdata'    => 1,
      'text'       => 1,
      'url'        => 2,
      'urldata'    => 2,
      'urlencoded' => 2,
      'json'       => 3,
      'jsondata'   => 3,
      'form'       => 4,
      'formdata'   => 4,
    ],
    # how to encode json
    JSONENC = 0
      |JSON_INVALID_UTF8_IGNORE
      |JSON_UNESCAPED_UNICODE,
    # response/total timeout (connection + transfer)
    TIMEOUT = 10,# sec
    MAX_TIMEOUT = 300,# sec
    # default options
    CURLOPT = [
      CURLOPT_VERBOSE        => false,# debug output
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_NOPROGRESS     => true,
      CURLOPT_CONNECTTIMEOUT => 5,# default(0)
      CURLOPT_TIMEOUT        => 0,# default(0): never
      CURLOPT_FORBID_REUSE   => false,# reuse!
      CURLOPT_FRESH_CONNECT  => false,# reuse!
      CURLOPT_FOLLOWLOCATION => false,# no redirects, assume API
      CURLOPT_HEADEROPT      => CURLHEADER_SEPARATE,
      CURLOPT_HEADER         => true,# to suply headers in response
      CURLOPT_PROTOCOLS      => (# limit to http
        CURLPROTO_HTTP|CURLPROTO_HTTPS
      ),
      CURLOPT_HTTP_VERSION  => CURL_HTTP_VERSION_NONE,
      CURLOPT_PIPEWAIT      => false,# be lazy/multiplexy?
      CURLOPT_TCP_NODELAY   => true,
      CURLOPT_TCP_KEEPALIVE => 1,
      CURLOPT_TCP_KEEPIDLE  => 300,
      CURLOPT_TCP_KEEPINTVL => 300,
      ### ignition laxed
      CURLOPT_SSL_ENABLE_ALPN  => true,# negotiate to h2
      CURLOPT_SSL_VERIFYSTATUS => false,# require OCSP during TLS handshake
      CURLOPT_SSL_VERIFYHOST   => 0,# MITM guard
      CURLOPT_SSL_VERIFYPEER   => false,# self-signed cert guard
    ],
    CURLOPT_NAME = [
      CURLOPT_VERBOSE          => 'VERBOSE',
      CURLOPT_RETURNTRANSFER   => 'RETURNTRANSFER',
      CURLOPT_CONNECTTIMEOUT   => 'CONNECTTIMEOUT',
      CURLOPT_TIMEOUT          => 'TIMEOUT',
      CURLOPT_FORBID_REUSE     => 'FORBID_REUSE',
      CURLOPT_FRESH_CONNECT    => 'FRESH_CONNECT',
      CURLOPT_FOLLOWLOCATION   => 'FOLLOWLOCATION',
      CURLOPT_HEADEROPT        => 'HEADEROPT',
      CURLOPT_HEADER           => 'HEADER',
      CURLOPT_PROTOCOLS        => 'PROTOCOLS',
      CURLOPT_HTTP_VERSION     => 'HTTP_VERSION',
      CURLOPT_PIPEWAIT         => 'PIPEWAIT',
      CURLOPT_TCP_NODELAY      => 'TCP_NODELAY',
      CURLOPT_TCP_KEEPALIVE    => 'TCP_KEEPALIVE',
      CURLOPT_TCP_KEEPIDLE     => 'TCP_KEEPIDLE',
      CURLOPT_TCP_KEEPINTVL    => 'TCP_KEEPINTVL',
      CURLOPT_SSL_ENABLE_ALPN  => 'SSL_ENABLE_ALPN',
      CURLOPT_SSL_VERIFYSTATUS => 'SSL_VERIFYSTATUS',
      CURLOPT_SSL_VERIFYHOST   => 'SSL_VERIFYHOST',
      CURLOPT_SSL_VERIFYPEER   => 'SSL_VERIFYPEER',
      ###
      CURLOPT_URL           => 'URL',
      CURLOPT_HTTPHEADER    => 'HTTPHEADER',
      CURLOPT_CUSTOMREQUEST => 'CUSTOMREQUEST',
      CURLOPT_POST          => 'POST',
      CURLOPT_POSTFIELDS    => 'POSTFIELDS',
      CURLOPT_NOBODY        => 'NOBODY',
    ];
  ###
  # }}}
  # basis {{{
  public array  $options,$headers;
  public string $url,$method;
  public int    $type=0,$timeout;
  public bool   $polling,$progress;
  function __construct(array $cfg) {
    $this->set($cfg);
  }
  function __debugInfo(): array
  {
    return [];# TODO
  }
  # }}}
  # stasis {{{
  static function _options(array $cfg, array $def): array # {{{
  {
    # PRIMARY SOURCE: NESTED ARRAY
    $o = [];
    if (isset($cfg[$k = 'options']) ||
        isset($cfg[$k = 'curl']) ||
        isset($cfg[$k = 'curl-options']))
    {
      if (!is_array($cfg[$k]))
      {
        throw ErrorEx::fail(__CLASS__,
          "`".$k."` is not an array"
        );
      }
      foreach ($cfg[$k] as $i => $v)
      {
        if (!is_int($i))
        {
          throw ErrorEx::fail(__CLASS__,
            "`".$k."` is not an array of integers\n".
            "`CURLOPT_*` keys must be of integer type"
          );
        }
        $o[$i] = $v;
      }
    }
    # SECONDARY SOURCE: SHORTHANDS
    foreach ($cfg as $k => $v)
    {
      if (strncmp($k, 'curlopt-', 8)) {
        continue;
      }
      $i = array_search(
        strtoupper(substr($k, 8)),
        self::CURLOPT_NAME, true
      );
      if (!$i)
      {
        throw ErrorEx::fail(__CLASS__,
          "`".$k."` is not recognized"
        );
      }
      $o[$i] = $v;
    }
    # VALIDATION
    if (isset($o[CURLOPT_TIMEOUT]) ||
        isset($o[CURLOPT_TIMEOUT_MS]))
    {
      throw ErrorEx::fail(__CLASS__,
        "`CURLOPT_TIMEOUT` is not appropriate\n".
        "specify `timeout` instead"
      );
    }
    if (PHP_VERSION_ID > 80200)
    {
      if (isset($o[CURLOPT_XFERINFOFUNCTION]))
      {
        throw ErrorEx::fail(__CLASS__,
          "`CURLOPT_XFERINFOFUNCTION`".
          " is not appropriate\n".
          "specify `progress` instead"
        );
      }
    }
    if (isset($o[CURLOPT_PROGRESSFUNCTION]))
    {
      throw ErrorEx::fail(__CLASS__,
        "`CURLOPT_PROGRESSFUNCTION`".
        " is not appropriate\n".
        "specify `progress` instead"
      );
    }
    # DEFAULTS
    foreach ($def as $i => $v) {
      isset($o[$i]) || $o[$i] = $v;
    }
    return $o;
  }
  # }}}
  static function _headers(# {{{
    array $cfg, array $def=[], bool &$new=false
  ):array
  {
    if (!isset($cfg[$k = 'headers'])) {
      return $def;
    }
    if (!is_array($cfg[$k]))
    {
      throw ErrorEx::fail(__CLASS__,
        "`".$k."` is not an array"
      );
    }
    $a = array_is_list($a = $cfg[$k])
      ? self::headers_unpack($a)
      : array_change_key_case($a, \CASE_LOWER);
    ###
    foreach ($def as $k => $v) {
      isset($a[$k]) || $a[$k] = $v;
    }
    $new = true;
    return $a;
  }
  # }}}
  static function _method(# {{{
    array $cfg, string $def=''
  ):string
  {
    if (!isset($cfg[$k = 'method'])) {
      return $def;
    }
    if (!is_string($cfg[$k]))
    {
      throw ErrorEx::fail(__CLASS__,
        "`method` is not a string"
      );
    }
    return strtoupper($cfg[$k]);
  }
  # }}}
  static function _url(# {{{
    array $cfg, string $base=''
  ):string
  {
    if (!isset($cfg[$k = 'url']) &&
        !isset($cfg[$k = 'base-url']) &&
        !isset($cfg[$k = 'base-uri']))
    {
      return $base;
    }
    if (!is_string($cfg[$k]))
    {
      throw ErrorEx::fail(__CLASS__,
        "`".$k."` is not a string"
      );
    }
    return $base.$cfg[$k];
  }
  # }}}
  static function _timeout(# {{{
    array $cfg, int $def=self::TIMEOUT
  ):int
  {
    if (!isset($cfg[$k = 'timeout'])) {
      return $def;
    }
    if (!is_int($cfg[$k]))
    {
      throw ErrorEx::fail(__CLASS__,
        "`".$k."` is not an integer"
      );
    }
    if (($i = $cfg[$k]) < 1)
    {
      throw ErrorEx::fail(__CLASS__,
        "`".$k."` must start from 1 second"
      );
    }
    if ($i > static::MAX_TIMEOUT)
    {
      throw ErrorEx::fail(__CLASS__,
        "`".$k."` must not exeed".
        " ".static::MAX_TIMEOUT." seconds"
      );
    }
    return $i;
  }
  # }}}
  static function _polling(# {{{
    array $cfg, bool $def=false
  ):bool
  {
    if (!isset($cfg[$k = 'polling']) &&
        !isset($cfg[$k = 'long-polling']))
    {
      return $def;
    }
    if (!is_bool($cfg[$k]))
    {
      throw ErrorEx::fail(__CLASS__,
        "`".$k."` is not a boolean"
      );
    }
    return $cfg[$k];
  }
  # }}}
  static function _progress(# {{{
    array $cfg, bool $def=false
  ):bool
  {
    if (!isset($cfg[$k = 'progress'])) {
      return $def;
    }
    if (!is_bool($cfg[$k]))
    {
      throw ErrorEx::fail(__CLASS__,
        "`".$k."` is not a boolean"
      );
    }
    return $cfg[$k];
  }
  # }}}
  static function _type(array $cfg): int # {{{
  {
    if (!isset($cfg[$k = 'type']) &&
        !isset($cfg[$k = 'data-type']) &&
        !isset($cfg[$k = 'content-type']))
    {
      return 0;
    }
    if (is_int($v = $cfg[$k]))
    {
      if (!isset(self::TYPE[$v]))
      {
        throw ErrorEx::fail(__CLASS__,
          "`".$k."` is incorrect\n".
          "'unknown type index=".$v
        );
      }
      return $v;
    }
    if (!is_string($v))
    {
      throw ErrorEx::fail(__CLASS__,
        "`".$k."` is not a string/integer"
      );
    }
    if (!isset(self::TYPE_ID[$v]))
    {
      throw ErrorEx::fail(__CLASS__,
        "`".$k."` is incorrect\n".
        "'".$v."' is not recognized"
      );
    }
    return self::TYPE_ID[$v];
  }
  # }}}
  static function _content_type(array|string $data): int # {{{
  {
    # check falsy/empty, "0" bug? aaa heckit
    if (!$data) {
      return 0;
    }
    # check compound
    if (is_array($data))
    {
      # assume urldata
      $i = 2;
      # check has a file inside (at the first level)
      foreach ($data as $v)
      {
        if ($v instanceof CURLFile)
        {
          $i = 4;# nah, it is a formdata
          break;
        }
        if ($i === 2 && !is_scalar($v)) {
          $i = 3;# its jsondata
        }
      }
      return $i;
    }
    # plaintext
    return 1;
  }
  # }}}
  static function _jsonenc(array $data): string # {{{
  {
    $json = json_encode($data, static::JSONENC);
    if ($json === false)
    {
      throw ErrorEx::fail(__CLASS__,
        'json_encode', json_last_error_msg()
      );
    }
    return $json;
  }
  # }}}
  static function _urlenc(array $data): string # {{{
  {
    return http_build_query(
      $data, '', null, PHP_QUERY_RFC3986
    );
  }
  # }}}
  static function options_info(array $o): array # {{{
  {
    $info = [];
    foreach ($o as $i => $v)
    {
      $k = isset(self::CURLOPT_NAME[$i])
        ? self::CURLOPT_NAME[$i]
        : '#'.$i;
      ###
      $info[$k] = $v;
    }
    return $info;
  }
  # }}}
  static function headers_unpack(array $h): array # {{{
  {
    for ($a=[],$i=0,$j=count($h); $i < $j; ++$i)
    {
      $b = $h[$i];
      if ($k = strpos($b, ':', 1))
      {
        $c = strtolower(rtrim(substr($b, 0, $k)));
        $d = ltrim(substr($b, $k + 1));
        $a[$c] = $d;
      }
    }
    return $a;
  }
  # }}}
  static function headers_pack(array $h): array # {{{
  {
    $a = [];
    foreach ($h as $k => $v) {
      $v && $a[] = $k.': '.$v;
    }
    return $a;
  }
  # }}}
  static function type_id(string $name): int # {{{
  {
    return isset(self::TYPE_ID[$name])
      ? self::TYPE_ID[$name]
      : 0;
  }
  # }}}
  # }}}
  function set(array $cfg): void # {{{
  {
    # parse and validate configuration,
    # get options and headers
    $o = self::_options($cfg, static::CURLOPT);
    $h = self::_headers($cfg);
    # when headers are specified in options,
    # convert and merge them into hashmap
    if (isset($o[$i = CURLOPT_HTTPHEADER]))
    {
      if (!array_is_list($o[$i]))
      {
        throw ErrorEx::fail(__CLASS__,
          "`CURLOPT_HTTPHEADER` is incorrect\n".
          "it must be a list, not a hashmap\n".
          "use `headers` instead"
        );
      }
      $a = self::headers_unpack($o[$i]);
      foreach ($a as $k => $v) {
        $h[$k] = $v;
      }
      unset($o[$i], $a);
    }
    # set type
    $k = 'content-type';
    if ($j = self::_type($cfg))
    {
      $h[$k] = self::TYPE[$j];
      $this->type = $j;
    }
    elseif (isset($h[$k]))
    {
      $i = array_search($h[$k], self::TYPE, true);
      $this->type = $i ?: 1;
    }
    # set default headers
    $h && $o[$i] = self::headers_pack($h);
    # initialize
    $this->options  = $o;
    $this->headers  = $h;
    $this->url      = self::_url($cfg);
    $this->method   = self::_method($cfg);
    $this->timeout  = self::_timeout($cfg);
    $this->polling  = self::_polling($cfg);
    $this->progress = self::_progress($cfg);
  }
  # }}}
  function get(): array # {{{
  {
    # get clean curl options
    $o = $this->options;
    unset($o[CURLOPT_TIMEOUT]);
    if (isset($o[$i = CURLOPT_HTTPHEADER])) {
      unset($o[$i]);
    }
    # store base props
    $a = [
      'options' => $o,
      'headers' => $this->headers,
    ];
    # store individual props
    $this->method && $a['method'] = $this->method;
    $this->url && $a['base-url'] = $this->url;
    $this->type && $a['type'] = $this->type;
    $this->polling && $a['polling'] = true;
    $a['timeout'] = $this->timeout;
    return $a;
  }
  # }}}
  function timeoutFrom(?array $cfg): int # {{{
  {
    return $cfg
      ? self::_timeout($cfg, $this->timeout)
      : $this->timeout;
  }
  # }}}
  function pollingFrom(?array $cfg): bool # {{{
  {
    return $cfg
      ? self::_polling($cfg, $this->polling)
      : $this->polling;
  }
  # }}}
  function progressFrom(?array $cfg): bool # {{{
  {
    return $cfg
      ? self::_progress($cfg, $this->progress)
      : $this->progress;
  }
  # }}}
  function encode(# {{{
    array|string $data, ?array $cfg
  ):array
  {
    # prepare
    static $k='content-type';
    $x = false;
    if ($cfg === null)
    {
      $o = $this->options;
      $h = $this->headers;
      $url    = $this->url;
      $method = $this->method;
      $type   = $this->type;
    }
    else
    {
      $o = self::_options($cfg, $this->options);
      $h = self::_headers($cfg, $this->headers, $x);
      $url    = self::_url($cfg, $this->url);
      $method = self::_method($cfg, $this->method);
      if (isset($cfg['type']))
      {
        # type re-definition,
        # it is always correct non-zero integer
        $type = $cfg['type'];
        $h['content-type'] = self::TYPE[$type];
        $x = true;
      }
      else {
        $type = $this->type;
      }
    }
    # check type is explicitly defined
    if ($type)
    {
      # when no data is provided
      # content type must be cleared
      if (!$data && isset($h['content-type']))
      {
        unset($h['content-type']);
        $type = 0;
        $x = true;
      }
    }
    elseif ($type = self::_content_type($data))
    {
      # with content type autodetection,
      # the header is set when data provided
      $h['content-type'] = self::TYPE[$type];
      $x = true;
    }
    # set headers
    $x && $o[CURLOPT_HTTPHEADER] =
      self::headers_pack($h);
    # encode
    switch ($type) {
    case 0:
      # request with the empty body
      $o[CURLOPT_URL] = $url;
      $method && $o[CURLOPT_CUSTOMREQUEST] = $method;
      return $o;
    case 2:
      $data = self::_urlenc($data);
      break;
    case 3:
      $data = self::_jsonenc($data);
      break;
    }
    # set request options
    $o[CURLOPT_POSTFIELDS] = $data;
    $o[CURLOPT_URL] = $url;
    if ($method === 'POST' || !$method) {
      $o[CURLOPT_POST] = true;
    }
    else {
      $o[CURLOPT_CUSTOMREQUEST] = $method;
    }
    return $o;
  }
  # }}}
}
# }}}
class HurlResponse # {{{
{
  function __construct(
    public array  $request,# CURL's options
    public array  $info,# CURL's info
    public string $content,# raw
    public array  $headers,# hashmap/unpacked
    public bool   $isCorrect,# content correctness
    public ?array $data # decoded compound content
  ) {}
}
# }}}
class HurlFile extends CURLFile # {{{
{
  # this wrap adds a single feature -
  # autoremoval of the temporary file
  # which happens on deconstruction
  public bool $isTmp;
  static function new(
    string $path,        # path to a local file
    bool   $tmp  = false,# safe default
    string $name = ''    # name of uploaded
  ):object
  {
    $o = new self(
      $path, null, $name ?: basename($path)
    );
    $o->isTmp = $tmp;
    return $o;
  }
  static function of($file): ?self
  {
    return ($file instanceof self)
      ? $file : null;
  }
  function __destruct()
  {
    # remove file when its temporary
    if ($this->isTmp && $this->name)
    {
      Fx::try_file_unlink($this->name);
      $this->name = '';
    }
  }
}
# }}}
class Hurl_Instance # {{{
{
  # basis {{{
  public array $_cfg=[];
  function __construct(
    public object $_gear,
    public object $_request
  ) {}
  # }}}
  function __invoke(# {{{
    array|string $data='', ?array $cfg=null
  ):object
  {
    try
    {
      if ($this->_cfg)
      {
        if ($cfg)
        {
          # combine
          foreach ($this->_cfg as $k => $v) {
            isset($cfg[$k]) || $cfg[$k] = $v;
          }
        }
        else {# substitute
          $cfg = $this->_cfg;
        }
        $this->_cfg = [];# reset
      }
      $r = $this->_request;
      return new Promise(new Hurl_Action(
        $this->_gear, $this->_request, $data, $cfg
      ));
    }
    catch (Throwable $e) {
      return Promise::from($e);
    }
  }
  # }}}
  function type(string $type): self # {{{
  {
    if (!($id = HurlRequest::type_id($type)))
    {
      throw ErrorEx::fatal(__CLASS__,
        "type '".$type."' is not recognized"
      );
    }
    if ($this->_request->type !== $id) {
      $this->_cfg['type'] = $id;
    }
    return $this;
  }
  # }}}
  function url(string $url): self # {{{
  {
    $this->_cfg['url'] = $url;
    return $this;
  }
  # }}}
  function method(string $method): self # {{{
  {
    $method = strtoupper($method);
    if ($this->_request->method !== $method) {
      $this->_cfg['method'] = $method;
    }
    return $this;
  }
  # }}}
  function save(): void # {{{
  {
    if ($this->_cfg)
    {
      $cfg = $this->_request->get();
      foreach ($this->_cfg as $k => $v) {
        $cfg[$k] = $v;
      }
      $this->_request->set($cfg);
      $this->_cfg = [];
    }
  }
  # }}}
}
# }}}
class Hurl_Action extends Contextable # {{{
{
  # basis {{{
  public array   $_options,$_progress;
  public ?object $_curl=null;
  public int     $_stage=1,$_t0,$_t1,$_timeout;
  public bool    $_polling;
  function __construct(
    public object $_gear,
    object $request, string|array $data, ?array $cfg
  ) {
    # get timeout and polling
    $timeout = $request->timeoutFrom($cfg);
    $polling = $request->pollingFrom($cfg);
    # check inadequate
    if ($polling && $timeout < 10)
    {
      throw ErrorEx::fail(__CLASS__,
        "incorrect timeout configuration\n".
        "long polling timeouts ".
        "must start from 10 seconds"
      );
    }
    # compose options
    $options = $request->encode($data, $cfg);
    # set progress function
    if ($request->progressFrom($cfg))
    {
      $options[CURLOPT_NOPROGRESS] = false;
      if (PHP_VERSION_ID > 80200)
      {
        /** CURLOPT_XFERINFOFUNCTION
        * introduced in CURL version 7.32.0,
        * it avoids the use of floats and
        * provides more detailed information.
        */
        $options[CURLOPT_XFERINFOFUNCTION] =
        $this->_progress(...);
      }
      else
      {
        $options[CURLOPT_PROGRESSFUNCTION] =
        $this->_progress(...);
      }
    }
    # initialize
    $this->_options = $options;
    $this->_timeout = (int)($timeout * 1000000000);
    $this->_polling = $polling;
  }
  # }}}
  function _complete(): bool # {{{
  {
    switch ($this->_stage) {
    case 1:
      # initialize
      $this->_progress = [-1,-1,-1,-1];
      $this->_t0 = $t = Loop::$HRTIME;
      $this->_t1 = $t = $t + $this->_timeout;
      # try attaching
      if ($e = $this->_gear->actionAttach($this))
      {
        $this->_stage = 0;
        $this->result->error($e);
        return true;
      }
      # proceed to the next stage
      $this->result->promiseContextSet($this, $t);
      $this->_stage = 2;
      return false;
      ###
    case 2:
      # request has timed out
      $this->_stage = 4;
      $this->_gear->actionDetach($this);
      if (!$this->_polling) {
        $this->result->fail('timeout');
      }
    default:
      $this->result->promiseNoDelay();
    }
    return true;
  }
  # }}}
  function _done(): bool # {{{
  {
    if ($this->_stage === 2 ||
        $this->_stage === 3)
    {
      $this->_gear->actionDetach($this);
    }
    $this->_stage = 0;
    $this->result->promiseContextClear($this);
    return true;
  }
  # }}}
  function _progress(# {{{
    $curl, $dtotal, $dnow, $utotal, $unow
  ):int
  {
    # check nothing has changed
    $p = &$this->_progress;
    if ($p[0] === $dtotal && $p[1] === $dnow &&
        $p[2] === $utotal && $p[3] === $unow)
    {
      return 0;
    }
    # update currents
    $p[0] = $dtotal;
    $p[1] = $dnow;
    $p[2] = $utotal;
    $p[3] = $unow;
    # wakeup to report the change
    $this->_stage = 3;
    $this->result->promiseWakeup();
    return 0;# always sunny
  }
  # }}}
  function isRunning(): bool # {{{
  {
    return (
      $this->_stage === 2 &&
      $this->_stage === 3
    );
  }
  # }}}
  function progress(): ?array # {{{
  {
    return ($this->_stage === 3)
      ? $this->_progress
      : null;
  }
  # }}}
  function cancel(): void # {{{
  {
    if ($this->_stage !== 3)
    {
      throw ErrorEx::fail(__CLASS__,
        "action is not running\n".
        "unable to cancel"
      );
    }
    $this->_cancel();
  }
  # }}}
  function resume(bool $resetTimeout=false): object # {{{
  {
    if ($this->_stage !== 3)
    {
      throw ErrorEx::fail(__CLASS__,
        "action is not running\n".
        "unable to resume"
      );
    }
    if ($resetTimeout) {
      $this->_t1 = Loop::$HRTIME + $this->_timeout;
    }
    $this->_stage = 2;
    return $this->result
      ->promiseWakeup($this->_t1)
      ->promisePrependOne($this);
  }
  # }}}
  function repeat(): object # {{{
  {
    if ($this->_stage !== 4)
    {
      throw ErrorEx::fail(__CLASS__,
        "action is not finished\n".
        "unable to repeat"
      );
    }
    $this->_stage = 1;
    return $this->result->promisePrependOne($this);
  }
  # }}}
}
# }}}
class Hurl_Gear extends Completable # {{{
{
  const # {{{
    # easy handles pool range
    MIN_CURLS=5,
    MAX_CURLS=50,
    # multi-handle defaults
    MURLOPT = [
      # CURLMOPT_PIPELINING
      3 => 2,# multiplex http2
      # CURLMOPT_MAX_CONCURRENT_STREAMS
      #16 => 1000,# default(100)
    ];
  ###
  # }}}
  # basis {{{
  public object
    $murl,$actions;
  public ?object
    $nextAction=null;# with the closest timeout
  public array
    $curls=[];# pool of instances
  public int
    $actionCnt=0,$curlCnt=0,$stage=1;
  ###
  function __construct(array $o=self::MURLOPT)
  {
    # initialize
    $this->murl    = self::murl_new($o);
    $this->actions = new SplObjectStorage();
    $this->curlCnt = $n = self::MIN_CURLS;
    # for the faster startups,
    # pre-allocate curl handles
    while ($n--) {
      $this->curls[] = self::curl_new();
    }
  }
  # }}}
  # stasis {{{
  static function murl_new(array $o): object # {{{
  {
    if (!($murl = curl_multi_init())) {
      throw ErrorEx::fail('curl_multi_init');
    }
    try
    {
      foreach ($o as $i => $v)
      {
        if (!curl_multi_setopt($murl, $i, $v))
        {
          throw ErrorEx::fail('curl_multi_setopt',
            $i, self::murl_error($murl)
          );
        }
      }
    }
    catch (Throwable $e)
    {
      curl_multi_close($murl);
      throw ErrorEx::from($e);
    }
    return $murl;
  }
  # }}}
  static function murl_error(object $murl): string # {{{
  {
    return ($e = curl_multi_errno($murl))
      ? '#'.$e.' '.curl_multi_strerror($e)
      : '';
  }
  # }}}
  static function curl_new(): object # {{{
  {
    if (!($curl = curl_init())) {
      throw ErrorEx::fail('curl_init');
    }
    return $curl;
  }
  # }}}
  static function curl_error(object $curl): string # {{{
  {
    return ($e = curl_errno($curl))
      ? '#'.$e.' '.curl_error($curl)
      : '';
  }
  # }}}
  # }}}
  function _cancel(): void # {{{
  {
    if ($this->stage)
    {
      # first clear the stage,
      # this will close curls on detachment
      $this->stage = 0;
      # cancel actions through their method
      $q = $this->actions;
      $q->rewind();
      while ($this->actionCnt) {
        $q->getInfo()->_done();
      }
      # close easy-handles
      while ($this->curlCnt--) {
        curl_close(array_pop($this->curls));
      }
      # close the multi-handle
      curl_multi_close($this->murl);
    }
  }
  # }}}
  function _complete(): bool # {{{
  {
    return match ($this->stage) {
      1 => $this->do_suspend(),
      2 => $this->do_exec(),
      3 => $this->do_select(),
      default => true
    };
  }
  # }}}
  function do_suspend(): bool # {{{
  {
    $this->result->promiseHalt();
    return false;
  }
  # }}}
  function do_exec(): bool # {{{
  {
    # spin and check failed
    $n = 0;
    if ($e = curl_multi_exec($this->murl, $n))
    {
      throw Hurl::$ERROR = ErrorEx::fatal(
        'curl_multi_exec', curl_multi_strerror($e)
      );
    }
    # determine the number of finished transfers and
    # check none is finished
    if (($m = $this->actionCnt - $n) === 0)
    {
      # enter select loop on the next tick
      $this->stage++;
      return false;
    }
    # complete actions
    do {$this->actionComplete();}
    while (--$m);
    # check nothing is running
    if ($n === 0)
    {
      $this->stage = 1;# back to stasis
      return $this->do_suspend();
    }
    # resume execution on the next tick
    return false;
  }
  # }}}
  function do_select(): bool # {{{
  {
    # check something's happened
    if ($i = curl_multi_select($this->murl, 0))
    {
      # check failed
      if ($i < 0)
      {
        throw Hurl::$ERROR = ErrorEx::fatal(
          'curl_multi_select',
          self::murl_error($this->murl)
        );
      }
      # some activity detected,
      # get back to execution
      $this->stage = 2;
      return $this->do_exec();
    }
    # inactive!
    # get the closest timeout and
    # select continuation variant
    if (!($i = $this->nextTimeout())) {
      $this->result->promiseIdle();
    }
    elseif ($i < 2*1000000000) {
      # the next tick
    }
    elseif ($i < 5*1000000000) {
      $this->result->promiseDelay(3);
    }
    elseif ($i < 10*1000000000) {
      $this->result->promiseDelay(10);
    }
    else {
      $this->result->promiseIdle();
    }
    return false;
  }
  # }}}
  function nextTimeout(): int # {{{
  {
    # checkout current
    if ($a = $this->nextAction) {
      return $a->_t1 - Loop::$HRTIME;
    }
    # determine the next action,
    # get the first non-polling action
    $q = $this->actions;
    $q->rewind();
  a1:
    # check no more actions left
    if (!$q->valid()) {
      return 0;
    }
    # skip action that is polling
    if (($a = $q->getInfo())->_polling)
    {
      $q->next();
      goto a1;
    }
    # select first action's timeout
    $t = $a->_t1;
    $q->next();
    # find the action with minimal timeout
    while ($q->valid())
    {
      if (($b = $q->getInfo())->_t1 < $t)
      {
        $t = $b->_t1;
        $a = $b;
      }
      $q->next();
    }
    # update current and complete
    $this->nextAction = $a;
    return $t - Loop::$HRTIME;
  }
  # }}}
  function actionAttach(object $action): ?object # {{{
  {
    if (!$this->stage)
    {
      return ErrorEx::fail(__CLASS__,
        "unable to start new action\n".
        "gear is not attached to the loop"
      );
    }
    try
    {
      # get an easy-handle
      $curl = null;
      if ($this->curlCnt)
      {
        $curl = array_pop($this->curls);
        $this->curlCnt--;
      }
      else {
        $curl = self::curl_new();
      }
      # initialize it
      if (!curl_setopt_array($curl, $action->_options))
      {
        throw ErrorEx::fatal('curl_setopt_array',
          self::curl_error($curl)
        );
      }
      # attach to multi-handle
      $i = curl_multi_add_handle($this->murl, $curl);
      if ($i)
      {
        throw ErrorEx::fatal('curl_multi_add_handle',
          curl_multi_strerror($i)
        );
      }
      # add to the store
      $this->actionCnt++;
      $this->actions->attach(
        $action->_curl = $curl, $action
      );
      if (!$action->_polling)
      {
        # active action, tune the loop
        Loop::yield_more();
        # update the next action
        if (!($next = $this->nextAction) ||
            $action->_timeout < $next->_timeout)
        {
          $this->nextAction = $action;
        }
      }
      # wakeup and switch to execution
      $this->result->promiseWakeup();
      $this->stage = 2;
      # complete
      $e = null;# no error
    }
    catch (Throwable $e)
    {
      $curl && curl_close($curl);
      Hurl::$ERROR = $e = ErrorEx::from($e);
    }
    return $e;
  }
  # }}}
  function actionComplete(): bool # {{{
  {
    # read info about finished transfer and
    # prepare the action
    $info   = curl_multi_info_read($this->murl);
    $curl   = $info['handle'];
    $action = $this->actions->offsetGet($curl);
    $result = $action->result->promiseWakeup();
    $action->_stage = 4;# finished
    # check failed
    if ($i = $info['result'])
    {
      $result->fail(curl_strerror($i));
      $this->actionDetach($action);
      return false;
    }
    # read transfer details
    if (!($info = curl_getinfo($curl)))
    {
      throw Hurl::$ERROR = ErrorEx::fatal(
        'curl_getinfo', self::curl_error($curl)
      );
    }
    # read content string
    $s = curl_multi_getcontent($curl);
    # no more reads ahead,
    # action could be detatched
    $this->actionDetach($action);
    # prepare response data
    $isCorrect = true;# assume correctness
    $data = null;# decoded content
    # headers should be supplied with the content,
    # separate and parse them into hashmap
    if (isset($info['header_size']) &&
        ($i = $info['header_size']))
    {
      $h = rtrim(substr($s, 0, $i));
      $s = substr($s, $i);# purify content
      $headers = HurlRequest::headers_unpack(
        explode("\r\n", $h)
      );
    }
    else {
      $headers = [];
    }
    # parse content when appropriate
    # TODO: url/form datas
    switch ($info['content_type']) {
    case 'application/json':
      # decode JSON into array
      $data = json_decode(
        $s, true, 128, JSON_INVALID_UTF8_IGNORE
      );
      if ($data === null)
      {
        # transmission itself was successful
        # but the content is incorrect -
        # this means bogus server software;
        # mark response as incorrect and
        # supply result with a warning
        $isCorrect = false;
        $result->warn(
          'json_decode', json_last_error_msg()
        );
      }
      break;
    }
    # construct and set the response
    $result->value = new HurlResponse(
      $action->_options, $info,
      $s, $headers, $isCorrect, $data
    );
    return true;
  }
  # }}}
  function actionDetach(object $action): void # {{{
  {
    # detach from the store
    $curl = $action->_curl;
    $action->_curl = null;
    $this->actions->detach($curl);
    if ($action === $this->nextAction) {
      $this->nextAction = null;
    }
    # decrement counter and
    # enter stasis when no more actions left
    if (--$this->actionCnt === 0 && $this->stage) {
      $this->stage = 1;
    }
    # revert loop tuning
    $action->_polling || Loop::yield_less();
    # detach from the multi-handle
    $i = curl_multi_remove_handle($this->murl, $curl);
    if ($i)
    {
      throw Hurl::$ERROR = ErrorEx::fatal(
        'curl_multi_remove_handle',
        curl_multi_strerror($e)
      );
    }
    # when active and in the range,
    # refurbish easy-handle, close otherwise
    if ($this->stage &&
        $this->curlCnt < self::MAX_CURLS)
    {
      curl_reset($curl);
      $this->curls[] = $curl;
      $this->curlCnt++;
    }
    else {
      curl_close($curl);
    }
  }
  # }}}
}
# }}}
return Hurl::_init();
###
