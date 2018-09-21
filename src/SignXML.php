<?php

namespace XMLSunat;

use DOMDocument;
use Greenter\XMLSecLibs\Certificate\X509Certificate;
use Greenter\XMLSecLibs\Certificate\X509ContentType;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use Greenter\XMLSecLibs\XMLSecurityKey;

class SignXML extends SignedXml
{
    public function customSignXML($pathXML)
    {
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = false;
        $xml->formatOutput = true;
        $xml->loadXML(file_get_contents($pathXML));

        $this->customSign($xml);

        $xml->saveXML();
        $xml->save($pathXML);
    }

    protected function customSign(DOMDocument $data)
    {
        if (null === $this->privateKey) {
            throw new RuntimeException(
                'Missing private key. Use setPrivateKey to set one.'
            );
        }

        $objKey = new XMLSecurityKey(
            $this->keyAlgorithm,
            [
                'type' => 'private',
            ]
        );
        $objKey->loadKey($this->privateKey);

        $objXMLSecDSig = $this->createXmlSecurityDSig();
        $objXMLSecDSig->setCanonicalMethod($this->canonicalMethod);
        $objXMLSecDSig->addReference($data, $this->digestAlgorithm, [self::ENVELOPED], ['force_uri' => true]);
        $objXMLSecDSig->sign($objKey, $this->getCustomNodeSign($data));

        /* Add associated public key */
        if ($this->getPublicKey()) {
            $objXMLSecDSig->add509Cert($this->getPublicKey(), true, false, ['subjectName' => true]);
        }
    }

    protected function getCustomNodeSign(DOMDocument $data)
    {
        $els = $data->getElementsByTagNameNS(
            self::EXT_NS,
            'ExtensionContent');

        $nodeSign = null;
        foreach ($els as $element) {
            /** @var \DOMElement $element*/
            $val = $element->nodeValue;
            if (strlen(trim($val)) === 0) {
                $nodeSign = $element;
                break;
            }
        }

        if ($nodeSign == null) {
            $nodeSign = $data->documentElement;
        }

        return $nodeSign;
    }

    public function convertPfxToPem($pathCert)
    {
        $pfx = file_get_contents($pathCert);
        $password = 'develop';

        $certificate = new X509Certificate($pfx, $password);
        $pem = $certificate->export(X509ContentType::PEM);

        file_put_contents(str_replace('pfx', 'pem', $pathCert), $pem);
    }
}