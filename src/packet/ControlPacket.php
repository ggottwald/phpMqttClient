<?php
/**
 * @author Oliver Lorenz
 * @since 2015-04-24
 * Time: 00:32
 */

namespace oliverlorenz\reactphpmqtt\packet;

use oliverlorenz\reactphpmqtt\protocol\Version;

abstract class ControlPacket {

    /** @var $version Version */
    protected $version;

    protected $payload = '';

    protected $identifier;

    public function __construct(Version $version)
    {
        $this->version = $version;
    }

    /**
     * @param Version $version
     * @param string  $rawInput
     * @param int     $topicStart
     * @return static
     */
    public static function parse(Version $version, $rawInput, $topicStart = 2)
    {
        static::checkRawInputValidControlPackageType($rawInput);

        return new static($version);
    }

    protected static function checkRawInputValidControlPackageType($rawInput)
    {
        $packetType = ord($rawInput[0]) >> 4;
        if ($packetType !== static::getControlPacketType()) {
            throw new \RuntimeException('raw input is not valid for this control packet');
        }
    }

    /** @return int */
    public static function getControlPacketType() {
        throw new \RuntimeException('you must overwrite getControlPacketType()');
    }

    protected function getPayloadLength()
    {
        return strlen($this->getPayload());
    }

    public function getPayload()
    {
        return $this->payload;
    }

    protected function getRemainingLength()
    {
        return strlen($this->getVariableHeader()) + $this->getPayloadLength();
    }

    /**
     * @return string
     */
    protected function getFixedHeader()
    {
        // Figure 3.8
        $byte1 = static::getControlPacketType() << 4;
        $byte1 = $this->addReservedBitsToFixedHeaderControlPacketType($byte1);

        $remaining = $this->getRemainingLength();

        $header = chr($byte1);
        do {
            $digit = $remaining % 128;

            $remaining = intval($remaining / 128);

            if ($remaining > 0) {
                $digit |= 0x80;
            }

            $header .= chr($digit);
        } while ($remaining > 0);

        return $header;
    }

    /**
     * @return string
     */
    protected function getVariableHeader()
    {
        return '';
    }

    /**
     * @param $stringToAdd
     */
    public function addRawToPayLoad($stringToAdd)
    {
        $this->payload .= $stringToAdd;
    }

    /**
     * @param $fieldPayload
     */
    public function addLengthPrefixedField($fieldPayload)
    {
        $return = $this->getLengthPrefixField($fieldPayload);
        $this->addRawToPayLoad($return);
    }

    public function getLengthPrefixField($fieldPayload)
    {
        $stringLength = strlen($fieldPayload);
        $msb = $stringLength >> 8;
        $lsb = $stringLength % 256;
        $return = chr($msb);
        $return .= chr($lsb);
        $return .= $fieldPayload;

        return $return;
    }

    public function get()
    {
        return $this->getFixedHeader() .
               $this->getVariableHeader() .
               $this->getPayload();
    }

    /**
     * @param $byte1
     * @return $byte1 unmodified
     */
    protected function addReservedBitsToFixedHeaderControlPacketType($byte1)
    {
        return $byte1;
    }

    /**
     * @param int $startIndex
     * @param string $rawInput
     * @return string
     */
    protected static function getPayloadLengthPrefixFieldInRawInput($startIndex, $rawInput)
    {
        $headerLength = 2;
        $header = substr($rawInput, $startIndex, $headerLength);
        $lengthOfMessage = ord($header[1]);

        return substr($rawInput, $startIndex + $headerLength, $lengthOfMessage);
    }
}
