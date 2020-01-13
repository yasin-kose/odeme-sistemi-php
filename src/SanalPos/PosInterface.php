<?php

namespace SanalPos;

/**
 * Sanal POS Interface.
 *
 * Bundan sonra yazacağım POS'lara kalıp olması açısından bu projede dahil ediyorum.
 */
interface PosInterface
{
    /**
     * Kredi kartı ayarları.
     *
     * @param int $kartNo
     * @param int $sonKullanmaTarihi (MMYY)
     * @param int $cvc
     */
    public function krediKartiAyarlari($kartNo, $sonKullanmaTarihi, $cvc);

    /**
     * Sipariş ayarları.
     *
     * @param float  $miktar
     * @param string $siparisID
     */
    public function siparisAyarlari($miktar, $siparisID, $taksit, $extra);

    /**
     * Girilen kredi kartı gibi verilerin bankaya göndermeden önce doğrulaması.
     *
     * @return bool
     */
    public function dogrula();

    /*
     * Doğrulamadan sonra kullanılacak methodlar
     */

    /*
     * Verileri bankaya gönderecek
     *
     * @return POSSonuc
     */
    public function odeme();

    /*
     * Sonradan eklenebilecek özellikler
     *
     * public function preAuth();
     * public function iade();
     * public function iptal();
     * public function siparisDetaylari();
     */
}
