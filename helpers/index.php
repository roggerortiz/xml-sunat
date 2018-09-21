<?php

/**
 * Devuelve la parte entero de un numero decimal expresado como string
 *
 * @param string $number
 * @return int
 */
function int_val ($number) {
    return intval((float) $number);
}

/**
 * Devuelve el la parte decimal de un numero decimal expresado como string
 *
 * @param string $number
 * @return int
 */
function decimal_val ($number) {
    $partes = explode('.', (float) $number);

    if(count($partes) <= 1) {
        $partes = explode(',', $number);
    }

    if(count($partes) <= 1) {
        $partes[1] = 0;
    }

    return sprintf("%02d", $partes[1]);
}

/**
 * Retorna color de label para la condición de un Alumno.
 *
 * @param  string  $number
 * @param  string  $currency
 * @return string
 */
function numero_a_letras($number, $currency)
{
    $entero = trim(NumeroALetras::convertir(int_val($number)));

    $decimal = decimal_val($number);

    return mb_strtoupper("{$entero} y {$decimal}/100 {$currency}", 'UTF-8');
}