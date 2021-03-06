<?php

namespace Saltwater\RedBean\Service;

use Saltwater\Server as S;
use Saltwater\Utils as U;
use Saltwater\Salt\Service;

class Rest extends Service
{
    public function isCallable($method)
    {
        if (parent::isCallable($method)) {
            return true;
        }

        $class = U::explodeClass($this);

        return strpos($method, array_pop($class)) !== false;
    }

    /**
     * @param object $call
     * @param mixed  $data
     *
     * @return array|int|\RedBean_OODBBean
     */
    public function call($call, $data = null)
    {
        if (parent::isCallable($call->function)) {
            return parent::call($call, $data);
        }

        return $this->restCall($call, $data);
    }

    /**
     * @param object $call
     * @param mixed  $data
     *
     * @return array|int|\RedBean_OODBBean
     */
    protected function restCall($call, $data = null)
    {
        $path = $call->method;

        if (is_numeric($call->path)) {
            $path .= '/' . $call->path;
        }

        return $this->callPath($call->http, $path, $data);
    }

    /**
     * @param string $http
     * @param string $path
     * @param mixed  $data
     */
    protected function callPath($http, $path, $data = null)
    {
        $rest = $this->restHandler();

        return $rest->handleRESTRequest($http, $path, $data);
    }

    protected function restHandler()
    {
        return new \RedBean_Plugin_BeanCan(S::$n->db($this->module));
    }
}
