<?php

namespace App\Controllers\Admin;

use App\Libraries\AvatarException;
use App\Libraries\VoterAvatars;
use App\Models\AuditLogModel;
use App\Services\VoterDirectory;
use App\Services\VoterType;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;

/**
 * Data pemilih di panel admin (Stage 3): daftar + pencarian + filter,
 * detail (status & riwayat suara, log unlock), ubah status akun, hapus.
 * Stage 11: tambah & ubah satu pemilih lewat form (VoterEditor), selain impor.
 *
 * Student\... dan Teacher\... hanya menetapkan voterType(); tabel siswa
 * dan guru tetap terpisah. Pilihan kandidat hanya tampil di halaman detail
 * (untuk keperluan unlock), tidak di daftar.
 *
 * Stage 4: setelah pemilihan selesai, ubah status & hapus pemilih ditolak
 * (AdminController::resultsLocked()) agar hasil akhir tidak berubah. Stage 11:
 * begitu juga tambah & ubah data (jumlah pemilih, rombel, dsb. bagian hasil).
 *
 * Stage 26: detail menampilkan avatar Living Election Wall; admin dapat
 * menghapus foto avatar yang tidak pantas (tidak dikunci: avatar tidak
 * memengaruhi hasil). Menghapus pemilih ikut menghapus file fotonya.
 */
abstract class VoterController extends AdminController
{
    abstract protected function voterType(): VoterType;

    /**
     * GET admin/siswa | admin/guru
     */
    public function index()
    {
        $type      = $this->voterType();
        $directory = service('voterDirectory');
        $classes   = $type === VoterType::Student ? service('analytics')->classes() : [];
        $filters   = VoterDirectory::filters($type, (array) $this->request->getGet(), $classes);
        $page      = $this->page();
        $election  = $this->election();
        $result    = $directory->paginate($type, $election === null ? null : (int) $election['id'], $filters, $page);

        return $this->render('admin/voters/index', [
            'title'   => 'Data ' . $type->label(),
            'type'    => $type,
            'filters' => $filters,
            'classes' => $classes,
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'offset'  => ($page - 1) * VoterDirectory::PER_PAGE,
            'pager'   => $this->pagerLinks($page, VoterDirectory::PER_PAGE, $result['total']),
            'summary' => service('analytics')->snapshot($election)['summary'][$type === VoterType::Student ? 'students' : 'teachers'],
        ], $type->voterTable());
    }

    /**
     * GET admin/siswa/{id} | admin/guru/{id}
     */
    public function show($id = null)
    {
        $type      = $this->voterType();
        $voter     = $this->findOr404($id);
        $directory = service('voterDirectory');
        $election  = $this->election();

        return $this->render('admin/voters/show', [
            'title'     => $voter['name'],
            'type'      => $type,
            'voter'     => $voter,
            'vote'      => $directory->activeVote($type, (int) $voter['id'], $election === null ? null : (int) $election['id']),
            'history'   => $directory->history($type, (int) $voter['id']),
            'unlocks'   => $directory->unlockLogs($type, (int) $voter['id']),
            'deletable' => $directory->historyCount($type, (int) $voter['id']) === 0,
            'avatar'    => service('voterAvatars')->present($type, $voter),
        ], $type->voterTable());
    }

    /**
     * GET admin/siswa/tambah | admin/guru/tambah
     */
    public function new()
    {
        $type = $this->voterType();

        if ($this->resultsLocked()) {
            return redirect()->to($type->adminPath())->with('error', self::RESULTS_LOCKED_MESSAGE);
        }

        return $this->form(null, ['status_aktif' => '1']);
    }

    /**
     * POST admin/siswa | admin/guru
     */
    public function create()
    {
        return $this->save(null);
    }

    /**
     * GET admin/siswa/{id}/ubah | admin/guru/{id}/ubah
     */
    public function edit($id = null)
    {
        $voter = $this->findOr404($id);

        if ($this->resultsLocked()) {
            return redirect()->to($this->voterType()->adminPath((string) $voter['id']))->with('error', self::RESULTS_LOCKED_MESSAGE);
        }

        return $this->form($voter, $voter);
    }

    /**
     * POST admin/siswa/{id} | admin/guru/{id}
     */
    public function update($id = null)
    {
        return $this->save($this->findOr404($id));
    }

    /**
     * POST admin/siswa/{id}/status  (status_aktif = 0|1)
     * Pemilih nonaktif tidak dapat login dan tidak dihitung dalam analitik.
     */
    public function status($id = null)
    {
        $type   = $this->voterType();
        $voter  = $this->findOr404($id);
        $target = $this->request->getPost('status_aktif');

        // Stage 4: status aktif menentukan suara yang dihitung; setelah selesai dikunci.
        if ($this->resultsLocked()) {
            return redirect()->to($type->adminPath((string) $voter['id']))->with('error', self::RESULTS_LOCKED_MESSAGE);
        }

        if (! in_array($target, ['0', '1'], true)) {
            return redirect()->to($type->adminPath((string) $voter['id']))->with('error', 'Status akun tidak valid.');
        }

        if ((int) $voter['status_aktif'] === (int) $target) {
            return redirect()->to($type->adminPath((string) $voter['id']));
        }

        $type->voterModel()->builder()->where('id', (int) $voter['id'])->update([
            'status_aktif' => (int) $target,
            'updated_at'   => Time::now()->toDateTimeString(),
        ]);

        $verb = $target === '1' ? 'diaktifkan' : 'dinonaktifkan';
        $this->audit($type->auditAction('status'), sprintf(
            '%s %s (%s %s) %s.',
            $type->label(),
            $voter['name'],
            $type->identifierLabel(),
            $voter[$type->identifierColumn()],
            $verb,
        ));

        return redirect()->to($type->adminPath((string) $voter['id']))->with('success', sprintf(
            'Akun %s %s. %s',
            $voter['name'],
            $verb,
            $target === '1' ? 'Pemilih dapat login kembali.' : 'Pemilih tidak dapat login dan tidak dihitung di analitik.',
        ));
    }

    /**
     * POST admin/siswa/{id}/hapus
     * Hanya untuk data tanpa riwayat suara/unlock (mis. salah impor).
     */
    public function delete($id = null)
    {
        $type      = $this->voterType();
        $voter     = $this->findOr404($id);
        $directory = service('voterDirectory');
        $label     = sprintf('%s %s (%s %s)', $type->label(), $voter['name'], $type->identifierLabel(), $voter[$type->identifierColumn()]);

        // Stage 4: jumlah pemilih (partisipasi) bagian dari hasil akhir.
        if ($this->resultsLocked()) {
            return redirect()->to($type->adminPath((string) $voter['id']))->with('error', self::RESULTS_LOCKED_MESSAGE);
        }

        if ($directory->historyCount($type, (int) $voter['id']) > 0) {
            return redirect()->to($type->adminPath((string) $voter['id']))
                ->with('error', $label . ' memiliki riwayat suara sehingga tidak dapat dihapus. Nonaktifkan akun bila perlu.');
        }

        try {
            $type->voterModel()->delete((int) $voter['id']);
        } catch (DatabaseException) {
            return redirect()->to($type->adminPath((string) $voter['id']))
                ->with('error', $label . ' tidak dapat dihapus karena sudah dipakai data suara.');
        }

        service('voterAvatars')->deleteFile($voter['avatar_file'] ?? null);
        $this->audit($type->auditAction('delete'), $label . ' dihapus.');

        return redirect()->to($type->adminPath())->with('success', $label . ' dihapus.');
    }

    /**
     * POST admin/siswa/{id}/avatar/hapus | admin/guru/{id}/avatar/hapus
     * Stage 26: moderasi foto avatar Living Election Wall. Pemilih kembali ke
     * avatar bawaan dan dapat mengunggah foto lain.
     */
    public function removeAvatar($id = null)
    {
        $type  = $this->voterType();
        $voter = $this->findOr404($id);
        $back  = redirect()->to($type->adminPath((string) $voter['id']));

        if (! VoterAvatars::isFileName($voter['avatar_file'] ?? null)) {
            return $back->with('error', $voter['name'] . ' tidak memakai foto avatar.');
        }

        try {
            service('voterAvatars')->reset($type, (int) $voter['id']);
        } catch (AvatarException $e) {
            return $back->with('error', $e->getMessage());
        }

        $this->audit(AuditLogModel::AVATAR_REMOVE, sprintf(
            'Foto avatar %s %s (%s %s) dihapus dari Living Election Wall.',
            strtolower($type->label()),
            $voter['name'],
            $type->identifierLabel(),
            $voter[$type->identifierColumn()],
        ));

        return $back->with('success', 'Foto avatar ' . $voter['name'] . ' dihapus. Avatar bawaan dipakai lagi.');
    }

    private function form(?array $voter, array $values): string
    {
        $type = $this->voterType();
        $old  = session()->getFlashdata('_ci_old_input');

        if (is_array($old['post'] ?? null)) {
            $values = array_merge($values, $old['post']);
        }

        return $this->render('admin/voters/form', [
            'title'   => ($voter === null ? 'Tambah ' : 'Ubah ') . strtolower($type->label()),
            'type'    => $type,
            'voter'   => $voter,
            'values'  => $values,
            'errors'  => (array) (session()->getFlashdata('errors') ?? []),
            'classes' => $type === VoterType::Student ? service('analytics')->classes() : [],
        ], $type->voterTable());
    }

    private function save(?array $existing)
    {
        $type = $this->voterType();
        $back = $existing === null ? $type->adminPath('tambah') : $type->adminPath($existing['id'] . '/ubah');

        if ($this->resultsLocked()) {
            return redirect()->to($existing === null ? $type->adminPath() : $type->adminPath((string) $existing['id']))
                ->with('error', self::RESULTS_LOCKED_MESSAGE);
        }

        $editor = service('voterEditor');
        $input  = [];

        // Hanya kolom form yang dibaca (anti mass assignment).
        foreach ([...$editor->fields($type), 'status_aktif'] as $field) {
            $value         = $this->request->getPost($field);
            $input[$field] = is_string($value) ? $value : '';
        }

        $result = $editor->save($type, $input, $existing);

        if (! $result['ok']) {
            return redirect()->to($back)->withInput()->with('errors', $result['errors']);
        }

        $values = $result['values'];
        $label  = sprintf('%s %s (%s %s)', $type->label(), $values['name'], $type->identifierLabel(), $values[$type->identifierColumn()]);

        if ($existing === null) {
            $this->audit($type->auditAction('create'), $label . ' ditambahkan lewat form' . ($type === VoterType::Student ? ', rombel ' . $values['kelas'] : '') . '.');
            $message = $label . ' ditambahkan.';
        } elseif ($result['changes'] === []) {
            $message = 'Tidak ada perubahan pada data ' . $values['name'] . '.';
        } else {
            $this->audit($type->auditAction('update'), sprintf(
                'Data %s %s (%s %s) diubah: %s.',
                strtolower($type->label()),
                $existing['name'],
                $type->identifierLabel(),
                $existing[$type->identifierColumn()],
                implode('; ', $result['changes']),
            ));
            $message = 'Data ' . $values['name'] . ' disimpan.';
        }

        $redirect = redirect()->to($type->adminPath((string) $result['id']))->with('success', $message);

        return $result['warnings'] === [] ? $redirect : $redirect->with('warning', implode(' ', $result['warnings']));
    }

    private function findOr404(mixed $id): array
    {
        $voter = is_string($id) && ctype_digit($id)
            ? service('voterDirectory')->find($this->voterType(), (int) $id)
            : null;

        if ($voter === null) {
            throw PageNotFoundException::forPageNotFound($this->voterType()->label() . ' tidak ditemukan.');
        }

        return $voter;
    }
}
