<?php

use App\Libraries\CandidateTheme;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Stage 2: tema visual per pasangan calon (warna, layout, asset, teks).
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
            'theme_background' => null,
            'theme_accent'     => '#2f5d50',
            'theme_asset'      => null,
            'theme_layout'     => null,
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
        $this->assertSame('--accent: #2F5D50; --accent-ink: #FFFFFF; --accent-text: #2F5D50;', $dark['style']);

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

    public function testLayoutDiffersPerCandidateAndCanBeChosen(): void
    {
        $this->assertSame('split', CandidateTheme::present($this->candidate(['nomor_urut' => 1]))['layout']);
        $this->assertSame('poster', CandidateTheme::present($this->candidate(['nomor_urut' => 2]))['layout']);
        $this->assertSame('column', CandidateTheme::present($this->candidate(['nomor_urut' => 3]))['layout']);
        $this->assertSame('split', CandidateTheme::present($this->candidate(['nomor_urut' => 4]))['layout']);

        $chosen = CandidateTheme::present($this->candidate(['nomor_urut' => 1, 'theme_layout' => 'column']));
        $this->assertSame('column', $chosen['layout']);
        $this->assertSame('dots', $chosen['pattern']);

        $this->assertSame('poster', CandidateTheme::present($this->candidate(['theme_layout' => 'unknown']))['layout']);
    }

    public function testAssetUrlsComeFromUploadDirectoryOnly(): void
    {
        $theme = CandidateTheme::present($this->candidate([
            'foto_ketua'       => 'ketua.webp',
            'theme_background' => '../../app/Config/Database.php',
            'theme_asset'      => ['hero' => 'hero.webp', 'texture' => ['bukan-string'], 'poster' => 'poster a.jpg'],
        ]));

        $this->assertStringEndsWith('uploads/candidates/ketua.webp', $theme['photo_ketua']);
        $this->assertNull($theme['photo_wakil']);
        $this->assertStringEndsWith('uploads/candidates/Database.php', $theme['background']);
        $this->assertStringEndsWith('uploads/candidates/hero.webp', $theme['hero']);
        $this->assertNull($theme['texture']);
        $this->assertNull($theme['artwork']);
        $this->assertStringEndsWith('uploads/candidates/poster%20a.jpg', $theme['poster']);
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

        $none = CandidateTheme::present($this->candidate());
        $this->assertNull($none['video']);
        $this->assertNull($none['video_embed']);
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
