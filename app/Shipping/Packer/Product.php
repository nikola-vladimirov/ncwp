<?php
namespace Nashenski\Shipping\Packer;

defined( 'ABSPATH' ) || exit;

class Product {
    public string $name;
    public Size $size;

    public function __construct( string $name, Size $size ) {
        $this->name = $name;
        $this->size = $size;
    }
}