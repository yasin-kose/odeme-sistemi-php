<?php

namespace SanalPos\Finansbank;

use SanalPos\SanalPos3DResponseInterface;
use SanalPos\SanalPosResponseInterface;
use SimpleXMLElement;

class SanalPosResponseFinansbank implements SanalPosResponseInterface, SanalPos3DResponseInterface
{
    /**
     * @var Sonuc
     */
    protected $response;
    protected $xml;
    private $is3D = false;
    /**
     * @var
     */

    public function __construct($response)
    {
        $this->response = $response;
        $this->xml = new SimpleXMLElement($response);
    }

    public function success()
    {
        if (@$this->xml->ErrMsg) {
            return $this->xml->TxnResult && $this->xml->TxnResult->__toString() === 'Success';
        }
        // if response code === '00'
        // then the transaction is approved
        // if code is anything other than '00' that means there's an error
        return (string) $this->xml->ProcReturnCode === '00';
    }

    public function errors()
    {
        if ($this->success()) {
            return [];
        }
        return $this->xml->ErrMsg;
    }

    public function threeD()
    {
        return $this->get3DHtml();
    }

    public function response()
    {
        return $this->xml;
    }

    /**
     * işlemin başarılı olması durumunda, buradaki html kodu ekrana basılacak.
     * bu ekrana basılan kod, otomatik olarak 3d doğrulama sayfasına yönlendirecek bizi.
     *
     * @return string
     */
    public function get3DHtml()
    {
        return $this->xml;
    }
}
