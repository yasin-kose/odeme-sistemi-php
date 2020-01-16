<?php

namespace SanalPos\Paynet;

use SanalPos\SanalPos3DInterface;
use SanalPos\SanalPosBase;
use SanalPos\SanalPosInterface;

class SanalPosPaynet extends SanalPosBase implements SanalPosInterface, SanalPos3DInterface
{
    protected $pub_key;
    protected $sec_key;
    protected $firma_kod;
    protected $taksit_oran;
    protected $komisyon;
    protected $taksitli;
    protected $return_url;
    protected $is3D;
    
    public function __construct($pub_key, $sec_key, $firma_kod, $taksit_oran, $komisyon, $taksitli, $is3D, $return_url)
    {
        $this->pub_key = $pub_key;
        $this->sec_key = $sec_key;
        $this->firma_kod = $firma_kod;
        $this->taksit_oran = $taksit_oran;
        $this->komisyon = $komisyon;
        $this->taksitli = $taksitli;
        $this->return_url = $return_url;
        $this->is3D = $is3D==1;
    }


    public function getServer() 
    {
    }
    /**
     * @return mixed
     */
    public function pay($pre = false, $successUrl = null, $failureUrl = null)
    {
        if ($this->is3D) {
            return $this->pay3d();
        }
        $isTest = ($this->mode == 'TEST') ? true:false;
        $paynet = new PaynetClient($this->sec_key, $isTest);
        
        $paymentParams 				    = new PaymentParameters();
        $paymentParams->amount 	        = $this->order['total'];
        $paymentParams->reference_no 	= $this->order['orderId'];
        $paymentParams->card_holder 	= $this->card['name'];
        $paymentParams->pan 	        = $this->card['number'];
        $paymentParams->month 	        = $this->card['month'];
        $paymentParams->year 	        = $this->card['year2'];
        $paymentParams->cvc 	        = $this->card['cvv'];
        $paymentParams->card_holder_mail= $this->order['email'];
        $paymentParams->description 	= $this->order['extra']['desc'];
        if($this->taksitli){
            $paymentParams->instalment 	    = $this->order['taksit']?:0;
        }
        $paymentParams->add_commission 	= $this->komisyon?:false;
        $paymentParams->ratio_code 	    = $this->taksit_oran;
        $paymentParams->agent_id 	    = $this->firma_kod;

        $result 			= $paynet->PaymentPost($paymentParams);
        $SiparisID			= $result->order_id;
        $XSiparisID			= $result->xact_id;
        $Durum				= (($result->is_succeed == true)?true:false);
        $BankaHata			= $result->bank_error_message;

        if($Durum and $this->order['email']){
            $SlipParams 				= new SlipParameters();
            $SlipParams->xact_id 	 	= $XSiparisID;
            $SlipParams->email 			= $this->order['email'];
            $SlipParams->send_mail		= true;
            $SlipResult		 			= $paynet->SlipPost($SlipParams);
        }
        return  ['status' => $Durum, 'message'=>$BankaHata];
    }

    /**
    * @return mixed
    */
    public function pay3d()
    {
        $isTest = ($this->mode == 'TEST') ? true:false;
        $paynet = new PaynetClient($this->sec_key, $isTest);
        
        $paymentParams 				= new Three3DPaymentParameters();
        $paymentParams->amount 	        = $this->order['total'];
        $paymentParams->reference_no 	= $this->order['orderId'];
        $paymentParams->card_holder 	= $this->card['name'];
        $paymentParams->pan 	        = $this->card['number'];
        $paymentParams->month 	        = $this->card['month'];
        $paymentParams->year 	        = $this->card['year2'];
        $paymentParams->cvc 	        = $this->card['cvv'];
        $paymentParams->card_holder_mail= $this->order['email'];
        $paymentParams->description 	= $this->order['extra']['desc'];
        if($this->taksitli){
            $paymentParams->instalment 	    = $this->order['taksit']?:0;
        }
        $paymentParams->add_commission 	= $this->komisyon?:false;
        $paymentParams->ratio_code 	    = $this->taksit_oran;
        $paymentParams->agent_id 	    = $this->firma_kod;
        $paymentParams->return_url 	    = $this->return_url;

        $result 		= $paynet->There3DPaymentPost($paymentParams);
        $Html			= $result->html_content;
        $Code			= $result->code;
        $Message		= $result->message;
        return  ['status' => $Code=='0', 'message'=>$Message, 'html'=>$Html];
    }

    /**
    * @return mixed
    */
    public function getTaksit($KartNumara, $Tutar=0)
    {
        $isTest = ($this->mode == 'TEST') ? true:false;
        $paynet = new PaynetClient($this->sec_key, $isTest);
        
        $ratioParams 				= new RatioParameters();
        $ratioParams->bin 	        = $KartNumara;
        $ratioParams->amount 	    = $Tutar;
        $ratioParams->addcommission_to_amount 	= $this->komisyon?:false;

        //Servisi çalıştır
        $sonuc = $paynet->GetRatios($ratioParams);
			
        return  $sonuc;
    }

    public function postAuth($orderId)
    {
    }

    public function cancel($orderId)
    {
    }

    public function refund($orderId, $amount = null)
    {
    }

    /**
     * 3d formunu ekrana bastıktan sonra kullanıcı sms doğrulamasını gireceği alana yönlendirilir.
     * SanalPos3DResponseInterface dosyasını kontrol edin.
     *
     * SMS kodunu girdikten sonra $successUrl ile belirlediğimiz adrese yönlendirilir.
     * İşte bu noktada, gelen post datayı kontrol ettikten sonra, çekim işlemini tamamlamak için
     * bu fonksiyon çalıştırılır.
     *
     * @param array $postData
     *
     * @return mixed
     */
    public function provision3d(array $postData)
    {

        $isTest = ($this->mode == 'TEST') ? true:false;
        $paynet = new PaynetClient($this->sec_key, $isTest);
        
        $chargeParams 				= new ChargeParameters();
        $chargeParams->session_id 	= $postData["session_id"];
        $chargeParams->token_id 	= $postData["token_id"];

        //Charge işlemini çalıştırır
        $result 			= $paynet->ChargePost($chargeParams);
        $SiparisID			= $result->order_id;
        $XSiparisID			= $result->xact_id;
        $Durum				= (($result->is_succeed == true)?true:false);
        $BankaHata			= $result->bank_error_message;
        $EMail				= $result->email?$result->email:'';

        if($Durum and $EMail){
            $SlipParams 				= new SlipParameters();
            $SlipParams->xact_id 	 	= $XSiparisID;
            $SlipParams->email 			= $EMail;
            $SlipParams->send_mail		= true;
            $SlipResult		 			= $paynet->SlipPost($SlipParams);
        }
        return  ['status' => $Durum, 'message'=>$Message];
    }
}
