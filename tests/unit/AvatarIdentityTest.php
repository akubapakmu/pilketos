<?php

use App\Libraries\AvatarIdentity;
use App\Services\VoterType;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Stage 26: avatar bawaan pemilih deterministik (HMAC-SHA256 kunci rahasia
 * aplikasi, "s:{id}" / "t:{id}") tanpa menulis database.
 *
 * @internal
 */
final class AvatarIdentityTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AvatarIdentity::useSecret('kunci-uji-stage-26');
    }

    protected function tearDown(): void
    {
        AvatarIdentity::useSecret(null);
        parent::tearDown();
    }

    public function testDefaultAvatarIsStableAndDerivedFromTheSecretHmac(): void
    {
        $first  = AvatarIdentity::default(VoterType::Student, 12);
        $second = AvatarIdentity::default(VoterType::Student, 12);
        $hash   = hash_hmac('sha256', 's:12', 'kunci-uji-stage-26');

        $this->assertSame($first, $second);
        $this->assertSame(substr($hash, 0, 16), $first['seed']);
        $this->assertMatchesRegularExpression(AvatarIdentity::SEED_PATTERN, $first['seed']);
        $this->assertArrayHasKey($first['style'], AvatarIdentity::STYLES);
        $this->assertSame(array_keys(AvatarIdentity::STYLES)[hexdec(substr($hash, 16, 4)) % 3], $first['style']);
    }

    public function testStudentsAndTeachersWithTheSameIdGetDifferentAvatars(): void
    {
        $this->assertNotSame(AvatarIdentity::default(VoterType::Student, 3)['seed'], AvatarIdentity::default(VoterType::Teacher, 3)['seed']);
        $this->assertNotSame(AvatarIdentity::default(VoterType::Student, 3)['seed'], AvatarIdentity::default(VoterType::Student, 4)['seed']);
    }

    public function testSeedCannotBeRecomputedWithoutTheSecret(): void
    {
        $withSecret = AvatarIdentity::default(VoterType::Student, 7)['seed'];

        AvatarIdentity::useSecret('kunci-lain');
        $this->assertNotSame($withSecret, AvatarIdentity::default(VoterType::Student, 7)['seed']);
        $this->assertStringNotContainsString(substr(hash('sha256', 's:7'), 0, 16), $withSecret);
    }

    public function testAllThreeStylesAppearAcrossVoters(): void
    {
        $styles = [];

        for ($id = 1; $id <= 60; $id++) {
            $styles[AvatarIdentity::default(VoterType::Student, $id)['style']] = true;
        }

        $this->assertEqualsCanonicalizing(array_keys(AvatarIdentity::STYLES), array_keys($styles));
    }

    public function testKeysValidationAndRandomAvatars(): void
    {
        $this->assertSame('s12', AvatarIdentity::key(VoterType::Student, 12));
        $this->assertSame('t3', AvatarIdentity::key(VoterType::Teacher, 3));

        $this->assertTrue(AvatarIdentity::isStyle('big-smile'));
        $this->assertFalse(AvatarIdentity::isStyle('bottts'));
        $this->assertFalse(AvatarIdentity::isStyle(['adventurer']));
        $this->assertTrue(AvatarIdentity::isSeed('0123456789abcdef'));
        $this->assertFalse(AvatarIdentity::isSeed('0123456789ABCDEF'));
        $this->assertFalse(AvatarIdentity::isSeed('0123456789abcdef0'));
        $this->assertFalse(AvatarIdentity::isSeed('<svg>'));

        $random = AvatarIdentity::random();
        $this->assertTrue(AvatarIdentity::isStyle($random['style']));
        $this->assertTrue(AvatarIdentity::isSeed($random['seed']));
    }

    public function testSecretFallsBackToAGeneratedKeyFileOutsidePublic(): void
    {
        if ((string) config(\Config\Encryption::class)->key !== '') {
            $this->markTestSkipped('encryption.key diisi: kunci avatar memakai kunci itu.');
        }

        AvatarIdentity::useSecret(null);
        $secret = AvatarIdentity::secret();

        $this->assertNotSame('', $secret);
        $this->assertSame($secret, AvatarIdentity::secret());
        $this->assertFileExists(WRITEPATH . 'avatar.key');
        $this->assertStringStartsNotWith(FCPATH, WRITEPATH . 'avatar.key');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', trim((string) file_get_contents(WRITEPATH . 'avatar.key')));
    }
}
