<?php

use XMLSunat\DocumentDetail;
use XMLSunat\Document;
use XMLSunat\XML;

require __DIR__ . '/vendor/autoload.php';

// $signer = new \XMLSunat\SignXML();
// $signer->convertPfxToPem('certs/LLAMA-PE-CERTIFICADO-DEMO-10466652186.pfx');

$document = new Document();
$document->number = 'B001-3';
$document->date = '2018-08-30';
$document->type = '03';
$document->currency = 'PEN';
$document->subtotal = '3.39';
$document->igv = '0.61';
$document->total = '4.00';

$detail = new DocumentDetail();
$detail->id = 1;
$detail->description = '1/2 Jarra Chicha Morada';
$detail->quantity = '1.00';
$detail->priceUnit = '4.00';
$detail->saleSubtotal = '3.39';
$detail->saleIgv = '0.61';
$detail->saleValue = '4.00';

$document->AddDetail($detail);

$document->supplier->id = '10466652186';
$document->supplier->name = 'ORTIZ BRICEÑO ROGGER ALEJANDRO';
$document->supplier->address = 'Mz. G Lt. 01 - Urb. Las Capullanas';
$document->supplier->district = 'Trujillo';
$document->supplier->province = 'Trujillo';
$document->supplier->city = 'La Libertad';
$document->supplier->codeCountry = 'PE';
$document->supplier->codeUbigeo = '130101';

$document->customer->id = '9999';
$document->customer->type = '0'; // 0 (CV), 1 (J), 6(N)
$document->customer->name = 'Clientes Varios';
$document->customer->address = 'Direccion';
$document->customer->codeCountry = 'PE';

$xml = new XML($document);
$xml->Generate();