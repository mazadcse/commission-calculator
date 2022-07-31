<?php

namespace Tests\Service;

use Commission\Service\FeeCalculator;
use PHPUnit\Framework\TestCase;

class FeeCalculatorTest extends TestCase
{
    private $feeCalculatorObj;

    public function setUp()
    {
        $this->feeCalculatorObj = new FeeCalculator('input.csv');
    }

    public function testAdd()
    {
        $this->assertEquals( true,   $this->feeCalculatorObj->getFee(false));
    }

}
