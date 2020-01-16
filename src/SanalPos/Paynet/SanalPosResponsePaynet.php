<?php

namespace SanalPos\Paynet;

use SanalPos\SanalPos3DResponseInterface;
use SanalPos\SanalPosResponseInterface;

class SanalPosResponsePaynet implements SanalPosResponseInterface, SanalPos3DResponseInterface
{
    /**
     * @var Sonuc
     */
    protected $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function success()
    {
        if (!$this->response || !$this->response['status']) {
            return false;
        }

        return true;
    }

    public function errors()
    {
        if (!$this->response) {
            return 'No response';
        }
        if ($this->success()) {
            return [];
        }

        return $this->response['message'];
    }

    public function threeD()
    {
        return $this->get3DHtml();
    }

    public function response()
    {
        return $this->response;
    }

    /**
     * işlemin başarılı olması durumunda, buradaki html kodu ekrana basılacak.
     * bu ekrana basılan kod, otomatik olarak 3d doğrulama sayfasına yönlendirecek bizi.
     *
     * @return string
     */
    public function get3DHtml()
    {
        return $this->response['html'];
    }
}
