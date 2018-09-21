<?php

namespace XMLSunat;

class Document
{
    public $number;
    public $date;
    public $type;
    public $currency;
    public $numberWrong;
    public $typeWrong;
    public $conceptCode;
    public $concept;
    public $subtotal;
    public $igv;
    public $total;
    protected $details;

    public $supplier;
    public $customer;

    public function __construct()
    {
        $this->details = array();

        $this->supplier = new Person();
        $this->customer = new Person();
    }

    public function GetDetails()
    {
        return $this->details;
    }

    public function AddDetail(DocumentDetail $detail)
    {
        array_push($this->details, $detail);
    }
}