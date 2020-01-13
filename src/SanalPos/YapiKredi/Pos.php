<?php

namespace SanalPos\YapiKredi;

use SanalPos\BasePos;
use Posnet;

/**
 * Yapı Kredi için sanal POS.
 */
class Pos extends BasePos implements \SanalPos\PosInterface
{
    protected $posnet;

    /**
     * Banka ayarları.
     */
    protected $hostlar = array(
        'test' => 'https://setmpos.ykb.com/PosnetWebService/XML',
        'production' => 'https://posnet.yapikredi.com.tr/PosnetWebService/XML',
        'test_3d' => 'https://setmpos.ykb.com/3DSWebService/YKBPaymentService',
        'production_3d' => 'https://posnet.ykb.com/3DSWebService/YKBPaymentService',
    );
    protected $host;
    protected $musteriID;
    protected $terminalID;

    /**
     * Kart bilgileri.
     */
    protected $kartNo;
    protected $sonKullanmaTarihi;
    protected $cvc;

    /**
     * Sipariş bilgileri.
     */
    protected $tutar;
    protected $siparisID;
    protected $taksit;

    /**
     * Bağlantı ayarları.
     */
    public $baglantiAyarlari = array(
        'timeOut' => 30,
    );

    /**
     * Posnet nesnesinin injectionı, sanal pos bilgileri ve environment
     * belirlemek için kullanılıyor.
     *
     * @param \Posnet|\PosnetOOS $posnet
     * @param string             $musteriID
     * @param string             $terminalID
     * @param string             $environment
     */
    public function __construct($posnet, $musteriID, $terminalID, $environment = 'production')
    {
        // Posnet injection
        $this->posnet = $posnet;

        // Banka giriş verileri
        $this->musteriID = $musteriID;
        $this->terminalID = $terminalID;
        $this->host = $this->hostlar[$environment];
    }

    /**
     * Kredi kartı ayarlarını yap.
     *
     * @param string $kartNo
     * @param string $sonKullanmaTarihi
     * @param string $cvc
     */
    public function krediKartiAyarlari($kartNo, $sonKullanmaTarihi, $cvc)
    {
        $this->kartNo = $kartNo;
        $this->sonKullanmaTarihi = $sonKullanmaTarihi;
        $this->cvc = $cvc;
    }

    /**
     * Sipariş ayarlarını belirle.
     *
     * @param float  $tutar
     * @param string $siparisID
     */
    public function siparisAyarlari($tutar, $siparisID, $taksit, $extra)
    {
        $this->tutar = $tutar;
        $this->siparisID = $siparisID;
        $this->taksit = $taksit;
    }

    /**
     * Bağlantı ayarlarını düzenle.
     *
     * @param array $yeniAyarlar
     */
    public function baglantiAyarlari($yeniAyarlar)
    {
        $this->baglantiAyarlari = array_merge($this->baglantiAyarlari, $yeniAyarlar);
    }

    public static function quickRandom($length = 16)
    {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
    }
    /**
     * Ayarları yapılan ödemeyi gerçekleştir.
     *
     * @return PosSonucInterface|\PosnetOOSResponse
     */
    public function odeme()
    {
        // Kontrol yapmadan deneme yapan olabilir
        // if ( ! $this->dogrula())
        //    throw new \InvalidArgumentException;

        // Bankaya post edilecek veriler
        $kur = 'YT';

        // İşlem tutarını düzenle
        $tutar = number_format($this->tutar, 2, '', '');

        // Son kullanma tarihi formatı
        $sktAy = substr($this->sonKullanmaTarihi, 0, 2);
        $sktYil = substr($this->sonKullanmaTarihi, 4, 2);

        $this->posnet->SetURL($this->host);
        $this->posnet->SetMid($this->musteriID);
        $this->posnet->SetTid($this->terminalID);
        if ($this->posnet instanceof \PosnetOOS) {
            //$this->posnet->SetDebugLevel(1);
            $result = $this->posnet->CreateTranRequestDatas(
                'Kart holder name', //todo:
                $tutar,
                $kur,
                $this->taksit,
                $xid = $this->quickRandom(20), //todo: laravel
                $trantype = 'Sale', //sale?
                $this->kartNo,
                $sktYil.$sktAy,
                $this->cvc
            );
        } else {
            $this->posnet->DoSaleTran(
                $this->kartNo,
                $sktYil.$sktAy,
                $this->cvc,
                $this->siparisID,
                $tutar,
                $kur,
                $this->taksit
            );
        }

        if ($this->posnet instanceof \PosnetOOS) {
            return $this->posnet;
        }

        // Sonuç nesnesini oluştur
        return new Sonuc($this->posnet);
    }
}
