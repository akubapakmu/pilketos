<?php

namespace App\Controllers\Admin;

use App\Libraries\CandidateAssetException;
use App\Libraries\CandidateAssets;
use App\Libraries\CandidateTheme;
use App\Models\AuditLogModel;
use App\Models\CandidateModel;
use App\Models\ElectionModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\I18n\Time;

/**
 * Kelola pasangan calon + tema visual (Stage 3).
 *
 * Tema yang diisi di sini dipakai langsung oleh halaman kandidat Stage 2
 * lewat App\Libraries\CandidateTheme: theme_accent (#RRGGBB), theme_layout
 * (split|poster|column|otomatis), theme_background, foto_ketua, foto_wakil,
 * dan theme_asset JSON {hero, texture, artwork, poster}. Kolom hanya berisi
 * nama file acak di public/uploads/candidates (CandidateAssets).
 *
 * Kandidat yang sudah punya baris suara tidak dapat dihapus (FK RESTRICT):
 * gunakan status nonaktif.
 *
 * Stage 4: setelah pemilihan selesai, tambah/hapus pasangan ditolak dan
 * nomor urut & status aktif dipertahankan (hasil akhir tidak berubah).
 *
 * Stage 15: batas jumlah pasangan AKTIF diatur di halaman ini (kolom
 * elections.batas_pasangan, 2-10, bawaan 3). Mengaktifkan pasangan melewati
 * batas ditolak; batas tidak dapat diturunkan di bawah jumlah pasangan aktif.
 * Field profil & kampanye (slogan, bio, karakter, program, pesan, video)
 * dipakai halaman profil paslon (paslon/partials/profile).
 */
class CandidateController extends AdminController
{
    /**
     * Label field untuk pesan audit.
     */
    private const FIELD_LABELS = [
        'nomor_urut'     => 'nomor urut',
        'nama_ketua'     => 'nama ketua',
        'nama_wakil'     => 'nama wakil',
        'visi'           => 'visi',
        'misi'           => 'misi',
        'slogan'         => 'slogan',
        'bio_ketua'      => 'bio ketua',
        'bio_wakil'      => 'bio wakil',
        'karakter_ketua' => 'karakter ketua',
        'karakter_wakil' => 'karakter wakil',
        'program'        => 'program unggulan',
        'pesan_ketua'    => 'pesan ketua',
        'pesan_wakil'    => 'pesan wakil',
        'video_youtube'  => 'video',
        'theme_name'     => 'nama tema',
        'theme_accent'   => 'warna aksen',
        'theme_layout'   => 'layout',
        'status_aktif'   => 'status aktif',
    ];

    /**
     * GET admin/paslon
     */
    public function index()
    {
        $model    = model(CandidateModel::class);
        $snapshot = service('analytics')->snapshot($this->election());
        $counted  = array_column($snapshot['candidates'], 'votes', 'id');
        $rows     = [];

        foreach ($model->getAllOrdered() as $candidate) {
            $id     = (int) $candidate['id'];
            $rows[] = [
                'raw'       => $candidate,
                'theme'     => CandidateTheme::present($candidate),
                'votes'     => $counted[$id] ?? 0,
                'vote_rows' => $model->voteRowCount($id),
                'assets'    => $this->assetFiles($candidate),
            ];
        }

        return $this->render('admin/candidates/index', [
            'title'       => 'Pasangan Calon',
            'rows'        => $rows,
            'activeCount' => count(array_filter($rows, static fn (array $r): bool => (int) $r['raw']['status_aktif'] === 1)),
            'batas'       => ElectionModel::batasPasangan($this->election()),
            'errors'      => (array) (session()->getFlashdata('errors') ?? []),
        ], 'candidates');
    }

    /**
     * POST admin/paslon/batas
     * Stage 15: batas jumlah pasangan aktif untuk pemilihan berjalan.
     */
    public function limit(): RedirectResponse
    {
        $election = $this->election();

        if ($election === null) {
            return redirect()->to('admin/paslon')->with('error', 'Buat jadwal pemilihan dulu (menu Jadwal), lalu atur batas pasangan.');
        }

        if ($this->resultsLocked()) {
            return redirect()->to('admin/paslon')->with('error', self::RESULTS_LOCKED_MESSAGE);
        }

        $raw = trim((string) $this->request->getPost('batas_pasangan'));
        $min = ElectionModel::BATAS_PASANGAN_MIN;
        $max = ElectionModel::BATAS_PASANGAN_MAX;

        if (! ctype_digit($raw) || (int) $raw < $min || (int) $raw > $max) {
            return redirect()->to('admin/paslon')->withInput()->with('errors', [
                'batas_pasangan' => sprintf('Batas pasangan berupa angka %d sampai %d.', $min, $max),
            ]);
        }

        $batas  = (int) $raw;
        $active = $this->activeCount();

        if ($batas < $active) {
            return redirect()->to('admin/paslon')->withInput()->with('errors', [
                'batas_pasangan' => sprintf(
                    'Saat ini ada %d pasangan aktif. Nonaktifkan dulu pasangan yang tidak ikut, atau pilih batas minimal %d.',
                    $active,
                    $active,
                ),
            ]);
        }

        $old = ElectionModel::batasPasangan($election);

        if ($old === $batas) {
            return redirect()->to('admin/paslon')->with('success', sprintf('Batas pasangan aktif tetap %d.', $batas));
        }

        model(ElectionModel::class)->builder()->where('id', (int) $election['id'])->update([
            'batas_pasangan' => $batas,
            'updated_at'     => Time::now()->toDateTimeString(),
        ]);
        $this->forgetElection();

        $this->audit(
            AuditLogModel::CANDIDATE_LIMIT,
            sprintf('Batas pasangan aktif %d -> %d (saat ini %d aktif).', $old, $batas, $active),
            ['election_id' => (int) $election['id']],
        );

        return redirect()->to('admin/paslon')->with('success', sprintf('Batas pasangan aktif sekarang %d.', $batas));
    }

    /**
     * GET admin/paslon/tambah
     */
    public function new()
    {
        if ($this->resultsLocked()) {
            return redirect()->to('admin/paslon')->with('error', self::RESULTS_LOCKED_MESSAGE);
        }

        $max = (int) (model(CandidateModel::class)->builder()->selectMax('nomor_urut', 'max')->get()->getRowArray()['max'] ?? 0);

        // Stage 15: batas penuh -> pasangan baru disiapkan nonaktif dulu.
        $full = $this->activeCount() >= ElectionModel::batasPasangan($this->election());

        return $this->form(null, ['nomor_urut' => min(99, $max + 1), 'status_aktif' => $full ? 0 : 1]);
    }

    /**
     * POST admin/paslon
     */
    public function create()
    {
        return $this->save(null);
    }

    /**
     * GET admin/paslon/{id}/ubah
     */
    public function edit($id = null)
    {
        $candidate = $this->findOr404($id);

        return $this->form($candidate, $candidate);
    }

    /**
     * POST admin/paslon/{id}
     */
    public function update($id = null)
    {
        return $this->save($this->findOr404($id));
    }

    /**
     * POST admin/paslon/{id}/hapus
     */
    public function delete($id = null)
    {
        $candidate = $this->findOr404($id);
        $model     = model(CandidateModel::class);
        $voteRows  = $model->voteRowCount((int) $candidate['id']);
        $label     = sprintf('Pasangan %02d (%s & %s)', $candidate['nomor_urut'], $candidate['nama_ketua'], $candidate['nama_wakil']);

        if ($this->resultsLocked()) {
            return redirect()->to('admin/paslon')->with('error', self::RESULTS_LOCKED_MESSAGE);
        }

        if ($voteRows > 0) {
            return redirect()->to('admin/paslon')->with('error', sprintf(
                '%s sudah memiliki %d baris suara (termasuk riwayat) sehingga tidak dapat dihapus. Nonaktifkan pasangan ini bila perlu.',
                $label,
                $voteRows,
            ));
        }

        try {
            $model->delete((int) $candidate['id']);
        } catch (DatabaseException) {
            return redirect()->to('admin/paslon')->with('error', $label . ' tidak dapat dihapus karena sudah dipakai data suara.');
        }

        $assets = service('candidateAssets');
        foreach ($this->assetFiles($candidate) as $file) {
            $assets->delete($file);
        }

        $this->audit(AuditLogModel::CANDIDATE_DELETE, $label . ' dihapus beserta file tema.');

        return redirect()->to('admin/paslon')->with('success', $label . ' dihapus.');
    }

    /**
     * GET admin/paslon/{id}/intip
     * Pratinjau profil paslon persis seperti yang dilihat pemilih (tanpa surat suara).
     */
    public function preview($id = null)
    {
        $candidate = $this->findOr404($id);
        $others    = array_filter(
            model(CandidateModel::class)->getAllOrdered(),
            static fn (array $c): bool => (int) $c['status_aktif'] === 1 || (int) $c['id'] === (int) $candidate['id'],
        );

        $theme = CandidateTheme::present($candidate);

        if ($theme['video'] !== null) {
            CandidateTheme::allowVideoFrame($this->response);
        }

        return view('admin/candidates/preview', [
            'title'     => sprintf('Pratinjau Pasangan %02d', $candidate['nomor_urut']),
            'candidate' => $theme,
            'active'    => (int) $candidate['status_aktif'] === 1,
            'switcher'  => CandidateTheme::presentAll(array_values($others)),
            'bodyClass' => 'is-admin-preview',
        ]);
    }

    // ------------------------------------------------------------------

    private function form(?array $candidate, array $values)
    {
        $old = session()->getFlashdata('_ci_old_input');

        if (is_array($old['post'] ?? null)) {
            $values = array_merge($values, $old['post']);
        }

        return $this->render('admin/candidates/form', [
            'title'        => $candidate === null ? 'Tambah Pasangan' : sprintf('Ubah Pasangan %02d', $candidate['nomor_urut']),
            'candidate'    => $candidate,
            'values'       => $values,
            'theme'        => $candidate === null ? null : CandidateTheme::present($candidate),
            'files'        => $candidate === null ? [] : $this->assetFiles($candidate),
            'errors'       => (array) (session()->getFlashdata('errors') ?? []),
            'postMax'      => CandidateAssets::iniBytes((string) ini_get('post_max_size')),
            'batas'        => ElectionModel::batasPasangan($this->election()),
            'activeOthers' => $this->activeCount($candidate === null ? null : (int) $candidate['id']),
        ], 'candidates');
    }

    private function save(?array $existing): RedirectResponse
    {
        $locked = $this->resultsLocked();

        if ($locked && $existing === null) {
            return redirect()->to('admin/paslon')->with('error', self::RESULTS_LOCKED_MESSAGE);
        }

        $model  = model(CandidateModel::class);
        $input  = $this->textInput();

        if ($locked) {
            // Stage 4: nomor urut & status aktif menentukan susunan hasil akhir;
            // nilai lama dipertahankan. Nama, visi-misi, dan foto tetap dapat dirapikan.
            $input['nomor_urut']   = (int) $existing['nomor_urut'];
            $input['status_aktif'] = (int) $existing['status_aktif'];
        }

        $data   = $input + ($existing === null ? [] : ['id' => (int) $existing['id']]);
        $back   = $existing === null ? 'admin/paslon/tambah' : 'admin/paslon/' . $existing['id'] . '/ubah';

        if (! $model->validate($data)) {
            return redirect()->to($back)->withInput()->with('errors', $model->errors());
        }

        // Semua file divalidasi & diproses dulu; bila satu gagal, yang lain dibatalkan.
        $assets = service('candidateAssets');
        $stored = [];
        $errors = [];

        // Stage 15: batas pasangan aktif, hanya saat pasangan BARU diaktifkan
        // (tambah aktif, atau nonaktif -> aktif). Mengubah pasangan yang sudah
        // aktif selalu boleh. Dilaporkan bersama kesalahan file (satu kali kirim).
        $activating = (int) $input['status_aktif'] === 1 && ($existing === null || (int) $existing['status_aktif'] !== 1);
        $batas      = ElectionModel::batasPasangan($this->election());

        if ($activating && $this->activeCount($existing === null ? null : (int) $existing['id']) >= $batas) {
            $errors['status_aktif'] = sprintf(
                'Batas %d pasangan aktif sudah penuh. Simpan sebagai nonaktif, nonaktifkan pasangan lain, atau naikkan batas di halaman Pasangan calon.',
                $batas,
            );
        }

        foreach (array_keys(CandidateAssets::SLOTS) as $slot) {
            $file = $this->request->getFile('asset_' . $slot);

            if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                $stored[$slot] = $assets->store($file, $slot, (int) $input['nomor_urut']);
            } catch (CandidateAssetException $e) {
                $errors['asset_' . $slot] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            array_map($assets->delete(...), $stored);

            return redirect()->to($back)->withInput()->with('errors', $errors);
        }

        [$files, $obsolete, $fileChanges] = $this->mergeFiles($existing, $stored, (array) $this->request->getPost('remove'));
        $data = array_merge($data, $files);

        try {
            $saved = $existing === null ? $model->insert($data) !== false : $model->update((int) $existing['id'], $data);
        } catch (DatabaseException $e) {
            log_message('error', 'Simpan kandidat gagal: {msg}', ['msg' => $e->getMessage()]);
            $saved = false;
        }

        if (! $saved) {
            array_map($assets->delete(...), $stored);

            return redirect()->to($back)->withInput()->with('errors', $model->errors() ?: ['Data pasangan gagal disimpan. Periksa nomor urut lalu coba lagi.']);
        }

        array_map($assets->delete(...), $obsolete);

        $label = sprintf('Pasangan %02d (%s & %s)', $input['nomor_urut'], $input['nama_ketua'], $input['nama_wakil']);

        if ($existing === null) {
            $this->audit(AuditLogModel::CANDIDATE_CREATE, $label . ' ditambahkan' . ($fileChanges === [] ? '.' : '; file: ' . implode(', ', $fileChanges) . '.'));

            return redirect()->to('admin/paslon')->with('success', $label . ' ditambahkan.');
        }

        $changes = array_merge($this->changedFields($existing, $input), $fileChanges);

        if ($changes !== []) {
            $this->audit(AuditLogModel::CANDIDATE_UPDATE, $label . ' diubah: ' . implode(', ', $changes) . '.');
        }

        return redirect()->to('admin/paslon')->with('success', $label . ($changes === [] ? ' tidak berubah.' : ' disimpan.'));
    }

    /**
     * Field teks dari form, dirapikan. Field lain di body diabaikan (anti mass assignment).
     *
     * @return array<string, int|string|null>
     */
    private function textInput(): array
    {
        $post = fn (string $key): string => trim(str_replace(["\r\n", "\r"], "\n", (string) $this->request->getPost($key)));

        $accent = strtoupper($post('theme_accent'));
        if (preg_match('/^[0-9A-F]{6}$/', $accent) === 1) {
            $accent = '#' . $accent;
        }

        $number = $post('nomor_urut');
        $layout = $post('theme_layout');
        // Satu baris (spasi & baris baru dirapatkan); kosong = null.
        $line = static fn (string $value): ?string => ($value = trim((string) preg_replace('/\s+/u', ' ', $value))) === '' ? null : $value;
        // Kata karakter: baris baru juga dianggap pemisah.
        $tags = static fn (string $value): ?string => $line((string) preg_replace('/\s*\n\s*/u', ', ', $value));
        // Tautan YouTube -> ID. Tautan tidak dikenali dikirim apa adanya agar
        // ditolak validasi model ("Tautan YouTube tidak dikenali").
        $video = $post('video_youtube');

        return [
            'nomor_urut'     => ctype_digit($number) ? (int) $number : $number,
            'nama_ketua'     => (string) preg_replace('/\s+/u', ' ', $post('nama_ketua')),
            'nama_wakil'     => (string) preg_replace('/\s+/u', ' ', $post('nama_wakil')),
            'visi'           => $post('visi') === '' ? null : $post('visi'),
            'misi'           => $post('misi') === '' ? null : $post('misi'),
            'slogan'         => $line($post('slogan')),
            'bio_ketua'      => $line($post('bio_ketua')),
            'bio_wakil'      => $line($post('bio_wakil')),
            'karakter_ketua' => $tags($post('karakter_ketua')),
            'karakter_wakil' => $tags($post('karakter_wakil')),
            'program'        => $post('program') === '' ? null : $post('program'),
            'pesan_ketua'    => $line($post('pesan_ketua')),
            'pesan_wakil'    => $line($post('pesan_wakil')),
            'video_youtube'  => $video === '' ? null : (CandidateTheme::youtubeId($video) ?? mb_substr($video, 0, 200)),
            'theme_name'     => $post('theme_name') === '' ? null : $post('theme_name'),
            'theme_accent'   => $accent === '' ? null : $accent,
            'theme_layout'   => $layout === '' ? null : $layout,
            'status_aktif'   => $post('status_aktif') === '1' ? 1 : 0,
        ];
    }

    /**
     * Gabungkan file baru / dihapus dengan file lama.
     *
     * @param array<string, string> $stored  slot => nama file baru
     * @param list<mixed>           $remove  slot yang dicentang "hapus"
     *
     * @return array{0: array<string, mixed>, 1: list<string>, 2: list<string>} [kolom, file lama untuk dihapus, ringkasan audit]
     */
    private function mergeFiles(?array $existing, array $stored, array $remove): array
    {
        $current  = $existing === null ? [] : $this->assetFiles($existing);
        $json     = is_array($existing['theme_asset'] ?? null) ? $existing['theme_asset'] : [];
        $columns  = [];
        $obsolete = [];
        $changes  = [];

        foreach (CandidateAssets::SLOTS as $slot => $config) {
            $old = $current[$slot] ?? null;
            $new = $old;

            if (isset($stored[$slot])) {
                $new       = $stored[$slot];
                $changes[] = strtolower($config['label']) . ($old === null ? ' diunggah' : ' diganti');
            } elseif ($old !== null && in_array($slot, $remove, true)) {
                $new       = null;
                $changes[] = strtolower($config['label']) . ' dihapus';
            }

            if ($old !== null && $new !== $old) {
                $obsolete[] = $old;
            }

            if ($config['column'] !== null) {
                $columns[$config['column']] = $new;
            } elseif ($new === null) {
                unset($json[$config['asset']]);
            } else {
                $json[$config['asset']] = $new;
            }
        }

        // Hanya kunci yang dikenal CandidateTheme, dengan urutan tetap.
        $assets = [];
        foreach (CandidateTheme::ASSET_KEYS as $key) {
            if (isset($json[$key]) && is_string($json[$key]) && $json[$key] !== '') {
                $assets[$key] = $json[$key];
            }
        }
        $columns['theme_asset'] = $assets === [] ? null : $assets;

        return [$columns, $obsolete, $changes];
    }

    /**
     * File asset per slot (nama file) dari baris kandidat.
     *
     * @return array<string, string>
     */
    private function assetFiles(array $candidate): array
    {
        $json  = is_array($candidate['theme_asset'] ?? null) ? $candidate['theme_asset'] : [];
        $files = [];

        foreach (CandidateAssets::SLOTS as $slot => $config) {
            $value = $config['column'] !== null ? ($candidate[$config['column']] ?? null) : ($json[$config['asset']] ?? null);

            if (is_string($value) && $value !== '') {
                $files[$slot] = $value;
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function changedFields(array $existing, array $input): array
    {
        $changes = [];

        foreach (self::FIELD_LABELS as $field => $label) {
            $old = $existing[$field] === null ? null : (string) $existing[$field];
            $new = $input[$field] === null ? null : (string) $input[$field];

            if ($old !== $new) {
                $changes[] = $label;
            }
        }

        return $changes;
    }

    /**
     * Jumlah pasangan aktif, tanpa pasangan $exceptId (yang sedang diubah).
     */
    private function activeCount(?int $exceptId = null): int
    {
        $builder = model(CandidateModel::class)->builder()->where('status_aktif', 1);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults();
    }

    private function findOr404(mixed $id): array
    {
        $candidate = is_string($id) && ctype_digit($id) ? model(CandidateModel::class)->find((int) $id) : null;

        if ($candidate === null) {
            throw PageNotFoundException::forPageNotFound('Pasangan calon tidak ditemukan.');
        }

        return $candidate;
    }
}
