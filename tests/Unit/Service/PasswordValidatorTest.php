<?php

declare(strict_types=1);

namespace ICO\Tests\Unit\Service;

use ICO\Service\PasswordValidator;
use PHPUnit\Framework\TestCase;

class PasswordValidatorTest extends TestCase
{
    private PasswordValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PasswordValidator();
    }

    // -------------------------------------------------------------------------
    // Mot de passe valide
    // -------------------------------------------------------------------------

    public function testValidPasswordReturnsNull(): void
    {
        $this->assertNull($this->validator->validate('Abcdef1234!@'));
    }

    public function testValidPasswordWithVariousSpecialChars(): void
    {
        $this->assertNull($this->validator->validate('MyPass123#ok!'));
        $this->assertNull($this->validator->validate('SecureP@ss1234'));
        $this->assertNull($this->validator->validate('Tr0ub4dor&3!!!'));
    }

    // -------------------------------------------------------------------------
    // Trop court
    // -------------------------------------------------------------------------

    public function testTooShortReturnsError(): void
    {
        $error = $this->validator->validate('Ab1!');
        $this->assertNotNull($error);
        $this->assertStringContainsString('12', $error);
    }

    public function testExactly11CharsReturnsError(): void
    {
        // 11 chars, sinon toutes les autres règles satisfaites
        $error = $this->validator->validate('Abcdef1234!');
        $this->assertNotNull($error);
        $this->assertStringContainsString('12', $error);
    }

    public function testExactly12CharsIsValid(): void
    {
        $this->assertNull($this->validator->validate('Abcdef1234!@'));
    }

    // -------------------------------------------------------------------------
    // Absence de minuscule
    // -------------------------------------------------------------------------

    public function testMissingLowercaseReturnsError(): void
    {
        $error = $this->validator->validate('ABCDEFGH1234!');
        $this->assertNotNull($error);
        $this->assertStringContainsString('minuscule', $error);
    }

    // -------------------------------------------------------------------------
    // Absence de majuscule
    // -------------------------------------------------------------------------

    public function testMissingUppercaseReturnsError(): void
    {
        $error = $this->validator->validate('abcdefgh1234!');
        $this->assertNotNull($error);
        $this->assertStringContainsString('majuscule', $error);
    }

    // -------------------------------------------------------------------------
    // Absence de chiffre
    // -------------------------------------------------------------------------

    public function testMissingDigitReturnsError(): void
    {
        $error = $this->validator->validate('Abcdefghijk!');
        $this->assertNotNull($error);
        $this->assertStringContainsString('chiffre', $error);
    }

    // -------------------------------------------------------------------------
    // Absence de caractère spécial
    // -------------------------------------------------------------------------

    public function testMissingSpecialCharReturnsError(): void
    {
        $error = $this->validator->validate('Abcdefgh1234');
        $this->assertNotNull($error);
        $this->assertStringContainsString('spécial', $error);
    }

    // -------------------------------------------------------------------------
    // Ordre des vérifications (longueur en premier)
    // -------------------------------------------------------------------------

    public function testLengthCheckedBeforeOtherRules(): void
    {
        // Court ET sans majuscule ni chiffre ni spécial → retourne l'erreur longueur
        $error = $this->validator->validate('abc');
        $this->assertNotNull($error);
        $this->assertStringContainsString('12', $error);
    }
}
