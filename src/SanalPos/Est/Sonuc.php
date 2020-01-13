<?php

namespace SanalPos\Est;

/**
 * POSSonuc interfacei ile Yapı Kredi POS döngüleri.
 */
class Sonuc implements \SanalPos\PosSonucInterface
{
    public $estDongu;

    /**
     * Est'den gelen sonuç dizisini kaydet.
     *
     * @param string $estDongu
     */
    public function __construct($estDongu)
    {
        $this->estDongu = $estDongu;
    }

    public function basariliMi()
    {
        return intval($this->estDongu['return_code']) == 0 and !empty($this->estDongu['orderid']);
    }

    public function hataMesajlari()
    {
        return array(
            array(
                'kod' => $this->estDongu['return_code'],
                'mesaj' => $this->estDongu['error_msg'],
            ),
        );
    }

    public function raw()
    {
        return json_encode($this->estDongu);
    }
}
