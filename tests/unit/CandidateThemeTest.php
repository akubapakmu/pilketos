<?php

use App\Libraries\CandidateAssets;
use App\Libraries\CandidateTheme;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Stage 2: tema visual per pasangan calon (warna, asset, teks).
 * Stage 16: satu tampilan untuk semua pasangan (tanpa layout/pola/latar
 * panggung/tekstur/artwork), gambar pembuka & latar penutup, --accent-soft,
 * thumbnail asli YouTube.
 *
 * @internal
 */
final class CandidateThemeTest extends CIUnitTestCase
{
    private function candidate(array $override = []): array
    {
        return $override + [
            'id'               => 7,
            'nomor_urut'       => 2,
            'nama_ketua'       => 'Bagas Prayoga',
            'nama_wakil'       => 'Citra Maheswari',
            'foto_ketua'       => null,
            'foto_wakil'       => null,
            'visi'             => ' Visi pasangan. ',
            'misi'             => "1. Satu\n2) Dua\n- Tiga\n\n  \n* Empat",
            'theme_name'       => 'Deep Forest',
            'theme_accent'     => '#2f5d50',
            'theme_asset'      => null,
            'status_aktif'     => 1,
        ];
    }

    public function testPresentsNumberNamesAndCleansMisi(): void
    {
        $theme = CandidateTheme::present($this->candidate());

        $this->assertSame('02', $theme['label']);
        $this->assertSame('Visi pasangan.', $theme['visi']);
        $this->assertSame(['Satu', 'Dua', 'Tiga', 'Empat'], $theme['misi']);
        $this->assertSame('BP', $theme['ketua_initials']);
        $this->assertSame('CM', $theme['wakil_initials']);
        $this->assertSame('Deep Forest', $theme['theme_name']);
    }

    public function testAccentIsValidatedAndGivesReadableInk(): void
    {
        $dark = CandidateTheme::present($this->candidate());
        $this->assertSame('#2F5D50', $dark['accent']);
        $this->assertSame('#FFFFFF', $dark['accent_ink']);
        $this->assertSame('#2F5D50', $dark['accent_text']);
        $this->assertSame('#E6ECEA', $dark['accent_soft']);
        $this->assertSame('--accent: #2F5D50; --accent-ink: #FFFFFF; --accent-text: #2F5D50; --accent-soft: #E6ECEA;', $dark['style']);

        // Kuning terang: teks di atasnya gelap, dan tidak dipakai sebagai warna teks di kertas.
        $light = CandidateTheme::present($this->candidate(['theme_accent' => '#F2D14B']));
        $this->assertSame('#15141A', $light['accent_ink']);
        $this->assertSame('#15141A', $light['accent_text']);

        // Nilai tidak valid / upaya injeksi CSS jatuh ke warna netral.
        foreach (['red', '#12', 'linear-gradient(red, blue)', '#FFF; background:url(x)', null] as $bad) {
            $this->assertSame('#15141A', CandidateTheme::present($this->candidate(['theme_accent' => $bad]))['accent']);
        }
    }

    public function testContrastRatio(): void
    {
        $this->assertEqualsWithDelta(21.0, CandidateTheme::contrast('#000000', '#FFFFFF'), 0.001);
        $this->assertEqualsWithDelta(1.0, CandidateTheme::contrast('#777777', '#777777'), 0.001);
    }

    public function testEveryPairSharesOneLayoutWithoutPatternsOrStageAssets(): void
    {
        foreach ([1, 2, 3, 4] as $number) {
            $theme = CandidateTheme::present($this->candidate([
                'nomor_urut'       => $number,
                'theme_layout'     => 'poster',
                'theme_background' => 'bg.webp',
                'theme_asset'      => ['texture' => 'tex.webp', 'artwork' => 'art.webp'],
            ]));

            foreach (['layout', 'pattern', 'background', 'texture', 'artwork'] as $gone) {
                $this->assertArrayNotHasKey($gone, $theme, $gone);
            }
        }

        $this->assertSame(['hero', 'banner', 'poster', 'closing'], CandidateTheme::ASSET_KEYS);
    }

    public function testAssetUrlsComeFromUploadDirectoryOnly(): void
    {
        $theme = CandidateTheme::present($this->candidate([
            'foto_ketua'  => 'ketua.webp',
            'theme_asset' => ['hero' => 'hero.webp', 'banner' => '../../app/Config/Database.php', 'closing' => ['bukan-string'], 'poster' => 'poster a.jpg'],
        ]));

        $this->assertStringEndsWith('uploads/candidates/ketua.webp', $theme['photo_ketua']);
        $this->assertNull($theme['photo_wakil']);
        $this->assertStringEndsWith('uploads/candidates/hero.webp', $theme['hero']);
        $this->assertStringEndsWith('uploads/candidates/Database.php', $theme['banner']);
        // File tidak ada di folder upload: tanpa ukuran (bukan membaca app/Config).
        $this->assertNull($theme['banner_size']);
        $this->assertNull($theme['closing']);
        $this->assertStringEndsWith('uploads/candidates/poster%20a.jpg', $theme['poster']);
    }

    public function testBannerSizeIsReadFromTheUploadedFile(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cand-size-' . bin2hex(random_bytes(4));
        mkdir($dir);
        Services::injectMock('candidateAssets', new CandidateAssets($dir));

        try {
            imagepng(imagecreatetruecolor(320, 180), $dir . DIRECTORY_SEPARATOR . 'c01-banner-a.png');

            $theme = CandidateTheme::present($this->candidate(['theme_asset' => ['banner' => 'c01-banner-a.png']]));

            $this->assertSame([320, 180], $theme['banner_size']);
            $this->assertNull(CandidateTheme::present($this->candidate())['banner_size']);
        } finally {
            array_map('unlink', glob($dir . DIRECTORY_SEPARATOR . '*') ?: []);
            rmdir($dir);
            Services::resetSingle('candidateAssets');
        }
    }

    public function testSoftAccentIsASolidLightTint(): void
    {
        $this->assertSame('#F8E8E6', CandidateTheme::soft('#C4432B'));
        $this->assertSame('#E3E3E4', CandidateTheme::soft('#15141A'));
        $this->assertSame('#FFFFFF', CandidateTheme::soft('#FFFFFF'));
        // Aksen tidak valid tetap menghasilkan tint dari warna netral.
        $this->assertSame('#E3E3E4', CandidateTheme::present($this->candidate(['theme_accent' => 'red']))['accent_soft']);
    }

    public function testFallbacksForEmptyData(): void
    {
        $theme = CandidateTheme::present($this->candidate(['theme_name' => '', 'misi' => null, 'nama_wakil' => '']));

        $this->assertSame('Pasangan 02', $theme['theme_name']);
        // Stage 8: nama tema bawaan tidak ditulis ulang di samping "Pasangan 02".
        $this->assertFalse($theme['theme_distinct']);
        $this->assertFalse(CandidateTheme::present($this->candidate(['theme_name' => ' pasangan 02 ']))['theme_distinct']);
        $this->assertTrue(CandidateTheme::present($this->candidate())['theme_distinct']);
        $this->assertSame([], $theme['misi']);
        $this->assertSame('?', $theme['wakil_initials']);
    }

    /**
     * Stage 6: CIEDE2000 untuk aturan netralitas warna identitas sekolah
     * (06-VISUAL-DIRECTION §5.1). Data uji Sharma, Wu & Dalal (2005).
     */
    public function testDeltaE2000MatchesTheReferenceData(): void
    {
        $pairs = [
            [[50, 2.6772, -79.7751], [50, 0, -82.7485], 2.0425],
            [[50, 0, 0], [50, -1, 2], 2.3669],
            [[50, 2.49, -0.001], [50, -2.49, 0.0009], 7.1792],
            [[60.2574, -34.0099, 36.2677], [60.4626, -34.1751, 39.4387], 1.2644],
            [[22.7233, 20.0904, -46.694], [23.0331, 14.973, -42.5619], 2.0373],
            [[90.9257, -0.5406, -0.9208], [88.6381, -0.8985, -0.7239], 1.5381],
        ];

        foreach ($pairs as [$lab1, $lab2, $expected]) {
            $this->assertEqualsWithDelta($expected, CandidateTheme::deltaE2000($lab1, $lab2), 0.0001);
            $this->assertEqualsWithDelta($expected, CandidateTheme::deltaE2000($lab2, $lab1), 0.0001);
        }

        $this->assertEqualsWithDelta(0.0, CandidateTheme::deltaE('#A8628F', '#a8628f'), 0.0001);
        $this->assertEqualsWithDelta(100.0, CandidateTheme::deltaE('#000000', '#FFFFFF'), 0.001);
        // Parijoto vs aksen data seed (terracotta, hijau tua, biru tua): jelas berbeda.
        foreach (['#C4432B', '#2F5D50', '#1B3A6B'] as $accent) {
            $this->assertGreaterThan(20, CandidateTheme::deltaE('#A8628F', $accent), $accent);
        }
        // Ungu yang hampir sama: terlalu mirip.
        $this->assertLessThan(20, CandidateTheme::deltaE('#A8628F', '#9C5A86'));
    }

    // ------------------------------------------------------------------
    // Stage 15: profil & kampanye
    // ------------------------------------------------------------------

    public function testProfileFieldsArePresentedAndEmptyOnesAreBlank(): void
    {
        $theme = CandidateTheme::present($this->candidate([
            'slogan'         => "  Satu tim.\r\n",
            'karakter_ketua' => 'Kreatif, aktif · Jujur, kreatif, Ramah',
            'program'        => "1. Student Voice — Aspirasi siswa.\n- Creative Day",
            'video_youtube'  => 'dQw4w9WgXcQ',
        ]));

        $this->assertSame('Satu tim.', $theme['slogan']);
        $this->assertSame('', $theme['bio_ketua']);
        $this->assertSame('', $theme['pesan_wakil']);
        $this->assertSame(['Kreatif', 'aktif', 'Jujur'], $theme['karakter_ketua']);
        $this->assertSame([], $theme['karakter_wakil']);
        $this->assertSame([['title' => 'Student Voice', 'text' => 'Aspirasi siswa.'], ['title' => 'Creative Day', 'text' => '']], $theme['program']);
        $this->assertSame('dQw4w9WgXcQ', $theme['video']);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $theme['video_url']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0', $theme['video_embed']);
        // Stage 16: thumbnail asli YouTube (hqdefault selalu ada, maxres dicoba paslon.js).
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $theme['video_thumb']);
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', $theme['video_thumb_hd']);

        $none = CandidateTheme::present($this->candidate());
        $this->assertNull($none['video']);
        $this->assertNull($none['video_embed']);
        $this->assertNull($none['video_thumb']);
        $this->assertNull($none['video_thumb_hd']);
        $this->assertSame([], $none['program']);
    }

    public function testProgramItemsSplitTitleAndDescription(): void
    {
        $items = CandidateTheme::programItems("Student Voice — Wadah aspirasi\nCreative Day: Ruang karya\nTalent Space | Dukung bakat\nGreen School - Gerakan bersih\nGo-Green\nJam 07:00 apel\nKlub—Hobi");

        $this->assertSame([
            ['title' => 'Student Voice', 'text' => 'Wadah aspirasi'],
            ['title' => 'Creative Day', 'text' => 'Ruang karya'],
            ['title' => 'Talent Space', 'text' => 'Dukung bakat'],
            ['title' => 'Green School', 'text' => 'Gerakan bersih'],
            ['title' => 'Go-Green', 'text' => ''],
            ['title' => 'Jam 07:00 apel', 'text' => ''],
            ['title' => 'Klub', 'text' => 'Hobi'],
        ], $items);
        $this->assertSame([], CandidateTheme::programItems(null));
    }

    public function testTagsAreDedupedAndCappedAtThree(): void
    {
        $this->assertSame(['Kreatif', 'Aktif', 'Bertanggung jawab'], CandidateTheme::tags("Kreatif;  Aktif\nkreatif, Bertanggung   jawab, Ramah"));
        $this->assertSame([], CandidateTheme::tags(' , ; '));
        $this->assertSame([], CandidateTheme::tags(null));
    }

    public function testYoutubeIdOnlyAcceptsYoutubeLinks(): void
    {
        foreach ([
            'dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1',
            'youtube.com/watch?v=dQw4w9WgXcQ',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ?si=abc',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ] as $link) {
            $this->assertSame('dQw4w9WgXcQ', CandidateTheme::youtubeId($link), $link);
        }

        foreach ([
            '',
            'https://vimeo.com/123456',
            'https://evil.test/watch?v=dQw4w9WgXcQ',
            'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ',
            'javascript:alert(1)//youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/playlist?list=PL123',
            'https://www.youtube.com/watch?v=short',
            'dQw4w9WgXc"',
        ] as $link) {
            $this->assertNull(CandidateTheme::youtubeId($link), $link);
        }
    }

    public function testColumnsBalanceRowsForAnyNumberOfPairs(): void
    {
        $expected = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 3, 6 => 3, 7 => 4, 8 => 4, 9 => 3, 10 => 4];

        foreach ($expected as $count => $columns) {
            $this->assertSame($columns, CandidateTheme::columns($count), (string) $count);
        }

        $this->assertSame(2, CandidateTheme::columns(4, 3));
        $this->assertSame('--cols: 3; --cols-md: 3;', CandidateTheme::gridStyle(5));
        $this->assertSame('--cols: 4; --cols-md: 2;', CandidateTheme::gridStyle(4));
    }
}
