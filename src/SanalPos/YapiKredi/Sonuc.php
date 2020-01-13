<?php

namespace SanalPos\YapiKredi;

use Posnet;

/**
 * POSSonuc interfacei ile Yapı Kredi POS döngüleri.
 */
class Sonuc implements \SanalPos\PosSonucInterface
{
    public $posnet;

    /**
     * Verileri RAW nesnesi olarak tutmaktansa
     * Posnet nesnesi daha kullanışlı.
     *
     * @param Posnet
     */
    public function __construct(Posnet $posnet)
    {
        $this->posnet = $posnet;
    }

    public function basariliMi()
    {
        return $this->posnet->GetApprovedCode() === '1';
    }

    public function hataMesajlari()
    {
        return array(
            array(
                'kod' => $this->posnet->GetResponseCode(),
                'mesaj' => $this->posnet->GetResponseText(),
            ),
        );
    }

    public function raw()
    {
        return $this->posnet->GetResponseXMLData();
    }
}
