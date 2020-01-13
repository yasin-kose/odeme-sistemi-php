<?php

namespace SanalPos\Finansbank;

use SanalPos\SanalPos3DInterface;
use SanalPos\SanalPosBase;
use SanalPos\SanalPosInterface;
use DOMDocument;

class SanalPosFinansbank extends SanalPosBase implements SanalPosInterface, SanalPos3DInterface
{
    protected $xml;
    protected $merchantId;
    protected $banks = [
        'finansbank' => 'https://vpos.qnbfinansbank.com/Gateway/XmlGate.aspx',
        'finansbank_3d' => 'https://vpos.qnbfinansbank.com/Gateway/XmlGate.aspx',
    ];

    protected $testServer = 'https://vpostest.qnbfinansbank.com/Gateway/XmlGate.aspx';
    protected $testServer3d = 'https://vpostest.qnbfinansbank.com/Gateway/XmlGate.aspx';
    /**
     * @var
     */
    private $bank;
    private $username;
    private $password;
    private $successUrl;
    private $failureUrl;
    private $storeKey;

    /**
     * SanalPosFinansbank constructor.
     *
     * @param $bank Finansbank|Finansbank_3d
     * @param $merchantId
     * @param $username
     * @param $password
     *
     * @throws \Exception
     */
    public function __construct(
        $bank,
        $merchantId,
        $username,
        $password,
        $storeKey
    ) {
        if (!array_key_exists($bank, $this->banks)) {
            throw new \Exception('Bilinmeyen Banka');
        } else {
            $this->server = $this->banks[$bank];
        }
        $this->merchantId = $merchantId;
        $this->username = $username;
        $this->password = $password;
        $this->bank = $bank;
        $this->storeKey = $storeKey;
    }

    public function getServer()
    {
        if ('finansbank' === $this->bank) {
            $this->server = 'TEST' == $this->mode ? $this->testServer : $this->banks['finansbank'];
        } elseif ('finansbank_3d' === $this->bank) {
            $this->server = 'TEST' == $this->mode ? $this->testServer3d : $this->banks['finansbank_3d'];
        }

        return $this->server;
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
     * @param bool        $pre     
     * @param null|string $successUrl yalnızca 3d ödeme yapılacaksa gerekli
     * @param null|string $failureUrl yalnızca 3d ödeme yapılacaksa gerekli
     *
     * @return mixed
     */
    public function pay($pre = false, $successUrl = null, $failureUrl = null)
    {
        if ($this->bank === 'vakifbank_3d') {
            return $this->send3d($successUrl, $failureUrl);
        }

        $this->order['total']   = (int) $this->order['total'] * 100;
        $orderId                = $this->order['orderId'];
        $rnd                    = microtime(); 
        $storekey               = $this->storeKey;  //isyeri anahtari
        $MbrId                  = "5";                                                                    
        $TxnType                = "Auth";       
        $hashstr                = $MbrId . $orderId . $this->order['total'] . $successUrl . $failureUrl . $TxnType . ($this->order['taksit'] ?$this->order['taksit']: 0) . $rnd . $storekey;
        $HashData               = base64_encode(pack('H*',sha1($hashstr)));
        $expiryYear             = 4 === strlen($this->card['year']) ? substr($this->card['year'], 2, 2) : $this->card['year'];
        $expiryMonth            = 1 === strlen($this->card['month']) ? '0'.$this->card['month'] : $this->card['month'];

        $dom = new DOMDocument('1.0', 'ISO-8859-9');
        $root = $dom->createElement('PayforRequest');
        $x['MbrId'] = $dom->createElement('MbrId', $MbrId);
        $x['MerchantId'] = $dom->createElement('MerchantId', $this->merchantId);
        $x['UserCode'] = $dom->createElement('UserCode', $this->username);
        $x['UserPass'] = $dom->createElement('UserPass', $this->password);
        $x['SecureType'] = $dom->createElement('SecureType', 'SecureType');
        $x['TxnType'] = $dom->createElement('TxnType', $TxnType);
        $x['PurchAmount'] = $dom->createElement('PurchAmount', $this->order['total']);
        $x['Currency'] = $dom->createElement('Currency', 949); //TODO: set currencycode parameter
        $x['OrderId'] = $dom->createElement('OrderId', $this->order['orderId']);
        $x['InstallmentCount'] = $dom->createElement('InstallmentCount', ($this->order['taksit'] ?$this->order['taksit']: 0));
        $x['Pan'] = $dom->createElement('Pan', $this->card['number']);
        $x['Cvv2'] = $dom->createElement('Cvv2', $this->card['cvv']);
        $x['Expiry'] = $dom->createElement('Expiry', $expiryMonth.$expiryYear);
        $x['Hash'] = $dom->createElement('Hash', $HashData);
        $x['Rnd'] = $dom->createElement('Rnd', $rnd);
        $x['Lang'] = $dom->createElement('Lang', 'TR');

        foreach ($x as $node) {
            $root->appendChild($node);
        }
        $dom->appendChild($root);
        $this->xml = $dom->saveXML();

        return $this->send();
    }

    public function send()
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/xml', 'Content-length: '.strlen($xml)));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_URL, $this->getServer());
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        try {
            $response = @simplexml_load_string($data);
            if (!$response) {
                return [
                    'success' => false,
                    'message' => 'XML Hatası',
                ];
            }
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
        return $response;
    }

    /**
    * @param $successUrl
    * @param $failureUrl
    *
    * @return mixed
    */
    private function send3d($successUrl, $failureUrl)
    {
        $this->order['total']   = (int) $this->order['total'] * 100;
        $orderId                = $this->order['orderId'];
        $rnd                    = microtime(); 
        $storekey               = $this->storeKey;  //isyeri anahtari
        $MbrId                  = "5";                                                                    
        $TxnType                = "Auth";       
        $hashstr                = $MbrId . $orderId . $this->order['total'] . $successUrl . $failureUrl . $TxnType . ($this->order['taksit'] ?$this->order['taksit']: 0) . $rnd . $storekey;
        $HashData               = base64_encode(pack('H*',sha1($hashstr)));
        $expiryYear             = 4 === strlen($this->card['year']) ? substr($this->card['year'], 2, 2) : $this->card['year'];
        $expiryMonth            = 1 === strlen($this->card['month']) ? '0'.$this->card['month'] : $this->card['month'];

        $dom = new DOMDocument('1.0', 'ISO-8859-9');
        $root = $dom->createElement('PayforRequest');
        $x['MbrId'] = $dom->createElement('MbrId', $MbrId);
        $x['MerchantId'] = $dom->createElement('MerchantId', $this->merchantId);
        $x['PurchAmount'] = $dom->createElement('PurchAmount', $this->order['total']);
        $x['Currency'] = $dom->createElement('Currency', 949); //TODO: set currencycode parameter
        $x['OrderId'] = $dom->createElement('OrderId', $this->order['orderId']);
        $x['InstallmentCount'] = $dom->createElement('InstallmentCount', ($this->order['taksit'] ?$this->order['taksit']: 0));

        $x['TxnType'] = $dom->createElement('TxnType', $TxnType);
        $x['UserCode'] = $dom->createElement('UserCode', $this->username);
        $x['UserPass'] = $dom->createElement('UserPass', $this->password);
        $x['SecureType'] = $dom->createElement('SecureType', '3DModel');
        $x['Pan'] = $dom->createElement('Pan', $this->card['number']);
        $x['Expiry'] = $dom->createElement('Expiry', $expiryMonth.$expiryYear);
        $x['Cvv2'] = $dom->createElement('Cvv2', $this->card['cvv']);
        if ($successUrl) {
            $x['OkUrl'] = $dom->createElement('OkUrl', $successUrl);
        }
        if ($failureUrl) {
            $x['FailUrl'] = $dom->createElement('FailUrl', $failureUrl);
        }
        $x['Hash'] = $dom->createElement('Hash', $HashData);
        $x['Rnd'] = $dom->createElement('Rnd', $rnd);
        $x['Lang'] = $dom->createElement('Lang', 'TR');

        foreach ($x as $node) {
            $root->appendChild($node);
        }
        $dom->appendChild($root);
        $this->xml = $dom->saveXML();

        return $this->send();
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
		$ThreeDStatus=$postData["3DStatus"]; 
		if($ThreeDStatus!="1"){
            return [
                'success' => false,
                'message' => 'Ödeme başarısız, daha sonra tekrar deneyiniz.#1'
            ];
        }
        $requestGuid            = $postData["RequestGuid"]; 
        $orderidval             = $postData["OrderId"];  
        $payersecuritylevelval  = $postData["Eci"];       
        $payertxnidval          = $postData["PayerTxnId"];        
        $payerauthenticationcodeval = $postData["PayerAuthenticationCode"]; 

		if(!$requestGuid or !$orderidval or !$payersecuritylevelval or !$payertxnidval or !$payerauthenticationcodeval){
			return [
                'success' => false,
                'message' => 'Ödeme başarısız, daha sonra tekrar deneyiniz.#2'
            ];
		}
        $orderId = $this->order['orderId'];

       $xml = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
           <PayforRequest>
				<RequestGuid>'.$requestGuid.'</RequestGuid>
				<OrderId>'.$orderidval.'</OrderId>
				<UserCode>'.$this->username.'</UserCode>
                <UserPass>'.$this->password.'</UserPass>
				<SecureType>3DModelPayment</SecureType>
			</PayforRequest>';

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/xml', 'Content-length: '.strlen($xml)));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_URL, $this->getServer());
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        try {
            $response = @simplexml_load_string($data);
            if (!$response) {
                return [
                    'success' => false,
                    'message' => 'XML Hatası',
                ];
            }
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
        print_r($response);
        die();
		 if ($response->IsEnrolled[0]=='true' and $response->ResponseCode[0]=='00') {
				return [
					'status' => true,
					'message' => $response->ResponseMessage[0],
				];
         }else{
			  return [
                        'status' => false,
                        'message' => $response->ResponseMessage[0],
                    ];
		 }
        return $response;
    }
}
