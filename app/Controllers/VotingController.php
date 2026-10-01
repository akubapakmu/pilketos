<?php

namespace App\Controllers;

use App\Libraries\CandidateTheme;
use App\Libraries\DeviceInfo;
use App\Models\CandidateModel;
use App\Services\VoteResult;
use App\Services\VoterType;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Alur voting bersama siswa & guru (Stage 2).
 *
 * Student\VoteController dan Teacher\VoteController hanya menetapkan
 * voterType(); route masing-masing berada di grup filter studentauth /
 * teacherauth. Identitas pemilih selalu dari sesi ("<role>_id"), tidak pernah
 * dari input, sehingga tidak ada IDOR: tidak ada parameter id pemilih/suara.
 *
 * Halaman (Stage 22: bilik suara dipecah menjadi tiga halaman berurutan):
 * - index     : pembuka multi-langkah (coblos) -> tombol "Selanjutnya";
 * - lineup    : "Sekilas paslon" (sekilas-paslon), kartu -> profil lengkap;
 * - candidate : profil lengkap satu paslon aktif (Stage 15, siswa|guru/paslon/0X),
 *               tombol 3D "Pilih Paslon 0X" -> bilik-suara#coblos-0X;
 * - booth     : bilik suara (bilik-suara): surat suara + paku + konfirmasi;
 * - confirm   : konfirmasi tanpa JavaScript (fallback terakhir);
 * - submit    : simpan suara (AJAX JSON atau form POST biasa);
 * - myVote    : pilihan milik pemilih sendiri (setelah memilih / login ulang).
 */
abstract class VotingController extends BaseController
{
    /**
     * Batas request submit per pemilih (anti-spam, bukan pengaman suara
     * ganda; pengaman suara ganda ada di transaction + unique key).
     */
    private const SUBMIT_CAPACITY = 10;
    private const SUBMIT_SECONDS  = 60;

    abstract protected function voterType(): VoterType;

    protected function voterId(): int
    {
        return (int) session()->get($this->voterType()->voterKey());
    }

    /**
     * Profil pemilih aktif (filter auth sudah menjamin ada & aktif).
     */
    protected function voter(): array
    {
        return $this->voterType()->voterModel()->findActive($this->voterId());
    }

    /**
     * GET <siswa|guru>/coblos
     * Stage 22: pembuka multi-langkah (Kenali Calonmu -> Pilih Pasanganmu ->
     * Cek Lagi Pilihanmu), lalu "Selanjutnya" ke Sekilas paslon.
     */
    public function index()
    {
        return $this->boothPage('voting/index', 'Langkah Memilih');
    }

    /**
     * GET <siswa|guru>/sekilas-paslon
     * Stage 22: "Sekilas paslon" menjadi halaman sendiri (dulu bagian coblos).
     */
    public function lineup()
    {
        return $this->boothPage('voting/sekilas', 'Sekilas Paslon');
    }

    /**
     * GET <siswa|guru>/bilik-suara
     * Stage 22: surat suara + paku coblos menjadi halaman sendiri; tujuan
     * tombol "Pilih Paslon 0X" di profil paslon (bilik-suara#coblos-0X).
     */
    public function booth()
    {
        return $this->boothPage('voting/booth', 'Bilik Suara');
    }

    /**
     * GET <siswa|guru>/paslon/{nomorUrut}
     * Stage 15: profil lengkap satu paslon AKTIF (nomor urut, "01" atau "1").
     * Tetap dapat dibuka setelah memilih; tombol "Pilih" hanya tampil saat
     * pemilih masih dapat mencoblos. Tidak ada data suara di halaman ini.
     */
    public function candidate($number = null)
    {
        $type    = $this->voterType();
        $lineup  = CandidateTheme::presentAll(model(CandidateModel::class)->getActiveCandidates());
        $current = null;

        if (is_string($number) && ctype_digit($number) && strlen($number) <= 3) {
            foreach ($lineup as $c) {
                if ($c['number'] === (int) $number) {
                    $current = $c;
                    break;
                }
            }
        }

        if ($current === null) {
            throw PageNotFoundException::forPageNotFound('Pasangan calon tidak ditemukan.');
        }

        if ($current['video'] !== null) {
            CandidateTheme::allowVideo($this->response);
        }

        $state = service('voting')->ballotState($type, $this->voterId());

        return view('paslon/show', [
            'title'   => sprintf('Paslon %s: %s & %s', $current['label'], $current['ketua'], $current['wakil']),
            'type'    => $type,
            'c'       => $current,
            'lineup'  => $lineup,
            'canVote' => $state['canVote'],
            'voted'   => $state['vote'] !== null,
        ]);
    }

    /**
     * GET <siswa|guru>/coblos/yakin/{candidateId}
     * Konfirmasi tanpa JavaScript. Versi JS memakai modal di bilik suara.
     */
    public function confirm($candidateId = null)
    {
        $type  = $this->voterType();
        $state = service('voting')->ballotState($type, $this->voterId());

        if ($state['vote'] !== null) {
            return redirect()->to($type->path('pilihanku'));
        }

        if (! $state['canVote']) {
            return redirect()->to($type->path('bilik-suara'))
                ->with('error', VoteResult::rejected(VoteResult::VOTING_CLOSED, $state['status'])->message());
        }

        $candidate = ctype_digit((string) $candidateId)
            ? model(CandidateModel::class)->where('status_aktif', 1)->find((int) $candidateId)
            : null;

        if ($candidate === null) {
            return redirect()->to($type->path('bilik-suara'))
                ->with('error', VoteResult::rejected(VoteResult::INVALID_CANDIDATE)->message());
        }

        return view('voting/confirm', [
            'title'     => 'Konfirmasi Pilihan',
            'type'      => $type,
            'candidate' => CandidateTheme::present($candidate),
        ]);
    }

    /**
     * POST <siswa|guru>/coblos
     * Body: candidate_id (JSON dari ballot.js atau form POST dari halaman konfirmasi).
     */
    public function submit()
    {
        $type   = $this->voterType();
        $raw    = $this->candidateInput();
        $digits = is_int($raw) ? (string) $raw : (is_string($raw) ? trim($raw) : '');

        if ($digits === '' || ! ctype_digit($digits) || strlen($digits) > 10) {
            return $this->respondVote(VoteResult::rejected(VoteResult::INVALID_CANDIDATE));
        }

        $throttleKey = 'vote_submit_' . $type->value . '_' . $this->voterId();
        if (! service('throttler')->check($throttleKey, self::SUBMIT_CAPACITY, self::SUBMIT_SECONDS)) {
            return $this->respondThrottled();
        }

        $result = service('voting')->castVote(
            $type,
            $this->voterId(),
            (int) $digits,
            DeviceInfo::fromRequest($this->request),
        );

        return $this->respondVote($result);
    }

    /**
     * GET <siswa|guru>/pilihanku
     * Hanya pilihan milik pemilih sendiri: tanpa jumlah suara, peringkat,
     * atau data kandidat lain.
     */
    public function myVote()
    {
        $type  = $this->voterType();
        $state = service('voting')->ballotState($type, $this->voterId());

        if ($state['vote'] === null) {
            return redirect()->to($type->path())
                ->with('error', 'Belum ada suara aktif untuk akun ini.');
        }

        return view('voting/my_vote', [
            'title'     => 'Pilihan Saya',
            'type'      => $type,
            'voter'     => $this->voter(),
            'election'  => $state['election'],
            'vote'      => $state['vote'],
            'candidate' => CandidateTheme::present($state['candidate']),
        ]);
    }

    /**
     * Halaman alur memilih (pembuka, Sekilas paslon, bilik suara). Pemilih yang
     * sudah memilih selalu dialihkan ke pilihanku (tidak ada form voting lagi).
     */
    private function boothPage(string $view, string $title): RedirectResponse|string
    {
        $type  = $this->voterType();
        $state = service('voting')->ballotState($type, $this->voterId());

        if ($state['vote'] !== null) {
            return redirect()->to($type->path('pilihanku'));
        }

        return view($view, [
            'title'      => $title,
            'type'       => $type,
            'voter'      => $this->voter(),
            'election'   => $state['election'],
            'canVote'    => $state['canVote'],
            'candidates' => CandidateTheme::presentAll(model(CandidateModel::class)->getActiveCandidates()),
        ]);
    }

    /**
     * candidate_id dari body JSON (ballot.js) atau form POST (fallback tanpa JS).
     * Field lain (mis. student_id) sengaja diabaikan.
     */
    private function candidateInput(): mixed
    {
        if (! $this->request->is('json')) {
            return $this->request->getPost('candidate_id');
        }

        try {
            return $this->request->getJsonVar('candidate_id');
        } catch (HTTPException) {
            return null;
        }
    }

    private function respondVote(VoteResult $result): RedirectResponse|ResponseInterface
    {
        $type = $this->voterType();

        if ($this->wantsJson()) {
            $payload = [
                'status'  => $result->status,
                'message' => $result->message(),
            ];

            if ($result->electionStatus !== null) {
                $payload['election_status'] = $result->electionStatus;
            }

            if ($result->isOk()) {
                $candidate           = $result->vote['candidate'];
                $payload['title']    = 'SUARA BERHASIL DISIMPAN';
                $payload['detail']   = 'Hak suara Anda telah dikunci.';
                $payload['redirect'] = site_url($type->path('pilihanku'));
                $payload['vote']     = [
                    'number'   => sprintf('%02d', $candidate['nomor_urut']),
                    'ketua'    => $candidate['nama_ketua'],
                    'wakil'    => $candidate['nama_wakil'],
                    'voted_at' => format_waktu($result->vote['voted_at'], 'd MMMM yyyy, HH.mm.ss'),
                ];
            } elseif ($result->status === VoteResult::ALREADY_VOTED) {
                $payload['redirect'] = site_url($type->path('pilihanku'));
            }

            return $this->response->setStatusCode($result->httpStatus())->setJSON($payload);
        }

        if ($result->isOk()) {
            return redirect()->to($type->path('pilihanku'))
                ->with('success', 'SUARA BERHASIL DISIMPAN. Hak suara Anda telah dikunci.');
        }

        $target = $result->status === VoteResult::ALREADY_VOTED ? 'pilihanku' : 'bilik-suara';

        return redirect()->to($type->path($target))->with('error', $result->message());
    }

    private function respondThrottled(): RedirectResponse|ResponseInterface
    {
        $message = 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';

        if ($this->wantsJson()) {
            return $this->response
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) max(1, service('throttler')->getTokenTime()))
                ->setJSON(['status' => 'throttled', 'message' => $message]);
        }

        return redirect()->to($this->voterType()->path('bilik-suara'))->with('error', $message);
    }

    private function wantsJson(): bool
    {
        return $this->request->isAJAX()
            || $this->request->is('json')
            || str_contains(strtolower($this->request->getHeaderLine('Accept')), 'application/json');
    }
}
