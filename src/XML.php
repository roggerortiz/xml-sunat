<?php

namespace XMLSunat;

use DOMDocument;
use Exception;

class XML
{
    protected $xml;
    protected $root;
    protected $nodeRoot;
    protected $types = ['01', '03', '07'];

    protected $document;
    protected $detail;

    protected $pathXML = '';
    protected $pathCert = 'certs/LLAMA-PE-CERTIFICADO-DEMO-10466652186.pem';

    public function __construct(Document $document)
    {
        $this->xml = new DomDocument('1.0', 'ISO-8859-1');

        $this->document = $document;
        $this->detail = new DocumentDetail();
        $this->pathXML = $this->GetPathXML();
    }

    protected function GetPathXML()
    {
        return "files/{$this->document->supplier->id}-{$this->document->type}-{$this->document->number}.xml";
    }

    public function Generate()
    {
        $this->Root();
        $this->UBLExtensions();
        $this->UBLVersionID();
        $this->CustomizationID();
        $this->DocumentID();
        $this->IssueDate();
        $this->InvoiceTypeCode();
        $this->DocumentCurrencyCode();
        $this->DiscrepancyResponse();
        $this->BillingReference();
        $this->Signature();
        $this->AccountingSupplierParty();
        $this->AccountingCustomerParty();
        $this->TaxTotal();
        $this->LegalMonetaryTotal();
        $this->AddDetailLines();

        $this->SaveXML();
        $this->SignXML();
    }

    protected function Root()
    {
        if(! $this->IsValidType()) {
            throw new Exception('Type of Document Not Valid', '500');
        }

        $this->SetNodeRoot();

        $this->root = $this->xml->createElementNS("urn:oasis:names:specification:ubl:schema:xsd:{$this->nodeRoot}-2", $this->nodeRoot);
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:sac', 'urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1');
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:ccts', 'urn:un:unece:uncefact:documentation:2');
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:udt', 'urn:un:unece:uncefact:data:specification:UnqualifiedDataTypesSchemaModule:2');
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:qdt', 'urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2');
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    }

    protected function IsValidType()
    {
        return in_array($this->document->type, $this->types);
    }

    protected function SetNodeRoot()
    {
        $this->nodeRoot = ($this->document->type == '07') ? 'CreditNote' : 'Invoice';
    }

    protected function SaveXML()
    {
        $this->xml->formatOutput = true;
        $this->xml->appendChild($this->root);

        $this->xml->save($this->pathXML);
    }

    protected function SignXML()
    {
        $signer = new SignXml();
        $signer->setCertificateFromFile($this->pathCert);
        $signer->customSignXML($this->pathXML);
    }

    protected function UBLExtensions() {
        $ublExtensions = $this->xml->createElement('ext:UBLExtensions');

        $ublExtensions->appendChild($this->UBLExtension(true));
        $ublExtensions->appendChild($this->UBLExtension(false));

        $this->root->appendChild($ublExtensions);
    }

    protected function UBLExtension($information)
    {
        $ublExtension = $this->xml->createElement('ext:UBLExtension');

        if ($information) {
            $extensionContent = $this->xml->createElement('ext:ExtensionContent');
            $extensionContent->appendChild($this->AdditionalInformation());
        } else {
            $extensionContent = $this->xml->createElement('ext:ExtensionContent', '');
        }

        $ublExtension->appendChild($extensionContent);

        return $ublExtension;
    }

    protected function AdditionalInformation()
    {
        $additionalInformation = $this->xml->createElement('sac:AdditionalInformation');

        $additionalInformation->appendChild($this->AdditionalMonetaryTotal('1001', $this->document->subtotal, $this->document->currency));

        if($this->document->type == '01' || $this->document->type == '03') {
            $additionalInformation->appendChild($this->AdditionalMonetaryTotal('1002', '0.00', $this->document->currency));
            $additionalInformation->appendChild($this->AdditionalMonetaryTotal('1004', '0.00', $this->document->currency));
            $additionalInformation->appendChild($this->AdditionalProperty('1000', numero_a_letras($this->document->total, 'SOLES', 'CÉNTIMOS')));
        }

        return $additionalInformation;
    }

    protected function AdditionalMonetaryTotal($id, $amount, $currency)
    {
        $additionalMonetaryTotal = $this->xml->createElement('sac:AdditionalMonetaryTotal');

        $monetaryID = $this->xml->createElement('cbc:ID', $id);

        $payableAmount = $this->xml->createElement('cbc:PayableAmount', $amount);
        $payableAmount->setAttribute('currencyID', $currency);

        $additionalMonetaryTotal->appendChild($monetaryID);
        $additionalMonetaryTotal->appendChild($payableAmount);

        return $additionalMonetaryTotal;
    }

    protected function AdditionalProperty($id, $value)
    {
        $additionalProperty = $this->xml->createElement('sac:AdditionalProperty');

        $cbcID = $this->xml->createElement('cbc:ID', $id);
        $additionalProperty->appendChild($cbcID);

        $cbcValue = $this->xml->createElement('cbc:Value', $value);
        $additionalProperty->appendChild($cbcValue);

        return $additionalProperty;
    }

    protected function UBLVersionID()
    {
        $ublVersionID = $this->xml->createElement('cbc:UBLVersionID', '2.0');

        $this->root->appendChild($ublVersionID);
    }

    protected function CustomizationID ()
    {
        $customizationID = $this->xml->createElement('cbc:CustomizationID', '1.0');

        $this->root->appendChild($customizationID);
    }

    protected function DocumentID()
    {
        $documentID = $this->xml->createElement('cbc:ID', $this->document->number);

        $this->root->appendChild($documentID);
    }

    protected function IssueDate()
    {
        $issueDate = $this->xml->createElement('cbc:IssueDate', $this->document->date);

        $this->root->appendChild($issueDate);
    }

    protected function InvoiceTypeCode()
    {
        if ($this->document->type != '01' && $this->document->type != '03') return;

        $invoiceTypeCode = $this->xml->createElement('cbc:InvoiceTypeCode', $this->document->type);

        $this->root->appendChild($invoiceTypeCode);
    }

    protected function DocumentCurrencyCode()
    {
        $documentCurrencyCode = $this->xml->createElement('cbc:DocumentCurrencyCode', $this->document->currency);

        $this->root->appendChild($documentCurrencyCode);
    }

    protected function DiscrepancyResponse()
    {
        if ($this->document->type != '07') return;

        $discrepancyResponse = $this->xml->createElement('cac:DiscrepancyResponse');

        $referenceID = $this->xml->createElement('cbc:ReferenceID', $this->document->numberWrong);
        $responseCode = $this->xml->createElement('cbc:ResponseCode', $this->document->conceptCode);
        $description = $this->xml->createElement('cbc:Description', $this->document->concept);

        $discrepancyResponse->appendChild($referenceID);
        $discrepancyResponse->appendChild($responseCode);
        $discrepancyResponse->appendChild($description);

        $this->root->appendChild($discrepancyResponse);
    }

    protected function BillingReference()
    {
        if ($this->document->type != '07') return;

        $billingReference = $this->xml->createElement('cac:BillingReference');
        $billingReference->appendChild($this->InvoiceDocumentReference());

        $this->root->appendChild($billingReference);
    }

    protected function InvoiceDocumentReference()
    {
        $invoiceDocumentReference = $this->xml->createElement('cac:InvoiceDocumentReference');

        $documentID = $this->xml->createElement('cbc:ID', $this->document->numberWrong);
        $documentTypeCode = $this->xml->createElement('cbc:DocumentTypeCode', $this->document->typeWrong);

        $invoiceDocumentReference->appendChild($documentID);
        $invoiceDocumentReference->appendChild($documentTypeCode);

        return $invoiceDocumentReference;
    }

    protected function Signature()
    {
        $signature = $this->xml->createElement('cac:Signature');
        $signature->appendChild($this->xml->createElement('cbc:ID', 'SFB001-00000003'));
        $signature->appendChild($this->SignatoryParty());
        $signature->appendChild($this->DigitalSignatureAttachment());

        $this->root->appendChild($signature);
    }

    protected function SignatoryParty()
    {
        $signatoryParty = $this->xml->createElement('cac:SignatoryParty');
        $signatoryParty->appendChild($this->PartyIdentification());
        $signatoryParty->appendChild($this->PartyName());

        return $signatoryParty;
    }

    protected function PartyIdentification()
    {
        $partyIdentification = $this->xml->createElement('cac:PartyIdentification');
        $partyIdentification->appendChild($this->xml->createElement('cbc:ID', '20478005017'));

        return $partyIdentification;
    }

    protected function PartyName()
    {
        $partyName = $this->xml->createElement('cac:PartyName');
        $partyName->appendChild($this->xml->createElement('cbc:Name', '<![CDATA[BIZLINKS S.A.C.]]>'));

        return $partyName;
    }

    protected function DigitalSignatureAttachment()
    {
        $digitalSignatureAttachment = $this->xml->createElement('cac:DigitalSignatureAttachment');
        $digitalSignatureAttachment->appendChild($this->ExternalReference());

        return $digitalSignatureAttachment;
    }

    protected function ExternalReference()
    {
        $externalReference = $this->xml->createElement('cac:ExternalReference');
        $externalReference->appendChild($this->xml->createElement('cbc:URI', '#SFB001-00000003'));

        return $externalReference;
    }

    protected function AccountingSupplierParty()
    {
        $accountingSupplierParty = $this->xml->createElement('cac:AccountingSupplierParty');
        $accountingSupplierParty->appendChild($this->xml->createElement('cbc:CustomerAssignedAccountID', $this->document->supplier->id));
        $accountingSupplierParty->appendChild($this->xml->createElement('cbc:AdditionalAccountID', '6'));
        $accountingSupplierParty->appendChild($this->PartySupplier());

        $this->root->appendChild($accountingSupplierParty);
    }

    protected function PartySupplier()
    {
        $partyCompany = $this->xml->createElement('cac:Party');
        $partyCompany->appendChild($this->PartyNameSupplier());
        $partyCompany->appendChild($this->PostalAddressSupplier());
        $partyCompany->appendChild($this->PartyLegalEntitySupplier());

        return $partyCompany;
    }

    protected function PartyNameSupplier()
    {
        $name = $this->xml->createCDATASection($this->document->supplier->name);

        $partyNameCompany = $this->xml->createElement('cac:PartyName');
        $partyNameCompany->appendChild($this->NameSupplier());

        return $partyNameCompany;
    }

    protected function NameSupplier()
    {
        $nameSupplier = $this->xml->createElement('cbc:Name');
        $nameSupplier->appendChild($this->xml->createCDATASection($this->document->supplier->name));

        return $nameSupplier;
    }

    protected function PostalAddressSupplier()
    {
        $postalAddressCompany = $this->xml->createElement('cac:PostalAddress');
        $postalAddressCompany->appendChild($this->UbigeoID());
        $postalAddressCompany->appendChild($this->StreetName());
        $postalAddressCompany->appendChild($this->CitySubdivisionName());
        $postalAddressCompany->appendChild($this->CityName());
        $postalAddressCompany->appendChild($this->CountrySubentity());
        $postalAddressCompany->appendChild($this->District());
        $postalAddressCompany->appendChild($this->Country($this->document->supplier->codeCountry));

        return $postalAddressCompany;
    }

    protected function UbigeoID()
    {
        return $this->xml->createElement('cbc:ID', $this->document->supplier->codeUbigeo);
    }

    protected function StreetName()
    {
        $streetName = $this->xml->createElement('cbc:StreetName');
        $streetName->appendChild($this->xml->createCDATASection($this->document->supplier->address));

        return $streetName;
    }

    protected function CitySubdivisionName()
    {
        $citySubdivisionName = $this->xml->createElement('cbc:CitySubdivisionName');
        $citySubdivisionName->appendChild($this->xml->createCDATASection($this->document->supplier->province));

        return $citySubdivisionName;
    }

    protected function CityName()
    {
        $cityName = $this->xml->createElement('cbc:CityName');
        $cityName->appendChild($this->xml->createCDATASection($this->document->supplier->city));

        return $cityName;
    }

    protected function CountrySubentity()
    {
        $countrySubentity = $this->xml->createElement('cbc:CountrySubentity');
        $countrySubentity->appendChild($this->xml->createCDATASection(''));

        return $countrySubentity;
    }

    protected function District()
    {
        $district = $this->xml->createElement('cbc:District');
        $district->appendChild($this->xml->createCDATASection($this->document->supplier->district));

        return $district;
    }

    protected function PartyLegalEntitySupplier()
    {
        $partyLegalEntityCompany = $this->xml->createElement('cac:PartyLegalEntity');
        $partyLegalEntityCompany->appendChild($this->RegistrationName($this->document->supplier->name));

        return $partyLegalEntityCompany;
    }

    protected function AccountingCustomerParty()
    {
        $accountingCustomerParty = $this->xml->createElement('cac:AccountingCustomerParty');
        $accountingCustomerParty->appendChild($this->xml->createElement('cbc:CustomerAssignedAccountID', $this->document->customer->id));
        $accountingCustomerParty->appendChild($this->xml->createElement('cbc:AdditionalAccountID', $this->document->customer->type));
        $accountingCustomerParty->appendChild($this->PartyCustomer());

        $this->root->appendChild($accountingCustomerParty);
    }

    protected function PartyCustomer()
    {
        $partyCustomer = $this->xml->createElement('cac:Party');
        $partyCustomer->appendChild($this->PartyLegalEntityCustomer());

        return $partyCustomer;
    }

    protected function PartyLegalEntityCustomer()
    {
        $partyLegalEntityCustomer = $this->xml->createElement('cac:PartyLegalEntity');
        $partyLegalEntityCustomer->appendChild($this->RegistrationName($this->document->customer->name));
        $partyLegalEntityCustomer->appendChild($this->RegistrationAddress());

        return $partyLegalEntityCustomer;
    }

    protected function RegistrationName($name)
    {
        $registrationName = $this->xml->createElement('cbc:RegistrationName');
        $registrationName->appendChild($this->xml->createCDATASection($name));

        return $registrationName;
    }

    protected function RegistrationAddress()
    {
        $registrationAddress = $this->xml->createElement('cac:RegistrationAddress');
        $registrationAddress->appendChild($this->xml->createElement('cbc:StreetName', $this->document->customer->address));
        $registrationAddress->appendChild($this->Country($this->document->customer->codeCountry));

        return $registrationAddress;
    }

    protected function Country($codeCountry)
    {
        $countryCompany = $this->xml->createElement('cac:Country');
        $countryCompany->appendChild($this->xml->createElement('cbc:IdentificationCode', $codeCountry));

        return $countryCompany;
    }

    protected function TaxTotal()
    {
        $taxTotal = $this->xml->createElement('cac:TaxTotal');
        $taxTotal->appendChild($this->TaxAmount('PEN', '0.61'));
        $taxTotal->appendChild($this->TaxSubtotal());

        return $this->root->appendChild($taxTotal);
    }

    protected function TaxSubtotal()
    {
        $taxSubtotal = $this->xml->createElement('cac:TaxSubtotal');
        $taxSubtotal->appendChild($this->TaxAmount('PEN', '0.61'));
        $taxSubtotal->appendChild($this->TaxCategory());

        return $taxSubtotal;
    }

    protected function TaxCategory()
    {
        $taxCategory = $this->xml->createElement('cac:TaxCategory');
        $taxCategory->appendChild($this->TaxScheme());

        return $taxCategory;
    }

    protected function TaxScheme()
    {
        $taxScheme = $this->xml->createElement('cac:TaxScheme');
        $taxScheme->appendChild($this->xml->createElement('cbc:ID', '1000'));
        $taxScheme->appendChild($this->xml->createElement('cbc:Name', 'IGV'));
        $taxScheme->appendChild($this->xml->createElement('cbc:TaxTypeCode', 'VAT'));

        return $taxScheme;
    }

    protected function TaxAmount($codeCurrency, $amount)
    {
        $taxAmount = $this->xml->createElement('cbc:TaxAmount', $amount);
        $taxAmount->setAttribute('currencyID', $codeCurrency);

        return $taxAmount;
    }

    protected function LegalMonetaryTotal()
    {
        $legalMonetaryTotal = $this->xml->createElement('cac:LegalMonetaryTotal');
        $legalMonetaryTotal->appendChild($this->LineExtensionAmount());
        $legalMonetaryTotal->appendChild($this->TaxExclusiveAmount());
        $legalMonetaryTotal->appendChild($this->PayableAmount());

        return $this->root->appendChild($legalMonetaryTotal);
    }

    protected function LineExtensionAmount()
    {
        if ($this->document->type != '01' && $this->document->type != '03') return;

        $lineExtensionAmount = $this->xml->createElement('cbc:LineExtensionAmount', $this->document->subtotal);
        $lineExtensionAmount->setAttribute('currencyID', $this->document->currency);

        return $lineExtensionAmount;
    }

    protected function TaxExclusiveAmount()
    {
        if ($this->document->type != '01' && $this->document->type != '03') return;

        $taxExclusiveAmount = $this->xml->createElement('cbc:TaxExclusiveAmount', $this->document->igv);
        $taxExclusiveAmount->setAttribute('currencyID', $this->document->currency);

        return $taxExclusiveAmount;
    }

    protected function PayableAmount()
    {
        $payableAmount = $this->xml->createElement('cbc:PayableAmount', $this->document->total);
        $payableAmount->setAttribute('currencyID', $this->document->currency);

        return $payableAmount;
    }

    protected function AddDetailLines()
    {
        foreach ($this->document->GetDetails() as $detail) {
            $this->detail = $detail;
            $this->DetailLine();
        }
    }

    protected function DetailLine()
    {
        $detailLine = $this->xml->createElement("cac:{$this->nodeRoot}Line");
        $detailLine->appendChild($this->DetailID());
        $detailLine->appendChild($this->DetailQuantity());
        $detailLine->appendChild($this->DetailLineExtensionAmount());
        $detailLine->appendChild($this->DetailPricingReference());
        $detailLine->appendChild($this->DetailTaxTotal());
        $detailLine->appendChild($this->DetailItem());
        $detailLine->appendChild($this->DetailPrice());

        return $this->root->appendChild($detailLine);
    }

    protected function DetailID()
    {
        return $this->xml->createElement("cbc:ID", $this->detail->id);
    }

    protected function DetailQuantity()
    {
        $detailQuantity = $this->xml->createElement("cbc:{$this->nodeRoot}dQuantity", $this->detail->quantity);
        $detailQuantity->setAttribute('currencyID', $this->document->currency);

        return $detailQuantity;
    }

    protected function DetailLineExtensionAmount()
    {
        $lineExtensionAmount = $this->xml->createElement('cbc:LineExtensionAmount', $this->detail->saleValue);
        $lineExtensionAmount->setAttribute('currencyID', $this->document->currency);

        return $lineExtensionAmount;
    }

    protected function DetailPricingReference()
    {
        $pricingReference = $this->xml->createElement('cac:PricingReference');
        $pricingReference->appendChild($this->DetailAlternativeConditionPrice('01', $this->detail->priceUnit));
        $pricingReference->appendChild($this->DetailAlternativeConditionPrice('02', '0.00'));

        return $pricingReference;
    }

    protected function DetailAlternativeConditionPrice($type, $amount)
    {
        $alternativeConditionPrice = $this->xml->createElement('cac:AlternativeConditionPrice');
        $alternativeConditionPrice->appendChild($this->DetailPriceAmount($amount));
        $alternativeConditionPrice->appendChild($this->DetailPriceTypeCode($type));

        return $alternativeConditionPrice;
    }

    protected function DetailPriceAmount($amount)
    {
        $priceAmount = $this->xml->createElement('cbc:PriceAmount', $amount);
        $priceAmount->setAttribute('currencyID', $this->document->currency);

        return $priceAmount;
    }

    protected function DetailPriceTypeCode($type)
    {
        return $this->xml->createElement('cbc:PriceTypeCode', $type);
    }

    protected function DetailTaxTotal()
    {
        $taxTotal = $this->xml->createElement('cac:TaxTotal');
        $taxTotal->appendChild($this->DetailTaxAmount());
        $taxTotal->appendChild($this->DetailTaxSubtotal());

        return $taxTotal;
    }

    protected function DetailTaxAmount()
    {
        $taxAmount = $this->xml->createElement('cbc:TaxAmount', $this->detail->saleIgv);
        $taxAmount->setAttribute('currencyID', $this->document->currency);

        return $taxAmount;
    }

    protected function DetailTaxSubtotal()
    {
        $taxSubtotal = $this->xml->createElement('cac:TaxSubtotal');
        $taxSubtotal->appendChild($this->DetailTaxAmount());
        $taxSubtotal->appendChild($this->DetailPercent());
        $taxSubtotal->appendChild($this->DetailTaxCategory());

        return $taxSubtotal;
    }

    protected function DetailPercent()
    {
        return $this->xml->createElement('cbc:Percent', '18.0');
    }

    protected function DetailTaxCategory()
    {
        $taxCategory = $this->xml->createElement('cac:TaxCategory');
        $taxCategory->appendChild($this->xml->createElement('cbc:ID', 'VAT'));
        $taxCategory->appendChild($this->xml->createElement('cbc:TaxExemptionReasonCode', '10'));
        $taxCategory->appendChild($this->xml->createElement('cbc:TierRange', '00'));
        $taxCategory->appendChild($this->DetailTaxScheme());

        return $taxCategory;
    }

    protected function DetailTaxScheme()
    {
        $taxScheme = $this->xml->createElement('cac:TaxScheme');
        $taxScheme->appendChild($this->xml->createElement('cbc:ID', '1000'));
        $taxScheme->appendChild($this->xml->createElement('cbc:Name', 'IGV'));
        $taxScheme->appendChild($this->xml->createElement('cbc:TaxTypeCode', 'VAT'));

        return $taxScheme;
    }

    protected function DetailItem()
    {
        $item = $this->xml->createElement('cac:Item');
        $item->appendChild($this->DetailDescription());
        $item->appendChild($this->DetailSellersItemIdentification());

        return $item;
    }

    protected function DetailDescription()
    {
        $description = $this->xml->createElement('cbc:Description');
        $description->appendChild($this->xml->createCDATASection($this->detail->description));

        return $description;
    }

    protected function DetailSellersItemIdentification()
    {
        $sellersItemIdentification = $this->xml->createElement('cac:SellersItemIdentification');
        $sellersItemIdentification->appendChild($this->xml->createElement('cbc:ID', '*'));

        return $sellersItemIdentification;
    }

    protected function DetailPrice()
    {
        $price = $this->xml->createElement('cac:Price');
        $price->appendChild($this->DetailPriceAmountST());

        return $price;
    }

    protected function DetailPriceAmountST()
    {
        $priceAmount = $this->xml->createElement('cbc:PriceAmount', $this->detail->saleSubtotal);
        $priceAmount->setAttribute('currencyID', $this->document->currency);

        return $priceAmount;
    }
}