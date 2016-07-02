<?php namespace DCarbone\SimpleConsulPHP\Base;

/*
   Copyright 2016 Daniel Carbone (daniel.p.carbone@gmail.com)

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

/**
 * Class AbstractConsulClient
 * @package DCarbone\SimpleConsulPHP\Base
 */
abstract class AbstractConsulClient
{
    /** @var string */
    private $_url = null;

    /** @var array */
    private $_curlOpts = array();

    /** @var array */
    private static $_defaultCurlOpts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLINFO_HEADER_OUT => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json',
        )
    );

    /**
     * AbstractConsulClient constructor.
     * @param string $url
     * @param array $curlOpts
     */
    public function __construct($url, array $curlOpts = array())
    {
        $this->_url = rtrim(trim($url), "/");
        $this->_curlOpts = $curlOpts + self::$_defaultCurlOpts;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * @return array
     */
    public function getCurlOpts()
    {
        return $this->_curlOpts;
    }

    /**
     * @param int $opt
     * @param mixed $value
     * @return static
     */
    public function setCurlOpt($opt, $value)
    {
        if (!is_int($opt))
        {
            throw new \InvalidArgumentException(sprintf(
                '%s::setCurlOpt - First argument must be integer that corresponds with a valid PHP CURL Option, %s seen.',
                get_class($this),
                gettype($opt)
            ));
        }

        // Don't let them override this.
        if (CURLOPT_RETURNTRANSFER === $opt)
            return $this;

        // Don't let them override this.
        if (CURLINFO_HEADER_OUT === $opt)
            return $this;

        $this->_curlOpts[$opt] = $value;

        return $this;
    }

    /**
     * @return static
     */
    public function resetCurlOpt()
    {
        $this->_curlOpts = self::$_defaultCurlOpts;
        return $this;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param QueryParameters $parameters
     * @return array|null
     */
    protected function execute($method, $uri, QueryParameters $parameters = null)
    {
        if (!is_string($method)) {
            throw new \InvalidArgumentException(sprintf('%s - Method must be string', get_class($this), gettype($method)));
        }

        if ('' === ($method = trim($method))) {
            throw new \InvalidArgumentException(sprintf('%s - Method must be non-empty string', get_class($this)));
        }

        switch(strtolower($method))
        {
            case 'get':
                $url = $this->_GET($uri, $parameters);
                break;
            case 'put':
                $url = $this->_PUT($uri, $parameters);
                break;
            case 'delete':
                $url = $this->_DELETE($uri, $parameters);
                break;

            default:
                throw new \UnexpectedValueException(sprintf(
                    '%s - SimpleConsulPHP currently does not support queries made using the "%s" method.',
                    get_class($this),
                    $method
                ));
        }

        $ch = curl_init($url);

        if (!curl_setopt_array($ch, $this->_curlOpts))
        {
            throw new \DomainException(sprintf(
                '%s - Unable to set specified Curl options, please ensure you\'re passing in valid constants.  Specified options: %s',
                get_class($this),
                json_encode($this->_curlOpts)
            ));
        }

        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        return $this->parseResponse($url, $data, $info);
    }

    /**
     * @param string $url
     * @param string|bool $data
     * @param array $info
     * @return array|null
     */
    protected function parseResponse($url, $data, $info)
    {
        if (is_string($data))
        {
            if (200 === $info['http_code'])
            {
                $data = @json_decode($data, true);
                $err = json_last_error();

                if (JSON_ERROR_NONE === $err)
                    return $data;

                throw new \DomainException(sprintf(
                    '%s - Unable to parse response as JSON.  Message: %s',
                    get_class($this),
                    PHP_VERSION_ID >= 50500 ? json_last_error_msg() : (string)$err
                ));
            }

            if (404 === $info['http_code'])
                return null;

            if ('' === $data || false === $data)
            {
                throw new \UnexpectedValueException(sprintf(
                    '%s - Error seen while executing.  Response code: %d',
                    get_class($this),
                    $info['http_code']
                ));
            }

            throw new \UnexpectedValueException(sprintf(
                '%s - Error seen while executing "%s": %s.',
                get_class($this),
                $url,
                $data
            ));
        }

        throw new \UnexpectedValueException(sprintf(
            '%s - Invalid response seen executing query "%s".',
            get_class($this),
            $url
        ));
    }

    /**
     * @param string $uri
     * @param QueryParameters $parameters
     * @return string
     */
    private function _GET($uri, QueryParameters $parameters = null)
    {
        unset($this->_curlOpts[CURLOPT_CUSTOMREQUEST]);
        unset($this->_curlOpts[CURLOPT_POST]);
        unset($this->_curlOpts[CURLOPT_POSTFIELDS]);

        $this->_curlOpts[CURLOPT_HTTPGET] = true;

        if (null === $parameters)
            return $this->_buildRootUrl($uri);

        return sprintf('%s?%s', $this->_buildRootUrl($uri), $parameters->queryString());
    }

    /**
     * @param string $uri
     * @param QueryParameters $parameters
     * @return string
     */
    private function _PUT($uri, QueryParameters $parameters = null)
    {
        unset($this->_curlOpts[CURLOPT_POST]);
        unset($this->_curlOpts[CURLOPT_HTTPGET]);

        $this->_curlOpts[CURLOPT_CUSTOMREQUEST] = 'PUT';

        if (null !== $parameters)
            $this->_curlOpts[CURLOPT_POSTFIELDS] = json_encode($parameters);

        return $this->_buildRootUrl($uri);
    }

    /**
     * @param string $uri
     * @param QueryParameters $parameters
     * @return string
     */
    private function _DELETE($uri, QueryParameters $parameters = null)
    {
        unset($this->_curlOpts[CURLOPT_POST]);
        unset($this->_curlOpts[CURLOPT_HTTPGET]);

        $this->_curlOpts[CURLOPT_CUSTOMREQUEST] = 'DELETE';

        if (null !== $parameters)
            $this->_curlOpts[CURLOPT_POSTFIELDS] = json_encode($parameters);

        return $this->_buildRootUrl($uri);
    }

    /**
     * @param string $uri
     * @return string
     */
    private function _buildRootUrl($uri)
    {
        return sprintf('%s/%s', $this->_url,  ltrim(trim($uri), "/"));
    }
}