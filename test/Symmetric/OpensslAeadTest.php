<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Crypt\Symmetric;

use Zend\Crypt\Symmetric\Openssl;
use Zend\Math\Rand;

/**
 *
 * This is a set of unit tests for OpenSSL Authenticated Encrypt with Associated Data (AEAD)
 * support from PHP 7.1+
 */
class OpensslAeadTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->crypt = new Openssl();
        if (! $this->crypt->isAuthEncAvailable()) {
            $this->markTestSkipped('Authenticated encryption is not available on this platform');
        }
    }

    public function testSetGetAad()
    {
        $this->crypt->setMode('gcm');
        $this->crypt->setAad('foo@bar.com');
        $this->assertEquals('foo@bar.com', $this->crypt->getAad());
    }

    /**
     * @expectedException Zend\Crypt\Symmetric\Exception\RuntimeException
     */
    public function testSetAadException()
    {
        $this->crypt->setMode('cbc');
        $this->crypt->setAad('foo@bar.com');
    }

    public function testSetGetGcmTagSize()
    {
        $this->crypt->setMode('gcm');
        $this->crypt->setTagSize(10);
        $this->assertEquals(10, $this->crypt->getTagSize());
    }

    public function testSetGetCcmTagSize()
    {
        $this->crypt->setMode('ccm');
        $this->crypt->setTagSize(28);
        $this->assertEquals(28, $this->crypt->getTagSize());
    }

    /**
     * @expectedException Zend\Crypt\Symmetric\Exception\RuntimeException
     */
    public function testSetTagSizeException()
    {
        $this->crypt->setMode('cbc');
        $this->crypt->setTagSize(10);
    }

    /**
     * @expectedException Zend\Crypt\Symmetric\Exception\InvalidArgumentException
     */
    public function testSetInvalidGcmTagSize()
    {
        $this->crypt->setMode('gcm');
        $this->crypt->setTagSize(18); // gcm supports tag size between 4 and 16
    }

    public function getAuthEncryptionMode()
    {
        return [
            [ 'gcm' ],
            [ 'ccm' ]
        ];
    }

    /**
     * @dataProvider getAuthEncryptionMode
     */
    public function testAuthenticatedEncryption($mode)
    {
        $this->crypt->setMode($mode);
        $this->crypt->setKey(random_bytes($this->crypt->getKeySize()));
        $this->crypt->setSalt(random_bytes($this->crypt->getSaltSize()));

        $plaintext = Rand::getBytes(1024);
        $encrypt = $this->crypt->encrypt($plaintext);
        $tag = $this->crypt->getTag();

        $this->assertEquals($this->crypt->getTagSize(), strlen($tag));
        $this->assertEquals(mb_substr($encrypt, 0, $this->crypt->getTagSize(), '8bit'), $tag);

        $decrypt = $this->crypt->decrypt($encrypt);
        $tag2 = $this->crypt->getTag();
        $this->assertEquals($tag, $tag2);
        $this->assertEquals($plaintext, $decrypt);
    }

    /**
     * @dataProvider getAuthEncryptionMode
     * @expectedException Zend\Crypt\Symmetric\Exception\RuntimeException
     */
    public function testAuthenticationError($mode)
    {
        $this->crypt->setMode($mode);
        $this->crypt->setKey(random_bytes($this->crypt->getKeySize()));
        $this->crypt->setSalt(random_bytes($this->crypt->getSaltSize()));

        $plaintext = Rand::getBytes(1024);
        $encrypt = $this->crypt->encrypt($plaintext);

        // Alter the encrypted message
        $i = rand(0, mb_strlen($encrypt, '8bit') - 1);
        $encrypt[$i] = $encrypt[$i] ^ chr(1);

        $decrypt = $this->crypt->decrypt($encrypt);
    }

    public function testGcmEncryptWithTagSize()
    {
        $this->crypt->setMode('gcm');
        $this->crypt->setKey(random_bytes($this->crypt->getKeySize()));
        $this->crypt->setSalt(random_bytes($this->crypt->getSaltSize()));
        $this->crypt->setTagSize(14);

        $plaintext = Rand::getBytes(1024);
        $encrypt = $this->crypt->encrypt($plaintext);
        $this->assertEquals(14, $this->crypt->getTagSize());
        $this->assertEquals($this->crypt->getTagSize(), strlen($this->crypt->getTag()));
    }

    public function testCcmEncryptWithTagSize()
    {
        $this->crypt->setMode('ccm');
        $this->crypt->setKey(random_bytes($this->crypt->getKeySize()));
        $this->crypt->setSalt(random_bytes($this->crypt->getSaltSize()));
        $this->crypt->setTagSize(24);

        $plaintext = Rand::getBytes(1024);
        $encrypt = $this->crypt->encrypt($plaintext);
        $this->assertEquals(24, $this->crypt->getTagSize());
        $this->assertEquals($this->crypt->getTagSize(), strlen($this->crypt->getTag()));
    }

    /**
     * @dataProvider getAuthEncryptionMode
     */
    public function testAuthenticatedEncryptionWithAdditionalData($mode)
    {
        $this->crypt->setMode($mode);
        $this->crypt->setKey(random_bytes($this->crypt->getKeySize()));
        $this->crypt->setSalt(random_bytes($this->crypt->getSaltSize()));
        $this->crypt->setAad('foo@bar.com');

        $plaintext = Rand::getBytes(1024);
        $encrypt = $this->crypt->encrypt($plaintext);
        $tag = $this->crypt->getTag();

        $this->assertEquals($this->crypt->getTagSize(), strlen($tag));
        $this->assertEquals(mb_substr($encrypt, 0, $this->crypt->getTagSize(), '8bit'), $tag);

        $decrypt = $this->crypt->decrypt($encrypt);
        $tag2 = $this->crypt->getTag();
        $this->assertEquals($tag, $tag2);
        $this->assertEquals($plaintext, $decrypt);
    }

    /**
     * @dataProvider getAuthEncryptionMode
     * @expectedException Zend\Crypt\Symmetric\Exception\RuntimeException
     */
    public function testAuthenticationErrorOnAdditionalData($mode)
    {
        $this->crypt->setMode($mode);
        $this->crypt->setKey(random_bytes($this->crypt->getKeySize()));
        $this->crypt->setSalt(random_bytes($this->crypt->getSaltSize()));
        $this->crypt->setAad('foo@bar.com');

        $plaintext = Rand::getBytes(1024);
        $encrypt = $this->crypt->encrypt($plaintext);

        // Alter the additional authentication data
        $this->crypt->setAad('foo@baz.com');
        $decrypt = $this->crypt->decrypt($encrypt);
    }
}
