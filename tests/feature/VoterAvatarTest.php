<?php

use App\Database\Migrations\AddAvatarToVoters;
use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\AvatarIdentity;
use App\Libraries\VoterAvatars;
use App\Models\AuditLogModel;
use App\Services\VoterType;
use CodeIgniter\Config\Services;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use Config\Homepage;
use Tests\Support\AssetSource;
use Tests\Support\UploadFixture;

/**
 * Stage 26: avatar pemilih untuk Living Election Wall: migration, unggah foto
 * (validasi berlapis, encode ulang 320 x 320 WebP, file lama terhapus),
 * avatar DiceBear acak, kembali ke bawaan, batas 10 perubahan per 10 menit,
 * kartu avatar di dasbor (urutan dasbor Stage 9/10 tetap), moderasi admin.
 * Folder unggahan diganti folder sementara (Services::injectMock).
 *
 * @internal
 */
final class VoterAvatarTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private const STUDENT = ['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true];
    private const TEACHER = ['user_type' => 'teacher', 'teacher_id' => 1, 'isLoggedIn' => true];
    private const ADMIN   = ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true];

    private string $dir;

    /** @var list<string> */
    private array $temp = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avatar-http-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        Services::injectMock('voterAvatars', new VoterAvatars($this->dir));
        Services::resetSingle('throttler');
        AvatarIdentity::useSecret('kunci-uji-stage-26');
        cache()->clean();
        service('renderer')->resetData();
    }

    protected function tearDown(): void
    {
        foreach ([...(glob($this->dir . '/{,.}*', GLOB_BRACE) ?: []), ...$this->temp] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->dir);
        UploadFixture::reset();
        AvatarIdentity::useSecret(null);
        config(Homepage::class)->electionWall = true;
        Time::setTestNow();
        Services::resetSingle('voterAvatars');
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // helper
    // ------------------------------------------------------------------

    private function image(int $width, int $height, string $type = 'png'): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, imagecolorallocate($image, 200, 40, 40));
        $path         = tempnam(sys_get_temp_dir(), 'av');
        $this->temp[] = $path;
        $type === 'png' ? imagepng($image, $path) : imagejpeg($image, $path, 90);

        return $path;
    }

    private function file(string $content): string
    {
        $path         = tempnam(sys_get_temp_dir(), 'av');
        $this->temp[] = $path;
        file_put_contents($path, $content);

        return $path;
    }

    private function stored(): array
    {
        return array_values(array_map('basename', glob($this->dir . '/*') ?: []));
    }

    private function row(string $table = 'students', int $id = 1): array
    {
        return $this->db->table($table)->where('id', $id)->get()->getRowArray();
    }

    /** Unggah lewat fetch (JSON) sebagai siswa 1. */
    private function upload(string $path, string $name, array $session = self::STUDENT, string $url = 'siswa/avatar')
    {
        UploadFixture::attach(['avatar' => ['path' => $path, 'name' => $name]]);

        return $this->withSession($session)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->call('POST', $url, [csrf_token() => csrf_hash()]);
    }

    private function postJson(string $url, array $body, array $session = self::STUDENT)
    {
        return $this->withSession($session)
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->withBodyFormat('json')
            ->post($url, $body);
    }

    private function json($result): array
    {
        return json_decode($result->getJSON(), true);
    }

    private function body($result): string
    {
        return (string) preg_replace('/<!-- DEBUG-VIEW[^>]*-->\s*/', '', (string) $result->response()->getBody());
    }

    // ------------------------------------------------------------------
    // migration & model
    // ------------------------------------------------------------------

    public function testMigrationAddsAvatarColumnsToBothVoterTablesAndRollsBack(): void
    {
        $columns = ['avatar_file', 'avatar_style', 'avatar_seed', 'avatar_updated_at'];

        foreach (['students', 'teachers'] as $table) {
            foreach ($columns as $column) {
                $this->assertTrue($this->db->fieldExists($column, $table), "{$table}.{$column}");
            }
        }

        $migration = new AddAvatarToVoters(Database::forge($this->DBGroup));
        $migration->down();

        foreach (['students', 'teachers'] as $table) {
            $this->db->resetDataCache();
            $this->assertFalse($this->db->fieldExists('avatar_file', $table), $table);
        }

        $migration->up();
        $this->db->resetDataCache();
        $this->assertTrue($this->db->fieldExists('avatar_seed', 'teachers'));
        $this->assertNull($this->row()['avatar_file']);
    }

    public function testAvatarColumnsAreNotMassAssignableByImportOrForms(): void
    {
        foreach ([VoterType::Student, VoterType::Teacher] as $type) {
            foreach (['avatar_file', 'avatar_style', 'avatar_seed', 'avatar_updated_at'] as $column) {
                $this->assertNotContains($column, $type->voterModel()->allowedFields, $type->value . '.' . $column);
            }
        }
    }

    public function testDefaultAvatarIsUsedWithoutWritingTheDatabase(): void
    {
        $avatar = service('voterAvatars')->present(VoterType::Student, $this->row());

        $this->assertNull($avatar['photo']);
        $this->assertFalse($avatar['custom']);
        $this->assertSame(AvatarIdentity::default(VoterType::Student, 1), ['style' => $avatar['style'], 'seed' => $avatar['seed']]);
        $this->assertSame('AF', $avatar['initials']);
        $this->assertNull($this->row()['avatar_updated_at']);
    }

    // ------------------------------------------------------------------
    // unggah foto
    // ------------------------------------------------------------------

    public function testUploadIsReencodedTo320SquareAndReplacesTheOldFile(): void
    {
        $result = $this->upload($this->image(900, 600), 'foto-saya.png');
        $result->assertStatus(200);

        $data = $this->json($result);
        $this->assertSame('ok', $data['status']);
        $this->assertSame([], array_diff($this->stored(), [$this->row()['avatar_file']]));

        $first = $this->row()['avatar_file'];
        $this->assertMatchesRegularExpression('/^s1-[a-f0-9]{16}\.(webp|jpg)$/', $first);
        $this->assertStringNotContainsString('foto-saya', $first);
        $this->assertSame(base_url(VoterAvatars::UPLOAD_DIR . '/' . $first), $data['avatar']['photo']);
        $this->assertTrue($data['avatar']['custom']);

        $info = getimagesize($this->dir . '/' . $first);
        $this->assertSame([320, 320], [$info[0], $info[1]]);
        $this->assertSame(function_exists('imagewebp') ? IMAGETYPE_WEBP : IMAGETYPE_JPEG, $info[2]);

        // Pilihan avatar DiceBear dikosongkan, unggah kedua menghapus file pertama.
        $this->db->table('students')->where('id', 1)->update(['avatar_style' => 'adventurer', 'avatar_seed' => '0123456789abcdef']);
        $this->upload($this->image(400, 400, 'jpg'), 'kedua.jpg')->assertStatus(200);

        $second = $this->row()['avatar_file'];
        $this->assertNotSame($first, $second);
        $this->assertSame([$second], $this->stored());
        $this->assertNull($this->row()['avatar_style']);
        $this->assertNull($this->row()['avatar_seed']);
        $this->assertNotNull($this->row()['avatar_updated_at']);
    }

    public function testUploadRejectsWrongTypesSizesAndDimensions(): void
    {
        $cases = [
            'bukan gambar' => [$this->file('<?php echo 1;'), 'foto.png', 'Isi file bukan foto'],
            'ekstensi'     => [$this->image(300, 300), 'foto.gif', 'JPG, PNG, atau WebP'],
            'heic'         => [$this->image(300, 300), 'IMG_0001.HEIC', 'HEIC'],
            'kecil'        => [$this->image(100, 300), 'kecil.png', 'Minimal 128 × 128 px'],
            'besar'        => [$this->file(str_repeat('x', VoterAvatars::MAX_BYTES + 1)), 'besar.png', 'Foto maksimal 2 MB'],
            'png sebagai jpg' => [$this->image(300, 300), 'foto.jpg', 'Isi file bukan foto'],
        ];

        foreach ($cases as $label => [$path, $name, $message]) {
            $result = $this->upload($path, $name);
            $result->assertStatus(422);
            $this->assertStringContainsString($message, $this->json($result)['message'], $label);
        }

        $this->assertSame([], $this->stored());
        $this->assertNull($this->row()['avatar_file']);
    }

    public function testUploadWithoutJavascriptRedirectsBackToTheCard(): void
    {
        UploadFixture::attach(['avatar' => ['path' => $this->image(500, 700), 'name' => 'potret.png']]);

        $result = $this->withSession(self::STUDENT)->call('POST', 'siswa/avatar', [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo(site_url('siswa') . '#avatar');
        $result->assertSessionHas('success', 'Foto avatar disimpan.');
        $this->assertCount(1, $this->stored());

        // Tanpa file: pesan jelas, bukan error.
        UploadFixture::reset();
        $empty = $this->withSession(self::STUDENT)->call('POST', 'siswa/avatar', [csrf_token() => csrf_hash()]);
        $empty->assertRedirectTo(site_url('siswa') . '#avatar');
        $empty->assertSessionHas('error', 'Pilih foto lebih dulu.');
    }

    // ------------------------------------------------------------------
    // acak, hapus, batas, guru
    // ------------------------------------------------------------------

    public function testRandomAvatarIsSavedAndRemovesThePhoto(): void
    {
        $this->upload($this->image(300, 300), 'a.png')->assertStatus(200);
        $this->assertCount(1, $this->stored());

        $result = $this->postJson('siswa/avatar/acak', ['style' => 'big-smile', 'seed' => 'a1b2c3d4e5f60718']);
        $result->assertStatus(200);

        $row = $this->row();
        $this->assertSame(['big-smile', 'a1b2c3d4e5f60718', null], [$row['avatar_style'], $row['avatar_seed'], $row['avatar_file']]);
        $this->assertSame([], $this->stored());
        $this->assertSame(['photo' => null, 'style' => 'big-smile', 'seed' => 'a1b2c3d4e5f60718'], array_intersect_key($this->json($result)['avatar'], ['photo' => 1, 'style' => 1, 'seed' => 1]));

        foreach ([['style' => 'bottts', 'seed' => 'a1b2c3d4e5f60718'], ['style' => 'adventurer', 'seed' => 'A1B2'], ['style' => 'adventurer', 'seed' => '<svg onload=1>']] as $bad) {
            $this->postJson('siswa/avatar/acak', $bad)->assertStatus(422);
        }

        $this->assertSame('big-smile', $this->row()['avatar_style']);
    }

    public function testRandomAvatarWithoutJavascriptIsPickedByTheServer(): void
    {
        $result = $this->withSession(self::STUDENT)->call('POST', 'siswa/avatar/acak', [csrf_token() => csrf_hash()]);

        $result->assertRedirectTo(site_url('siswa') . '#avatar');
        $this->assertTrue(AvatarIdentity::isStyle($this->row()['avatar_style']));
        $this->assertTrue(AvatarIdentity::isSeed($this->row()['avatar_seed']));
    }

    public function testRemoveReturnsToTheDefaultAvatar(): void
    {
        $this->upload($this->image(300, 300), 'a.png')->assertStatus(200);

        $result = $this->postJson('siswa/avatar/hapus', []);
        $result->assertStatus(200);

        $data = $this->json($result)['avatar'];
        $this->assertNull($data['photo']);
        $this->assertFalse($data['custom']);
        $this->assertSame(AvatarIdentity::default(VoterType::Student, 1)['seed'], $data['seed']);
        $this->assertSame([], $this->stored());
        $this->assertNull($this->row()['avatar_file']);
    }

    public function testChangesAreLimitedToTenPerTenMinutesPerVoter(): void
    {
        for ($i = 0; $i < VoterAvatars::THROTTLE_CAPACITY; $i++) {
            $this->postJson('siswa/avatar/acak', ['style' => 'adventurer', 'seed' => sprintf('%016x', $i)])->assertStatus(200);
        }

        $blocked = $this->postJson('siswa/avatar/acak', ['style' => 'adventurer', 'seed' => 'ffffffffffffffff']);
        $blocked->assertStatus(429);
        $blocked->assertHeader('Retry-After');
        $this->assertSame('throttled', $this->json($blocked)['status']);
        $this->assertSame(sprintf('%016x', 9), $this->row()['avatar_seed']);

        // Kuota per pemilih: guru tidak ikut terblokir.
        $this->postJson('guru/avatar/acak', ['style' => 'avataaars', 'seed' => '1111222233334444'], self::TEACHER)->assertStatus(200);
        $this->assertSame('avataaars', $this->row('teachers')['avatar_style']);
    }

    public function testTeacherUploadUsesTheTeacherTableAndPrefix(): void
    {
        $this->upload($this->image(300, 300), 'guru.png', self::TEACHER, 'guru/avatar')->assertStatus(200);

        $this->assertMatchesRegularExpression('/^t1-[a-f0-9]{16}\.(webp|jpg)$/', (string) $this->row('teachers')['avatar_file']);
        $this->assertNull($this->row()['avatar_file']);
    }

    public function testAvatarRoutesRequireTheVoterSession(): void
    {
        $this->withSession([])->call('POST', 'siswa/avatar/acak', [csrf_token() => csrf_hash()])->assertRedirectTo(site_url('siswa/masuk'));

        $json = $this->withSession([])
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->withBodyFormat('json')
            ->post('guru/avatar/hapus', []);
        $json->assertStatus(401);

        // Siswa tidak dapat memakai route guru.
        $this->postJson('guru/avatar/acak', ['style' => 'adventurer', 'seed' => '0123456789abcdef'])->assertStatus(401);
        $this->assertNull($this->row('teachers')['avatar_style']);
    }

    // ------------------------------------------------------------------
    // dasbor
    // ------------------------------------------------------------------

    public function testDashboardCardSitsInTheAsideAfterTheSchedule(): void
    {
        Time::setTestNow('2026-10-01 09:00:00');
        $this->db->table('elections')->where('id', 1)->update(['start_at' => '2026-10-01 07:00:00', 'end_at' => '2026-10-01 12:00:00']);

        foreach (['siswa' => [self::STUDENT, 'pilihanmu'], 'guru' => [self::TEACHER, 'pilihan Anda']] as $path => [$session, $phrase]) {
            $html = $this->body($this->withSession($session)->get($path));

            $schedule = strpos($html, 'id="schedule-title"');
            $card     = strpos($html, 'id="avatar"');
            $clock    = strpos($html, 'class="float-clock');
            $this->assertNotFalse($card, $path);
            $this->assertTrue($schedule < $card && $card < $clock, $path . ': jadwal -> avatar -> jam');
            $this->assertLessThan($card, strpos($html, 'class="vote-card'), $path . ': kartu status tetap pertama');
            $this->assertMatchesRegularExpression('/<\/aside>\s*<\/main>\s*<footer class="site-footer">/', $html, $path . ': jam tetap elemen terakhir <main>');

            $this->assertStringContainsString('action="' . site_url($path . '/avatar') . '"', $html);
            $this->assertStringContainsString('enctype="multipart/form-data"', $html);
            $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp,image/heic,image/heif"', $html);
            $this->assertStringContainsString('Unggah foto', $html);
            $this->assertStringContainsString('Acak avatar', $html);
            $this->assertStringContainsString('Pakai avatar ini', $html);
            $this->assertStringContainsString('Wall hanya menandai partisipasi, tidak pernah menampilkan ' . $phrase . '.', $html);
            $this->assertStringContainsString('Ilustrasi avatar: DiceBear &mdash; Adventurer oleh Lisa Wischofsky dan Big Smile oleh Ashley Seo (CC BY 4.0), Avataaars oleh Pablo Stanley.', $html);
            $this->assertStringContainsString('assets/js/dashboard-avatar.js', $html);
            $this->assertStringContainsString('assets/css/avatar.css', $html);
            $this->assertMatchesRegularExpression('/<form[^>]*data-avatar-remove hidden>/', $html, 'Hapus foto hanya bila ada foto');
            $this->assertStringContainsString('<dialog class="modal avatar-crop"', $html);
        }

        config(Homepage::class)->electionWall = false;
        service('renderer')->resetData();
        $hidden = $this->body($this->withSession(self::STUDENT)->get('siswa'));
        $this->assertStringNotContainsString('id="avatar"', $hidden);
        $this->assertStringNotContainsString('dashboard-avatar.js', $hidden);
    }

    public function testDashboardShowsThePhotoAndTheParticipationBadgeAfterVoting(): void
    {
        $this->upload($this->image(300, 300), 'a.png')->assertStatus(200);
        Time::setTestNow('2026-10-01 09:00:00');
        $this->db->table('elections')->where('id', 1)->update(['start_at' => '2026-10-01 07:00:00', 'end_at' => '2026-10-01 12:00:00']);
        $this->db->table('student_votes')->insert(['election_id' => 1, 'student_id' => 1, 'candidate_id' => 2, 'status' => 'LOCKED', 'voted_at' => '2026-10-01 08:05:09']);

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa'));

        $this->assertStringContainsString('src="' . esc(base_url('uploads/avatars/' . $this->row()['avatar_file']), 'attr') . '"', $html);
        $this->assertMatchesRegularExpression('/<form[^>]*data-avatar-remove>/', $html);
        $this->assertStringContainsString('class="avatar-card__badge"', $html);
        // Setelah memilih dasbor tetap tanpa tautan ke coblos (Stage 2).
        $this->assertStringNotContainsString('href="' . site_url('siswa/coblos') . '"', $html);
    }

    public function testDashboardAssetsFollowTheMotionAndModuleRules(): void
    {
        // Larangan diperiksa pada kode, bukan komentar.
        $strip = static fn (string $code): string => (string) preg_replace('~/\*.*?\*/~s', '', $code);
        $js    = AssetSource::read('assets/js/dashboard-avatar.js');
        $art   = AssetSource::read('assets/js/avatar-art.js');
        $css   = AssetSource::read('assets/css/avatar.css');

        $this->assertStringContainsString("import { createAvatar, adventurer, avataaars, bigSmile } from '../vendor/dicebear-9.4.2/dicebear.module.js';", $art);
        $this->assertStringContainsString('crypto.getRandomValues', $art);
        $this->assertStringContainsString('IntersectionObserver', $art);
        $this->assertStringContainsString("toBlob((webp)", $js);
        $this->assertStringContainsString("'image/webp', 0.85", $js);
        $this->assertStringContainsString("'image/jpeg', 0.88", $js);
        $this->assertStringContainsString("imageOrientation: 'from-image'", $js);
        $this->assertStringContainsString("'X-CSRF-TOKEN'", $js);
        $this->assertStringContainsString('const TILT_MAX = 6;', $js);
        $this->assertStringContainsString('(hover: hover) and (pointer: fine)', $js);
        $this->assertStringNotContainsString('localStorage', $strip($js));
        $this->assertStringContainsString('perspective: 720px;', $css);
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\) \{[^@]*\.avatar-card__avatar/s', $css);
        foreach (['gradient', 'backdrop-filter'] as $banned) {
            $this->assertStringNotContainsString($banned, $strip($css), $banned);
        }

        // Vendor DiceBear lokal + lisensi (atribusi CC BY 4.0).
        $vendor = FCPATH . 'assets/vendor/dicebear-9.4.2/';
        $this->assertFileExists($vendor . 'dicebear.module.js');
        $license = (string) file_get_contents($vendor . 'LICENSE');
        foreach (['@dicebear/core', 'Lisa Wischofsky', 'Ashley Seo', 'Pablo Stanley', 'CC BY 4.0', 'MIT License'] as $text) {
            $this->assertStringContainsString($text, $license);
        }
    }

    // ------------------------------------------------------------------
    // moderasi admin
    // ------------------------------------------------------------------

    public function testAdminSeesTheAvatarAndCanRemoveAPhotoWithAudit(): void
    {
        $plain = $this->body($this->withSession(self::ADMIN)->get('admin/siswa/1'));
        $this->assertStringContainsString('Avatar Living Election Wall', $plain);
        $this->assertStringContainsString('Avatar bawaan', $plain);
        $this->assertStringNotContainsString('Hapus foto avatar', $plain);

        $this->upload($this->image(300, 300), 'a.png')->assertStatus(200);
        service('renderer')->resetData();

        $html = $this->body($this->withSession(self::ADMIN)->get('admin/siswa/1'));
        $this->assertStringContainsString('Foto unggahan pemilih', $html);
        $this->assertStringContainsString('action="' . site_url('admin/siswa/1/avatar/hapus') . '"', $html);
        $this->assertMatchesRegularExpression('/data-confirm-button="Hapus foto" data-confirm-danger>/', $html);
        $this->assertStringContainsString('assets/js/avatar-art.js', $html);

        $result = $this->withSession(self::ADMIN)->call('POST', 'admin/siswa/1/avatar/hapus', [csrf_token() => csrf_hash()]);
        $result->assertRedirectTo(site_url('admin/siswa/1'));
        $result->assertSessionHas('success');

        $this->assertNull($this->row()['avatar_file']);
        $this->assertSame([], $this->stored());
        $this->seeInDatabase('audit_logs', ['action' => AuditLogModel::AVATAR_REMOVE, 'admin_id' => 1]);
        $this->assertSame('Hapus foto avatar pemilih', AuditLogModel::label(AuditLogModel::AVATAR_REMOVE));

        // Tidak ada foto lagi: ditolak dengan pesan, tanpa audit baru.
        $again = $this->withSession(self::ADMIN)->call('POST', 'admin/siswa/1/avatar/hapus', [csrf_token() => csrf_hash()]);
        $again->assertSessionHas('error');
        $this->assertSame(1, $this->db->table('audit_logs')->where('action', AuditLogModel::AVATAR_REMOVE)->countAllResults());
    }

    public function testDeletingAVoterAlsoDeletesTheAvatarFile(): void
    {
        $this->db->table('teachers')->insert(['nip' => '199001012020121001', 'name' => 'Guru Salah Impor', 'kodeunik' => '01011990', 'status_aktif' => 1]);
        $id = (int) $this->db->insertID();

        $this->upload($this->image(300, 300), 'g.png', ['user_type' => 'teacher', 'teacher_id' => $id, 'isLoggedIn' => true], 'guru/avatar')->assertStatus(200);
        $this->assertCount(1, $this->stored());

        $this->withSession(self::ADMIN)->call('POST', 'admin/guru/' . $id . '/hapus', [csrf_token() => csrf_hash()])
            ->assertRedirectTo(site_url('admin/guru'));

        $this->dontSeeInDatabase('teachers', ['id' => $id]);
        $this->assertSame([], $this->stored());
    }
}
